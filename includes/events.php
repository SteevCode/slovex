<?php
/**
 * Solvex Report — Event & Status Transition Engine
 * 
 * Enforces complaint lifecycle state machine, optimistic concurrency,
 * append-only event logging, and automated notifications.
 */

if (!defined('SOLVEX_ROOT')) {
    define('SOLVEX_ROOT', dirname(__DIR__));
}

require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';

/**
 * State Transition Matrix
 * 
 * Format:
 * [current_status => [
 *     new_status => [
 *         'roles' => ['student', 'staff', 'admin'],
 *         'require_reason' => bool,
 *         'owner_only' => bool (for student)
 *     ]
 * ]]
 */
function get_transition_matrix(): array
{
    $pendingTransitions = [
        'assigned' => [
            'roles'          => ['staff', 'admin'],
            'require_reason' => false,
        ],
        'in_progress' => [
            'roles'          => ['staff', 'admin'],
            'require_reason' => false,
        ],
        'rejected' => [
            'roles'          => ['staff', 'admin'],
            'require_reason' => true,
        ],
        'duplicate' => [
            'roles'          => ['staff', 'admin'],
            'require_reason' => true,
        ],
    ];

    return [
        'pending'   => $pendingTransitions,
        'submitted' => $pendingTransitions,
        'assigned' => [
            'in_progress' => [
                'roles'          => ['staff', 'admin'],
                'require_reason' => false,
            ],
            'rejected' => [
                'roles'          => ['staff', 'admin'],
                'require_reason' => true,
            ],
            'duplicate' => [
                'roles'          => ['staff', 'admin'],
                'require_reason' => true,
            ],
        ],
        'in_progress' => [
            'on_hold' => [
                'roles'          => ['staff', 'admin'],
                'require_reason' => true,
            ],
            'resolved' => [
                'roles'          => ['staff', 'admin'],
                'require_reason' => true,
            ],
            'duplicate' => [
                'roles'          => ['staff', 'admin'],
                'require_reason' => true,
            ],
        ],
        'on_hold' => [
            'in_progress' => [
                'roles'          => ['staff', 'admin'],
                'require_reason' => false,
            ],
            'resolved' => [
                'roles'          => ['staff', 'admin'],
                'require_reason' => true,
            ],
        ],
        'resolved' => [
            'closed' => [
                'roles'          => ['student', 'admin'],
                'require_reason' => false, // Required for admin via explicit check below
                'owner_only'     => true,  // If student, must be owner
            ],
            'reopened' => [
                'roles'          => ['student', 'staff', 'admin'],
                'require_reason' => true,
                'owner_only'     => true, // If student, must be owner
            ],
        ],
        'reopened' => [
            'assigned' => [
                'roles'          => ['staff', 'admin'],
                'require_reason' => false,
            ],
            'in_progress' => [
                'roles'          => ['staff', 'admin'],
                'require_reason' => false,
            ],
        ],
        'closed' => [
            'reopened' => [
                'roles'          => ['admin'],
                'require_reason' => true,
            ],
        ],
        'rejected' => [
            'reopened' => [
                'roles'          => ['admin', 'student'], // student = appeal once
                'require_reason' => true,
            ],
        ],
    ];
}

/**
 * Execute a status transition on a complaint.
 * 
 * Runs within a transaction, enforces role authorization, verifies reasons,
 * uses optimistic concurrency lock (WHERE id=? AND status=?), logs to complaint_events,
 * and generates appropriate notifications.
 * 
 * @param int $complaintId ID of the complaint
 * @param string $newStatus The target status
 * @param array $actor Authenticated user performing the change
 * @param string|null $reason Reason or notes for the transition
 * @return array ['success' => bool, 'message' => string]
 */
