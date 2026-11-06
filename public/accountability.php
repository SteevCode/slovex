<?php
/**
 * Solvex Report — Public Campus Accountability Leaderboard
 * 
 * Accessible to all campus members:
 * - "Longest Unresolved" Public Complaints
 * - Real Department SLA Performance & Reopen Rates
 * - Strictly zero reporter PII
 */

define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';

init_session();

$user = current_user();
$pdo  = db();

// Filters for leaderboard
$locFilter  = (int) get('location_id', '0');
$deptFilter = (int) get('department_id', '0');

$where = [
    'c.visibility = \'public\'',
    'cat.sensitive = 0',
    'c.status NOT IN (\'closed\', \'rejected\', \'duplicate\')'
];
$params = [];

if ($locFilter > 0) {
    $where[] = 'c.location_id = :loc_id';
    $params[':loc_id'] = $locFilter;
}
if ($deptFilter > 0) {
    $where[] = 'c.department_id = :dept_id';
    $params[':dept_id'] = $deptFilter;
}

$whereSql = implode(' AND ', $where);

// 1. Longest Unresolved Leaderboard Query
$sql = "
    SELECT c.id, c.reference, c.title, c.priority, c.status, c.created_at, c.resolved_at, c.closed_at,
           cat.name AS category_name, cat.icon AS category_icon, cat.target_days,
           dept.name AS department_name, loc.name AS location_name,
           (SELECT COUNT(*) FROM affected a WHERE a.complaint_id = c.id) AS affected_count
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    JOIN departments dept ON dept.id = c.department_id
    LEFT JOIN locations loc ON loc.id = c.location_id
    WHERE {$whereSql}
    ORDER BY DATEDIFF(NOW(), c.created_at) DESC
    LIMIT 20
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leaderboard = $stmt->fetchAll();

