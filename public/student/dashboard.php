<?php
/**
 * Solvex Report — Student Dashboard
 * 
 * Overview of student complaints:
 * - 4 Stat Cards: Open, In Progress, Resolved Awaiting Action, Closed
 * - Prominent "Report a Problem" CTA
 * - "Needs Your Action" list (Resolved tickets to accept or reopen)
 * - Recent Complaints table/cards
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';

init_session();
require_role('student');

$user   = current_user();
$userId = (int) $user['id'];
$pdo    = db();

// 1. Fetch Student Metrics
$statsStmt = $pdo->prepare('
    SELECT
        COUNT(CASE WHEN status IN (\'pending\', \'submitted\', \'assigned\', \'reopened\') THEN 1 END) AS count_open,
        COUNT(CASE WHEN status IN (\'in_progress\', \'on_hold\') THEN 1 END) AS count_in_progress,
        COUNT(CASE WHEN status = \'resolved\' THEN 1 END) AS count_action_needed,
        COUNT(CASE WHEN status = \'closed\' THEN 1 END) AS count_closed
    FROM complaints
    WHERE student_id = ?
');
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch() ?: [
    'count_open'          => 0,
    'count_in_progress'   => 0,
    'count_action_needed' => 0,
    'count_closed'        => 0,
];

// 2. Fetch "Needs Your Action" Items (Resolved complaints awaiting student closure)
$actionStmt = $pdo->prepare('
    SELECT c.id, c.reference, c.title, c.created_at, c.resolved_at,
           cat.name AS category_name, cat.icon AS category_icon,
           dept.name AS department_name
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    JOIN departments dept ON dept.id = c.department_id
    WHERE c.student_id = ? AND c.status = \'resolved\'
    ORDER BY c.resolved_at DESC
');
$actionStmt->execute([$userId]);
$actionItems = $actionStmt->fetchAll();

$questionStmt = $pdo->prepare('
    SELECT c.id, c.reference, c.title, MAX(com.created_at) AS last_staff_comment
    FROM comments com
    JOIN complaints c ON c.id = com.complaint_id
    JOIN users u ON u.id = com.user_id
    WHERE c.student_id = ?
      AND c.status NOT IN (\'closed\', \'rejected\')
      AND com.is_internal = 0
      AND u.role IN (\'staff\', \'admin\')
      AND com.created_at > COALESCE((
          SELECT MAX(own.created_at) FROM comments own
          WHERE own.complaint_id = c.id AND own.user_id = ?
      ), \'1970-01-01\')
    GROUP BY c.id, c.reference, c.title
    ORDER BY last_staff_comment DESC
');
$questionStmt->execute([$userId, $userId]);
$staffQuestions = $questionStmt->fetchAll();

// 3. Fetch Recent Complaints (Last 5)
$recentStmt = $pdo->prepare('
    SELECT c.id, c.reference, c.title, c.status, c.priority, c.visibility, c.created_at, c.updated_at,
           cat.name AS category_name, cat.icon AS category_icon, cat.target_days,
           dept.name AS department_name,
           loc.name AS location_name
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    JOIN departments dept ON dept.id = c.department_id
    LEFT JOIN locations loc ON loc.id = c.location_id
    WHERE c.student_id = ?
    ORDER BY c.created_at DESC
    LIMIT 5
');
$recentStmt->execute([$userId]);
$recentComplaints = $recentStmt->fetchAll();

$pageTitle = 'Student Portal';
$activeNav = 'dashboard';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-5">

    <!-- Top Greeting & Action Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-5">
        <div class="d-flex align-items-center gap-3">
            <span class="avatar-initials lg" style="background-color: <?= avatar_color($user['full_name']) ?>;">
                <?= e(initials($user['full_name'])) ?>
            </span>
            <div>
                <h1 class="h3 fw-bold text-navy mb-1">Hello, <?= e($user['full_name']) ?></h1>
                <div class="text-muted small">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">Student Portal</span>
                    Reg No: <strong><?= e($user['register_no']) ?></strong> &bull; <?= e($user['email']) ?>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= base_url('student/my_complaints.php') ?>" class="btn-solvex btn-secondary">
                <i class="bi bi-list-task me-1"></i> My Complaints
            </a>
            <a href="<?= base_url('student/report.php') ?>" class="btn-solvex btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Report a Problem
            </a>
        </div>
    </div>

    <!-- 4 Stat Cards (Stripe Style) -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-value"><?= (int)$stats['count_open'] ?></div>
                <div class="stat-label">Open</div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="bi bi-gear-wide-connected"></i>
                </div>
                <div class="stat-value"><?= (int)$stats['count_in_progress'] ?></div>
                <div class="stat-label">In Progress</div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="bi bi-exclamation-circle-fill"></i>
                </div>
                <div class="stat-value text-warning-dark"><?= (int)$stats['count_action_needed'] ?></div>
                <div class="stat-label">Resolved awaiting you</div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div class="stat-value text-success"><?= (int)$stats['count_closed'] ?></div>
                <div class="stat-label">Closed</div>
            </div>
        </div>
    </div>

    <!-- "Needs Your Action" Callout Banner -->
    <?php if (!empty($actionItems) || !empty($staffQuestions)): ?>
        <div class="card-solvex border-0 shadow-sm mb-5 bg-warning-subtle border-start border-4 border-warning">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-bell-fill text-warning-dark fs-5"></i>
                    <h5 class="fw-bold text-navy mb-0">Needs Your Action (<?= count($actionItems) + count($staffQuestions) ?>)</h5>
                </div>
                <p class="text-secondary small mb-3">
                    Verify resolved work, or reply when staff has asked a follow-up question.
                </p>

                <div class="d-flex flex-column gap-2">
                    <?php foreach ($staffQuestions as $q): ?>
                        <div class="p-3 bg-white rounded-3 border d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="badge bg-info-subtle text-primary fw-bold me-2">Staff question</span>
                                <span class="badge bg-primary-subtle text-primary fw-bold me-2 font-monospace"><?= e($q['reference']) ?></span>
                                <span class="fw-semibold text-navy"><?= e($q['title']) ?></span>
                            </div>
                            <a href="<?= base_url('complaint.php?ref=' . urlencode($q['reference'])) ?>#comments" class="btn-solvex btn-secondary btn-sm">
                                Reply <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($actionItems as $item): ?>
                        <div class="p-3 bg-white rounded-3 border d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="badge bg-primary-subtle text-primary fw-bold me-2 font-monospace"><?= e($item['reference']) ?></span>
                                <span class="fw-semibold text-navy"><?= e($item['title']) ?></span>
                                <div class="text-muted small mt-1">
                                    <i class="bi <?= e($item['category_icon']) ?> me-1"></i> <?= e($item['category_name']) ?> &bull; Dept: <?= e($item['department_name']) ?>
                                </div>
                            </div>
                            <a href="<?= base_url('complaint.php?ref=' . urlencode($item['reference'])) ?>" class="btn-solvex btn-accent btn-sm">
                                Review &amp; Verify <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Complaints Table / Cards -->
    <div class="card-solvex border-0 shadow-sm mb-5">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-navy mb-0"><i class="bi bi-clock me-2 text-primary"></i> Your Recent Complaints</h5>
            <a href="<?= base_url('student/my_complaints.php') ?>" class="small text-primary text-decoration-none fw-semibold">
                View All <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <div class="card-body p-0">
            <?php if (empty($recentComplaints)): ?>
                <div class="empty-state py-5 text-center">
                    <img src="<?= asset_url('img/empty-no-complaints.webp') ?>" alt="No complaints reported" width="160" height="120" class="mb-3" loading="lazy">
                    <h3 class="h5 fw-bold text-navy">No complaints reported yet</h3>
                    <p class="text-muted small">Notice any broken furniture, leaking tap, or Wi-Fi outage? Report it directly to get it fixed.</p>
                    <a href="<?= base_url('student/report.php') ?>" class="btn-solvex btn-primary mt-2">
                        <i class="bi bi-plus-lg me-1"></i> Report Your First Problem
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: var(--fs-sm);">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Reference</th>
                                <th>Problem Summary</th>
                                <th>Category &amp; Dept</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Ageing</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentComplaints as $c): ?>
                                <?php
                                    $daysOpen = days_since($c['created_at']);
                                    $targetDays = (int)$c['target_days'];
                                    $chipClass = ($daysOpen > $targetDays) ? 'danger' : (($daysOpen > ($targetDays / 2)) ? 'warning' : '');
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <a href="<?= base_url('complaint.php?ref=' . urlencode($c['reference'])) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= e($c['reference']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-navy text-truncate" style="max-width: 250px;">
                                            <?= e($c['title']) ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="bi bi-geo-alt me-1"></i> <?= e($c['location_name'] ?? 'Main Campus') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small"><i class="bi <?= e($c['category_icon']) ?> me-1 text-primary"></i> <?= e($c['category_name']) ?></div>
                                        <div class="text-muted small" style="font-size: 0.75rem;"><?= e($c['department_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="status-pill <?= e($c['status']) ?>"><?= ucwords(str_replace('_', ' ', e($c['status']))) ?></span>
                                    </td>
                                    <td>
                                        <span class="priority-tag <?= e($c['priority']) ?>"><?= e($c['priority']) ?></span>
                                    </td>
                                    <td>
                                        <span class="days-chip <?= $chipClass ?>">
                                            <i class="bi bi-clock-history"></i> <?= $daysOpen ?>d
                                        </span>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <a href="<?= base_url('complaint.php?ref=' . urlencode($c['reference'])) ?>" class="btn-solvex btn-ghost btn-sm">
                                            View <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