function change_status(int $complaintId, string $newStatus, array $actor, ?string $reason = null): array
{
    $pdo = db();

    // 1. Fetch current complaint details
    $stmt = $pdo->prepare(
        'SELECT id, reference, student_id, department_id, assigned_to, status, title, priority
         FROM complaints
         WHERE id = ?'
    );
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        return ['success' => false, 'message' => 'Complaint not found.'];
    }

    $currentStatus = $complaint['status'];
    $actorRole     = $actor['role'] ?? 'student';
    $actorId       = (int) ($actor['id'] ?? 0);
    $ownerId       = (int) $complaint['student_id'];

    if ($currentStatus === $newStatus) {
        return ['success' => false, 'message' => 'Complaint is already in ' . $newStatus . ' status.'];
    }

    // 2. Validate against transition matrix
    $matrix = get_transition_matrix();
    if (!isset($matrix[$currentStatus][$newStatus])) {
        return [
            'success' => false,
            'message' => "Transition from {$currentStatus} to {$newStatus} is not permitted."
        ];
    }

    $rule = $matrix[$currentStatus][$newStatus];

    // Role check
    if (!in_array($actorRole, $rule['roles'], true)) {
        return [
            'success' => false,
            'message' => 'Your role does not have permission to perform this transition.'
        ];
    }

    // Owner check for students
    if ($actorRole === 'student' && !empty($rule['owner_only']) && $actorId !== $ownerId) {
        return [
            'success' => false,
            'message' => 'Only the reporting student can accept or reopen this complaint.'
        ];
    }

    // Department match check for staff
    if ($actorRole === 'staff') {
        $actorDept = !empty($actor['department_id']) ? (int) $actor['department_id'] : null;
        if ($actorDept !== (int) $complaint['department_id']) {
            return [
                'success' => false,
                'message' => 'Staff members can only update complaints within their own department.'
            ];
        }

        // On-hold >7 days requires admin approval (staff cannot extend beyond)
        // If the complaint already has a 'hold_overdue' event, staff cannot put it back on hold
        if ($newStatus === 'on_hold') {
            $overduCheck = $pdo->prepare(
                "SELECT COUNT(*) FROM complaint_events WHERE complaint_id = ? AND type = 'hold_overdue'"
            );
            $overduCheck->execute([$complaintId]);
            if ((int) $overduCheck->fetchColumn() > 0) {
                return [
                    'success' => false,
                    'message' => 'This complaint has exceeded its on-hold period. Only an admin can approve further hold extension.'
                ];
            }
        }
    }

    // Hold date validation
    if ($newStatus === 'on_hold') {
        $holdDate = $actor['hold_until'] ?? null;
        if (empty($holdDate)) {
            return [
                'success' => false,
                'message' => 'An on-hold target date is required.'
            ];
        }
        $holdTs = strtotime($holdDate);
        $todayTs = strtotime('today');
        $maxTs = strtotime('+7 days', $todayTs);
        if (!$holdTs || $holdTs <= $todayTs) {
            return [
                'success' => false,
                'message' => 'On-hold date must be a valid future date.'
            ];
        }
        if ($holdTs > $maxTs && $actorRole !== 'admin') {
            return [
                'success' => false,
                'message' => 'On-hold duration cannot exceed 7 days without administrator authorization.'
            ];
        }
    }

    // Reason requirement check
    $trimmedReason = trim($reason ?? '');
    if (!empty($rule['require_reason']) && empty($trimmedReason)) {
        return [
            'success' => false,
            'message' => 'A reason is required when changing status to ' . str_replace('_', ' ', $newStatus) . '.'
        ];
    }

    // Admin force-closure requires justification reason
    if ($newStatus === 'closed' && $actorRole === 'admin' && empty($trimmedReason)) {
        return [
            'success' => false,
            'message' => 'Administrator closure requires a justification reason.'
        ];
    }

    // 3. Begin Transaction
    $pdo->beginTransaction();
    try {
        // Construct dynamic UPDATE statement depending on new status
        $updateFields = ['status = :new_status', 'updated_at = NOW()'];
        $params = [
            'new_status'   => $newStatus,
            'complaint_id' => $complaintId,
            'curr_status'  => $currentStatus,
        ];

        if ($newStatus === 'resolved') {
            $updateFields[] = 'resolved_at = NOW()';
        } elseif ($newStatus === 'closed') {
            $updateFields[] = 'closed_at = NOW()';
            $closedBy = ($actorRole === 'student') ? 'student' : 'admin';
            $updateFields[] = 'closed_by = :closed_by';
            $params['closed_by'] = $closedBy;
        } elseif ($newStatus === 'reopened') {
            $updateFields[] = 'resolved_at = NULL';
            $updateFields[] = 'closed_at = NULL';
            $updateFields[] = 'closed_by = NULL';
            $updateFields[] = 'hold_until = NULL';
        } elseif ($newStatus === 'on_hold') {
            // Actor may pass hold_until via actor array (staff complaint page sets this)
            if (!empty($actor['hold_until'])) {
                $updateFields[]       = 'hold_until = :hold_until';
                $params['hold_until'] = $actor['hold_until'];
            }
        } elseif ($newStatus === 'rejected' || $newStatus === 'duplicate') {
            $updateFields[] = 'hold_until = NULL';
        }

        $sql = 'UPDATE complaints SET ' . implode(', ', $updateFields) . ' WHERE id = :complaint_id AND status = :curr_status';
        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($params);

        // Optimistic concurrency check
        if ($updateStmt->rowCount() === 0) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => 'The complaint was updated concurrently by another user. Please refresh and try again.'
            ];
        }

        // 4. Append to complaint_events (strictly append-only)
        $eventStmt = $pdo->prepare(
            'INSERT INTO complaint_events (complaint_id, actor_id, type, old_value, new_value, reason, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $eventStmt->execute([
            $complaintId,
            $actorId ?: null,
            'status_change',
            $currentStatus,
            $newStatus,
            $trimmedReason ?: null,
        ]);

        // 5. Create notifications
        $formattedRef = $complaint['reference'];

        if ($actorRole === 'student') {
            // Student accepted or reopened -> notify assigned staff / department + admins
            $notifMsg = ($newStatus === 'closed')
                ? "Student confirmed resolution for #{$formattedRef}."
                : "Student reopened complaint #{$formattedRef}: \"{$trimmedReason}\"";

            if (!empty($complaint['assigned_to'])) {
                add_notification((int) $complaint['assigned_to'], $complaintId, $notifMsg);
            } else {
                notify_department_staff((int) $complaint['department_id'], $complaintId, $notifMsg);
            }

            // Reopen also notifies all admins
            if ($newStatus === 'reopened') {
                notify_all_admins_event($complaintId, "Complaint #{$formattedRef} was reopened by the student.");
            }
        } else {
            // Staff or Admin updated status -> notify reporting student
            $statusLabel = ucwords(str_replace('_', ' ', $newStatus));
            $notifMsg = "Your complaint #{$formattedRef} has been updated to {$statusLabel}.";
            if (!empty($trimmedReason)) {
                $notifMsg .= " Note: {$trimmedReason}";
            }
            add_notification($ownerId, $complaintId, $notifMsg);

            // Reopen by staff: also notify all admins
            if ($newStatus === 'reopened') {
                notify_all_admins_event($complaintId, "Complaint #{$formattedRef} was reopened by staff.");
            }
        }

        // 6. Critical priority escalation: notify all admins immediately on submission or escalation
        // (Caller may pass priority in the complaint array for this check)
        if (!empty($complaint['priority']) && $complaint['priority'] === 'critical' && in_array($newStatus, ['submitted', 'assigned', 'in_progress'], true)) {
            notify_all_admins_event($complaintId, "[CRITICAL] Complaint #{$formattedRef} requires immediate attention.");
        }

        $pdo->commit();
        return [
            'success' => true,
            'message' => 'Complaint status successfully changed to ' . ucwords(str_replace('_', ' ', $newStatus)) . '.'
        ];

    } catch (Exception $ex) {
        $pdo->rollBack();
        error_log('Status transition failed: ' . $ex->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to update complaint status. Please try again.'
        ];
    }
}