// 2. Department Accountability Metrics
$deptSummaryStmt = $pdo->query("
    SELECT d.id, d.name AS dept_name,
           COUNT(DISTINCT c.id) AS total_complaints,
           COUNT(DISTINCT CASE WHEN c.status NOT IN ('closed', 'rejected', 'duplicate') THEN c.id END) AS open_count,
           ROUND(AVG(CASE WHEN c.resolved_at IS NOT NULL THEN DATEDIFF(c.resolved_at, c.created_at) END), 1) AS avg_days_to_resolve,
           ROUND(COUNT(DISTINCT CASE WHEN ce.new_value = 'reopened' THEN c.id END) / NULLIF(COUNT(DISTINCT CASE WHEN c.resolved_at IS NOT NULL OR c.closed_at IS NOT NULL THEN c.id END), 0) * 100, 1) AS reopen_rate
    FROM departments d
    LEFT JOIN complaints c ON c.department_id = d.id
    LEFT JOIN complaint_events ce ON ce.complaint_id = c.id AND ce.type = 'status_change'
    GROUP BY d.id, d.name
    ORDER BY open_count DESC
");
$deptSummary = $deptSummaryStmt->fetchAll();

// Dropdowns
$locations   = $pdo->query('SELECT id, name FROM locations WHERE parent_id IS NULL ORDER BY name ASC')->fetchAll();
$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name ASC')->fetchAll();

$pageTitle = 'Campus Civic Accountability';
$activeNav = 'accountability';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Header Banner -->
    <div class="p-4 p-md-5 rounded-4 text-white mb-5 shadow-sm" style="background: linear-gradient(135deg, var(--clr-navy) 0%, var(--clr-primary-700) 100%);">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-primary text-white text-uppercase px-3 py-1 mb-2 font-monospace">Campus Civic Transparency</span>
                <h1 class="display-6 fw-bold mb-2">Public Resolution Leaderboard</h1>
                <p class="lead text-light opacity-75 mb-0" style="font-size: 1rem;">
                    Transparent tracking of campus issues. The longest open tickets in public facilities are highlighted here to ensure accountability and faster departmental response.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <div class="d-inline-flex flex-column align-items-lg-end bg-white bg-opacity-10 p-3 rounded-3">
                    <span class="small text-uppercase text-light opacity-75">Public Transparency Policy</span>
                    <span class="fw-bold fs-5 text-accent">Zero Reporter PII</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Department Summary Cards -->
    <h3 class="h5 fw-bold text-navy mb-3"><i class="bi bi-bar-chart-line text-primary me-2"></i>Department Performance Overview</h3>
    <div class="row g-3 mb-5">
        <?php foreach ($deptSummary as $dept): ?>
            <?php 
                $openCnt = (int) $dept['open_count'];
                $avgDays = $dept['avg_days_to_resolve'] !== null ? $dept['avg_days_to_resolve'] : 'N/A';
                $reopenRate = $dept['reopen_rate'] !== null ? $dept['reopen_rate'] . '%' : '0.0%';
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card-solvex border-0 shadow-sm p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h4 class="h6 fw-bold text-navy mb-0"><?= e($dept['dept_name']) ?></h4>
                        <span class="badge bg-light text-secondary border"><?= (int)$dept['total_complaints'] ?> Total</span>
                    </div>
                    <div class="row g-2 mt-2 pt-2 border-top text-center" style="font-size: 0.8125rem;">
                        <div class="col-4 border-end">
                            <div class="text-muted small">Open</div>
                            <div class="fw-bold fs-6 <?= $openCnt > 5 ? 'text-danger' : 'text-primary' ?>"><?= $openCnt ?></div>
                        </div>
                        <div class="col-4 border-end">
                            <div class="text-muted small">Avg Fix</div>
                            <div class="fw-bold fs-6 text-navy"><?= $avgDays ?><?= is_numeric($avgDays) ? 'd' : '' ?></div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Reopen</div>
                            <div class="fw-bold fs-6 <?= (float)$reopenRate > 15 ? 'text-warning' : 'text-success' ?>"><?= $reopenRate ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Leaderboard Filter -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <h3 class="h5 fw-bold text-navy mb-0">
            <i class="bi bi-hourglass-split text-warning me-2"></i>Longest Unresolved Public Problems
        </h3>
        <form method="GET" action="<?= base_url('accountability.php') ?>" class="d-flex gap-2">
            <select name="department_id" class="form-select-solvex form-select-sm" onchange="this.form.submit()">
                <option value="0">All Departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="location_id" class="form-select-solvex form-select-sm" onchange="this.form.submit()">
                <option value="0">All Zones</option>
                <?php foreach ($locations as $l): ?>
                    <option value="<?= (int)$l['id'] ?>" <?= $locFilter === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($deptFilter > 0 || $locFilter > 0): ?>
                <a href="<?= base_url('accountability.php') ?>" class="btn-solvex btn-ghost btn-sm" title="Clear"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Leaderboard Table -->
    <div class="card-solvex border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($leaderboard)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-check-circle-fill text-success fs-2 mb-2 d-block"></i>
                    No overdue or unresolved public issues found matching the criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: var(--fs-sm);">
                        <thead class="table-light text-muted text-uppercase small">
                            <tr>
                                <th class="ps-4">Rank</th>
                                <th>Reference</th>
                                <th>Problem Summary</th>
                                <th>Department &amp; Zone</th>
                                <th>Priority</th>
                                <th>Days Open</th>
                                <th>Affected</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; ?>
                            <?php foreach ($leaderboard as $row): ?>
                                <?php 
                                    $daysOpen = days_open($row);
                                    $targetDays = (int) $row['target_days'];
                                    $isOverdue = ($daysOpen > $targetDays);
                                ?>
                                <tr>
                                    <td class="ps-4 font-monospace fw-bold text-muted">#<?= $rank++ ?></td>
                                    <td>
                                        <a href="<?= base_url('complaint.php?ref=' . urlencode($row['reference'])) ?>" class="font-monospace text-primary fw-bold text-decoration-none">
                                            <?= e($row['reference']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-navy text-truncate" style="max-width: 280px;" title="<?= e($row['title']) ?>">
                                            <?= e($row['title']) ?>
                                        </div>
                                        <div class="small text-muted"><i class="bi <?= e($row['category_icon']) ?> me-1"></i><?= e($row['category_name']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark small"><?= e($row['department_name']) ?></div>
                                        <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= e($row['location_name'] ?? 'Main Campus') ?></div>
                                    </td>
                                    <td><span class="priority-tag <?= e($row['priority']) ?>"><?= e($row['priority']) ?></span></td>
                                    <td>
                                        <span class="days-chip <?= $isOverdue ? 'danger' : 'warning' ?>">
                                            <i class="bi bi-clock-history"></i> <?= $daysOpen ?> days
                                        </span>
                                    </td>
                                    <td>
                                        <span class="small fw-semibold text-secondary">
                                            <i class="bi bi-people me-1"></i> <?= (int)$row['affected_count'] ?>
                                        </span>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <a href="<?= base_url('complaint.php?ref=' . urlencode($row['reference'])) ?>" class="btn-solvex btn-ghost btn-sm py-1 px-2">
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

