<?php
/**
 * Solvex Report — Campus Accountability & SLA Leaderboard
 * 
 * Public accountability view accessible to all authenticated campus members:
 * - "Longest Unresolved" Public Complaints Leaderboard
 * - Department resolution rate & SLA performance summary
 * - Transparent civic tracking
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';

init_session();
require_login();

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
    SELECT c.id, c.reference, c.title, c.priority, c.status, c.created_at,
           cat.name AS category_name, cat.icon AS category_icon, cat.target_days,
           dept.name AS department_name, loc.name AS location_name,
           (SELECT COUNT(*) FROM affected a WHERE a.complaint_id = c.id) AS affected_count,
           DATEDIFF(NOW(), c.created_at) AS days_open
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
           COUNT(c.id) AS total_complaints,
           COUNT(CASE WHEN c.status NOT IN ('closed', 'rejected', 'duplicate') THEN 1 END) AS open_count,
           ROUND(AVG(CASE WHEN c.status NOT IN ('closed', 'rejected', 'duplicate') THEN DATEDIFF(NOW(), c.created_at) END), 1) AS avg_days_open,
           ROUND(COUNT(CASE WHEN c.status = 'reopened' THEN 1 END) / NULLIF(COUNT(c.id), 0) * 100, 1) AS reopen_rate
    FROM departments d
    LEFT JOIN complaints c ON c.department_id = d.id
    GROUP BY d.id, d.name
    ORDER BY open_count DESC
");
$deptSummary = $deptSummaryStmt->fetchAll();

// Dropdowns
$locations   = $pdo->query('SELECT id, name FROM locations WHERE parent_id IS NULL ORDER BY name ASC')->fetchAll();
$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name ASC')->fetchAll();

$pageTitle = 'Campus Accountability';
$activeNav = 'accountability';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Header Banner -->
    <div class="p-4 p-md-5 rounded-4 bg-navy text-white mb-5 shadow-sm">
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
    <div class="card-solvex border-0 shadow-sm mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: var(--fs-sm);">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-4">Department</th>
                            <th>Total Logged</th>
                            <th>Currently Open</th>
                            <th>Avg Days Unresolved</th>
                            <th class="pe-4">Reopen Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deptSummary as $ds): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-navy"><?= e($ds['dept_name']) ?></td>
                                <td><?= (int)$ds['total_complaints'] ?></td>
                                <td>
                                    <span class="badge <?= ($ds['open_count'] > 5) ? 'bg-danger' : 'bg-primary' ?>">
                                        <?= (int)$ds['open_count'] ?> open
                                    </span>
                                </td>
                                <td>
                                    <strong><?= (float)($ds['avg_days_open'] ?? 0) ?> days</strong>
                                </td>
                                <td class="pe-4">
                                    <?= (float)($ds['reopen_rate'] ?? 0) ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Leaderboard Filter -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <h3 class="h5 fw-bold text-navy mb-0"><i class="bi bi-hourglass-bottom text-danger me-2"></i>Longest Unresolved Public Reports</h3>
        <form method="GET" action="<?= base_url('admin/accountability.php') ?>" class="d-flex gap-2">
            <select class="form-select-solvex form-select-sm" name="department_id" onchange="this.form.submit()">
                <option value="0">All Departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select-solvex form-select-sm" name="location_id" onchange="this.form.submit()">
                <option value="0">All Locations</option>
                <?php foreach ($locations as $l): ?>
                    <option value="<?= (int)$l['id'] ?>" <?= $locFilter === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Leaderboard Cards -->
    <div class="d-flex flex-column gap-3 mb-5">
        <?php if (empty($leaderboard)): ?>
            <div class="empty-state py-5 text-center card-solvex border-0 shadow-sm">
                <i class="bi bi-emoji-smile text-success fs-1 mb-2"></i>
                <h4 class="h6 fw-bold text-navy">No pending issues!</h4>
                <p class="text-muted small">All public complaints in this scope are resolved.</p>
            </div>
        <?php else: ?>
            <?php foreach ($leaderboard as $idx => $item): ?>
                <div class="card-solvex border-0 shadow-sm p-3 p-md-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-center p-2 rounded-3 bg-danger-subtle text-danger fw-bold" style="min-width: 75px;">
                                <div class="fs-4 fw-extrabold line-height-1"><?= (int)$item['days_open'] ?></div>
                                <div class="text-uppercase" style="font-size:0.65rem;">Days Open</div>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="font-monospace fw-bold text-primary"><?= e($item['reference']) ?></span>
                                    <span class="status-pill <?= e($item['status']) ?>"><?= ucwords(str_replace('_', ' ', e($item['status']))) ?></span>
                                    <span class="priority-tag <?= e($item['priority']) ?>"><?= e($item['priority']) ?></span>
                                </div>
                                <h4 class="h6 fw-bold text-navy mb-1"><?= e($item['title']) ?></h4>
                                <div class="text-muted small" style="font-size:0.75rem;">
                                    <i class="bi <?= e($item['category_icon']) ?> me-1"></i><?= e($item['category_name']) ?> &bull;
                                    <i class="bi bi-geo-alt me-1"></i><?= e($item['location_name'] ?? 'Main Campus') ?> &bull;
                                    Dept: <strong><?= e($item['department_name']) ?></strong> &bull;
                                    <i class="bi bi-people me-1"></i><?= (int)$item['affected_count'] ?> affected
                                </div>
                            </div>
                        </div>
                        <div>
                            <a href="<?= base_url('complaint.php?ref=' . urlencode($item['reference'])) ?>" class="btn-solvex btn-secondary btn-sm px-3">
                                View Timeline <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>

