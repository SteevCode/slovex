<?php
/**
 * Solvex Report — Lazy Maintenance Engine
 *
 * Runs at most once per minute (throttled via settings table).
 * Idempotent & concurrency-safe.
 *
 * Rules:
 *  1. Auto-close complaints that have been 'resolved' for > 5 days
 *     (closed_by = 'auto', writes event, notifies student)
 *  2. Flag on-hold complaints that have exceeded hold_until or 7 days
 *     by writing a 'hold_overdue' event (once per complaint)
 *  3. Send a one-time notification for complaints unassigned > 24 hours
 *     (tracked via a 'notified_unassigned' event so it fires only once)
 */

if (!defined('SOLVEX_ROOT')) {
    define('SOLVEX_ROOT', dirname(__DIR__));
}

require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/events.php';

/**
 * Entry point. Call once per dashboard page load.
 * Uses an exclusive GET_LOCK() so concurrent requests cannot double-fire.
 */
function run_maintenance(): void
{
    $pdo = db();

    // ── Throttle: at most once per 60 seconds ───────────────────
    $row = $pdo->query("SELECT `value` FROM settings WHERE `key` = 'maintenance_last_run'")->fetch();
    if ($row) {
        $lastRun = new DateTime($row['value'], new DateTimeZone('Asia/Kolkata'));
        $now     = new DateTime('now',          new DateTimeZone('Asia/Kolkata'));
        if (($now->getTimestamp() - $lastRun->getTimestamp()) < 60) {
            return; // Ran less than 60 seconds ago – skip
        }
    }

    // ── Acquire advisory lock to prevent concurrent double-runs ─
    $lockResult = $pdo->query("SELECT GET_LOCK('solvex_maintenance', 0)")->fetchColumn();
    if (!$lockResult) {
        return; // Another request holds the lock right now
    }

    try {
        // Update last-run timestamp immediately (before slow work, so concurrent
        // requests that bypass the lock check will also see the updated time)
        $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES ('maintenance_last_run', NOW())
                        ON DUPLICATE KEY UPDATE `value` = NOW()")->execute();

        maintenance_auto_close($pdo);
        maintenance_flag_hold_overdue($pdo);
        maintenance_notify_unassigned($pdo);

    } finally {
        $pdo->query("SELECT RELEASE_LOCK('solvex_maintenance')");
    }
}

/**
 * Rule 1: Auto-close resolved complaints after 5 days with no student action.
 */
function maintenance_auto_close(\PDO $pdo): void
{
    // Find all resolved complaints where resolved_at > 5 days ago
    $stmt = $pdo->query(
        "SELECT id, reference, student_id, department_id, assigned_to, status
         FROM complaints
         WHERE status = 'resolved'
           AND resolved_at <= NOW() - INTERVAL 5 DAY"
    );
    $candidates = $stmt->fetchAll();

    foreach ($candidates as $c) {
        // Optimistic lock: UPDATE only if still 'resolved'
        $upd = $pdo->prepare(
            "UPDATE complaints
             SET status = 'closed', closed_at = NOW(), closed_by = 'auto', updated_at = NOW()
             WHERE id = ? AND status = 'resolved'"
        );
        $upd->execute([$c['id']]);

        if ($upd->rowCount() === 0) {
            continue; // Already changed by someone else
        }

        // Append audit event
        add_event(
            (int) $c['id'],
            null, // system actor
            'status_change',
            'resolved',
            'closed',
            'Auto-closed after 5 days with no student response.'
        );

        // Notify student
        add_notification(
            (int) $c['student_id'],
            (int) $c['id'],
            "Your complaint #{$c['reference']} was automatically closed after 5 days. " .
            "If the issue persists, please submit a new report."
        );

        error_log("[maintenance] Auto-closed complaint #{$c['reference']} (id={$c['id']})");
    }
}

/**
 * Rule 2: Flag on-hold complaints past hold_until date or held > 7 days.
 * Writes a 'hold_overdue' event once per complaint; does not auto-change status.
 */
function maintenance_flag_hold_overdue(\PDO $pdo): void
{
    // Find on-hold complaints that have exceeded their hold_until date or held > 7 days
    $stmt = $pdo->query(
        "SELECT c.id, c.reference, c.student_id, c.department_id, c.assigned_to,
                c.hold_until, c.created_at
         FROM complaints c
         WHERE c.status = 'on_hold'
           AND (
               (c.hold_until IS NOT NULL AND c.hold_until < CURDATE())
               OR (c.hold_until IS NULL AND c.updated_at <= NOW() - INTERVAL 7 DAY)
           )
           AND NOT EXISTS (
               SELECT 1 FROM complaint_events ce
               WHERE ce.complaint_id = c.id AND ce.type = 'hold_overdue'
           )"
    );
    $candidates = $stmt->fetchAll();

    foreach ($candidates as $c) {
        // Write a one-time hold_overdue event
        add_event(
            (int) $c['id'],
            null,
            'hold_overdue',
            null,
            null,
            'On-hold period has expired. Admin approval required to extend.'
        );

        // Notify assigned staff and all dept admins
        $msg = "Complaint #{$c['reference']} has exceeded its hold period. Admin approval is required.";
        if (!empty($c['assigned_to'])) {
            add_notification((int) $c['assigned_to'], (int) $c['id'], $msg);
        }
        notify_department_staff((int) $c['department_id'], (int) $c['id'], $msg);
        notify_all_admins((int) $c['id'], $msg);
    }
}

/**
 * Rule 3: Send a one-time notification for complaints unassigned > 24 hours.
 */
function maintenance_notify_unassigned(\PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT c.id, c.reference, c.department_id
         FROM complaints c
         WHERE c.status IN ('submitted', 'reopened')
           AND c.assigned_to IS NULL
           AND c.created_at <= NOW() - INTERVAL 24 HOUR
           AND NOT EXISTS (
               SELECT 1 FROM complaint_events ce
               WHERE ce.complaint_id = c.id AND ce.type = 'notified_unassigned'
           )"
    );
    $candidates = $stmt->fetchAll();

    foreach ($candidates as $c) {
        // Mark as notified (append-only event)
        add_event(
            (int) $c['id'],
            null,
            'notified_unassigned',
            null,
            null,
            'Unassigned notification sent after 24 hours.'
        );

        $msg = "Complaint #{$c['reference']} has been unassigned for over 24 hours and needs attention.";
        notify_department_staff((int) $c['department_id'], (int) $c['id'], $msg);
        notify_all_admins((int) $c['id'], $msg);
    }
}

/**
 * Notify all active admin users.
 */
function notify_all_admins(int $complaintId, string $message): void
{
    $pdo  = db();
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
        add_notification((int) $adminId, $complaintId, $message);
    }
}

/**
 * Write a row to admin_log for audit trail.
 */
function admin_log(int $actorId, string $action, string $target, string $detail = ''): void
{
    $pdo = db();
    $pdo->prepare(
        'INSERT INTO admin_log (actor_id, action, target, detail, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    )->execute([$actorId, $action, $target, $detail ?: null]);
}