/**
 * Append a general event to a complaint (assignment, priority, hold, duplicate).
 * Strictly append-only.
 */
function add_event(
    int $complaintId,
    ?int $actorId,
    string $type,
    ?string $oldVal = null,
    ?string $newVal = null,
    ?string $reason = null
): void {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO complaint_events (complaint_id, actor_id, type, old_value, new_value, reason, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $complaintId,
        $actorId ?: null,
        $type,
        $oldVal,
        $newVal,
        $reason,
    ]);
}

/**
 * Helper to insert a notification for a user.
 */
function add_notification(int $userId, ?int $complaintId, string $message): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, complaint_id, message, is_read, created_at)
         VALUES (?, ?, ?, 0, NOW())'
    );
    $stmt->execute([$userId, $complaintId, $message]);
}

/**
 * Notify all active admin users (used for critical/reopen escalation).
 */
function notify_all_admins_event(int $complaintId, string $message): void
{
    $pdo  = db();
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
        add_notification((int) $adminId, $complaintId, $message);
    }
}

/**
 * Notify active staff members within a department.
 */
function notify_department_staff(int $departmentId, int $complaintId, string $message): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id FROM users WHERE department_id = ? AND role IN (\'staff\', \'admin\') AND status = \'active\''
    );
    $stmt->execute([$departmentId]);
    $staffList = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($staffList as $staffId) {
        add_notification((int) $staffId, $complaintId, $message);
    }
}
