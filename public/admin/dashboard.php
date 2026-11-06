<?php
/**
 * Solvex Report — Admin Dashboard
 * 
 * Stripe-style metrics, live Chart.js charts, and operational triage lists:
 * - 6 Stat cards (Total open, unassigned >24h, overdue, critical open, resolved this month, avg days to resolve)
 * - Category bar chart & 30-day intake/resolution line chart
 * - 6 Critical operational queues: Overdue, Unassigned >24h, Critical open, Auto-closed, Rejections, Appeals
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';
require_once SOLVEX_ROOT . '/includes/maintenance.php';

init_session();
require_role('admin');

run_maintenance();

$user = current_user();
$pdo  = db();

// 1. Metric Calculations
$statsStmt = $pdo->query("
    SELECT
        COUNT(CASE WHEN c.status NOT IN ('closed', 'rejected', 'duplicate') THEN 1 END) AS count_open,
        COUNT(CASE WHEN c.assigned_to IS NULL AND c.status IN ('submitted', 'reopened') AND c.created_at <= NOW() - INTERVAL 24 HOUR THEN 1 END) AS count_unassigned_24h,
        COUNT(CASE WHEN DATEDIFF(NOW(), c.created_at) > cat.target_days AND c.status NOT IN ('closed', 'rejected', 'duplicate') THEN 1 END) AS count_overdue,
        COUNT(CASE WHEN c.priority = 'critical' AND c.status NOT IN ('closed', 'rejected', 'duplicate') THEN 1 END) AS count_critical_open,
        COUNT(CASE WHEN c.status = 'closed' AND c.closed_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN 1 END) AS count_resolved_month,
        ROUND(AVG(CASE WHEN c.status = 'closed' AND c.closed_at IS NOT NULL THEN DATEDIFF(c.closed_at, c.created_at) END), 1) AS avg_days_to_resolve
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
");
$stats = $statsStmt->fetch() ?: [];

// 2. Chart.js Data Generation
// A. Category breakdown
$catStmt = $pdo->query("
    SELECT cat.name, COUNT(c.id) AS total
    FROM categories cat
    LEFT JOIN complaints c ON c.category_id = cat.id
    GROUP BY cat.id, cat.name
    ORDER BY total DESC
");
$catRows = $catStmt->fetchAll();
$categoryLabels = array_column($catRows, 'name');
$categoryCounts = array_map('intval', array_column($catRows, 'total'));

// B. 30-Day Intake vs Resolved
$trendLabels = [];
$createdTrends = [];
$resolvedTrends = [];

$daysMap = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $daysMap[$d] = ['created' => 0, 'resolved' => 0, 'label' => date('d M', strtotime($d))];
}

$cTrendsStmt = $pdo->query("
    SELECT DATE(created_at) AS dt, COUNT(*) AS cnt
    FROM complaints
    WHERE created_at >= CURDATE() - INTERVAL 29 DAY
    GROUP BY DATE(created_at)
");
foreach ($cTrendsStmt->fetchAll() as $row) {
    if (isset($daysMap[$row['dt']])) {
        $daysMap[$row['dt']]['created'] = (int)$row['cnt'];
    }
}

$rTrendsStmt = $pdo->query("
    SELECT DATE(closed_at) AS dt, COUNT(*) AS cnt
    FROM complaints
    WHERE closed_at IS NOT NULL AND closed_at >= CURDATE() - INTERVAL 29 DAY
    GROUP BY DATE(closed_at)
");
foreach ($rTrendsStmt->fetchAll() as $row) {
    if (isset($daysMap[$row['dt']])) {
        $daysMap[$row['dt']]['resolved'] = (int)$row['cnt'];
    }
}

foreach ($daysMap as $dItem) {
    $trendLabels[]   = $dItem['label'];
    $createdTrends[]  = $dItem['created'];
    $resolvedTrends[] = $dItem['resolved'];
}

// 3. Operational Critical Lists
// Overdue Top 10
$overdueList = $pdo->query("
    SELECT c.id, c.reference, c.title, c.priority, c.created_at, cat.name AS category_name, cat.target_days
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    WHERE DATEDIFF(NOW(), c.created_at) > cat.target_days AND c.status NOT IN ('closed', 'rejected', 'duplicate')
    ORDER BY DATEDIFF(NOW(), c.created_at) DESC
    LIMIT 10
")->fetchAll();

// Unassigned > 24 Hours
$unassignedList = $pdo->query("
    SELECT c.id, c.reference, c.title, c.created_at, dept.name AS department_name
    FROM complaints c
    JOIN departments dept ON dept.id = c.department_id
    WHERE c.assigned_to IS NULL AND c.status IN ('submitted', 'reopened') AND c.created_at <= NOW() - INTERVAL 24 HOUR
    ORDER BY c.created_at ASC
    LIMIT 10
")->fetchAll();

// Critical Open
$criticalList = $pdo->query("
    SELECT c.id, c.reference, c.title, c.status, c.created_at, dept.name AS department_name
    FROM complaints c
    JOIN departments dept ON dept.id = c.department_id
    WHERE c.priority = 'critical' AND c.status NOT IN ('closed', 'rejected', 'duplicate')
    ORDER BY c.created_at ASC
    LIMIT 10
")->fetchAll();

// Auto-closed
$autoClosedList = $pdo->query("
    SELECT c.id, c.reference, c.title, c.closed_at, dept.name AS department_name
    FROM complaints c
    JOIN departments dept ON dept.id = c.department_id
    WHERE c.status = 'closed' AND c.closed_by = 'auto'
    ORDER BY c.closed_at DESC
    LIMIT 10
")->fetchAll();

// Rejections with Reasons
$rejectionsList = $pdo->query("
    SELECT c.id, c.reference, c.title, ce.reason, ce.created_at, u.full_name AS actor_name
    FROM complaints c
    JOIN complaint_events ce ON ce.complaint_id = c.id AND ce.type = 'status_change' AND ce.new_value = 'rejected'
    LEFT JOIN users u ON u.id = ce.actor_id
    ORDER BY ce.created_at DESC
    LIMIT 10
")->fetchAll();

// Appeals Pending (reopened from rejected)
$appealsList = $pdo->query("
    SELECT c.id, c.reference, c.title, c.updated_at, dept.name AS department_name
    FROM complaints c
    JOIN departments dept ON dept.id = c.department_id
    WHERE c.status = 'reopened' AND EXISTS (
        SELECT 1 FROM complaint_events ce WHERE ce.complaint_id = c.id AND ce.type = 'status_change' AND ce.new_value = 'rejected'
    )
    ORDER BY c.updated_at DESC
    LIMIT 10
")->fetchAll();

$pageTitle = 'Admin Overview';
$activeNav = 'dashboard';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container-fluid px-lg-4 py-4">

    <!-- Header Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-navy mb-1"><i class="bi bi-speedometer2 text-danger me-2"></i>Administration Control Center</h1>
            <p class="text-muted small mb-0">Real-time system health, campus SLA resolution oversight, and audit logs.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('admin/complaints.php') ?>" class="btn-solvex btn-secondary btn-sm">
                <i class="bi bi-list-task me-1"></i> All Complaints
            </a>
            <a href="<?= base_url('admin/reports.php') ?>" class="btn-solvex btn-primary btn-sm">
                <i class="bi bi-download me-1"></i> Export Reports
            </a>
        </div>
    </div>

    <!-- 6 Stripe-Style Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-2">
            <div class="stat-card border-0 shadow-sm p-3">
                <div class="stat-icon primary mb-2"><i class="bi bi-inbox-fill"></i></div>
                <div class="stat-value fs-4"><?= (int)($stats['count_open'] ?? 0) ?></div>
                <div class="stat-label small">Open Tickets</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="stat-card border-0 shadow-sm p-3">
                <div class="stat-icon warning mb-2"><i class="bi bi-person-x-fill"></i></div>
                <div class="stat-value fs-4 text-warning-dark"><?= (int)($stats['count_unassigned_24h'] ?? 0) ?></div>
                <div class="stat-label small">Unassigned >24h</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="stat-card border-0 shadow-sm p-3">
                <div class="stat-icon danger mb-2"><i class="bi bi-alarm-fill"></i></div>
                <div class="stat-value fs-4 text-danger"><?= (int)($stats['count_overdue'] ?? 0) ?></div>
                <div class="stat-label small">Overdue SLA</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="stat-card border-0 shadow-sm p-3">
                <div class="stat-icon danger mb-2"><i class="bi bi-exclamation-diamond-fill"></i></div>
                <div class="stat-value fs-4 text-danger"><?= (int)($stats['count_critical_open'] ?? 0) ?></div>
                <div class="stat-label small">Critical Open</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="stat-card border-0 shadow-sm p-3">
                <div class="stat-icon success mb-2"><i class="bi bi-check2-circle"></i></div>
                <div class="stat-value fs-4 text-success"><?= (int)($stats['count_resolved_month'] ?? 0) ?></div>
                <div class="stat-label small">Closed This Month</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="stat-card border-0 shadow-sm p-3">
                <div class="stat-icon info mb-2"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-value fs-4 text-primary"><?= (float)($stats['avg_days_to_resolve'] ?? 0) ?>d</div>
                <div class="stat-label small">Avg Resolution</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <!-- 30-Day Trend Chart -->
        <div class="col-lg-7">
            <div class="card-solvex border-0 shadow-sm p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="h6 fw-bold text-navy mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>30-Day Intake vs Resolution Trend</h5>
                    <span class="badge bg-light text-muted border">Daily volume</span>
                </div>
                <div style="height: 250px;">
                    <canvas id="intakeResolutionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Categories Distribution Chart -->
        <div class="col-lg-5">
            <div class="card-solvex border-0 shadow-sm p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="h6 fw-bold text-navy mb-0"><i class="bi bi-pie-chart me-2 text-primary"></i>Complaints per Category</h5>
                    <span class="badge bg-light text-muted border">Total lifetime</span>
                </div>
                <div style="height: 250px;">
                    <canvas id="categoryDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Operational Monitoring Grid (6 Tables) -->
    <div class="row g-4">

        <!-- 1. Overdue SLA -->
        <div class="col-md-6 col-lg-4">
            <div class="card-solvex border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i> Overdue Tickets (<?= count($overdueList) ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush small">
                        <?php if (empty($overdueList)): ?>
                            <div class="p-3 text-muted">No overdue tickets.</div>
                        <?php else: ?>
                            <?php foreach ($overdueList as $o): ?>
                                <a href="<?= base_url('staff/complaint.php?id=' . $o['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2 px-3">
                                    <div class="text-truncate me-2">
                                        <span class="font-monospace text-primary fw-bold"><?= e($o['reference']) ?></span>
                                        <div class="text-truncate text-navy"><?= e($o['title']) ?></div>
                                    </div>
                                    <span class="badge bg-danger"><?= days_since($o['created_at']) ?>d / <?= $o['target_days'] ?>d</span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Unassigned > 24 Hours -->
        <div class="col-md-6 col-lg-4">
            <div class="card-solvex border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-warning-dark mb-0"><i class="bi bi-person-x-fill me-1"></i> Unassigned >24h (<?= count($unassignedList) ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush small">
                        <?php if (empty($unassignedList)): ?>
                            <div class="p-3 text-muted">No unassigned tickets exceeding 24 hours.</div>
                        <?php else: ?>
                            <?php foreach ($unassignedList as $u): ?>
                                <a href="<?= base_url('staff/complaint.php?id=' . $u['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2 px-3">
                                    <div class="text-truncate me-2">
                                        <span class="font-monospace text-primary fw-bold"><?= e($u['reference']) ?></span>
                                        <div class="text-truncate text-navy"><?= e($u['title']) ?></div>
                                        <div class="text-muted" style="font-size:0.7rem;"><?= e($u['department_name']) ?></div>
                                    </div>
                                    <span class="badge bg-warning text-dark"><?= days_since($u['created_at']) ?>d ago</span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Critical Open -->
        <div class="col-md-6 col-lg-4">
            <div class="card-solvex border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-danger mb-0"><i class="bi bi-fire me-1"></i> Critical Open (<?= count($criticalList) ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush small">
                        <?php if (empty($criticalList)): ?>
                            <div class="p-3 text-muted">No open critical complaints.</div>
                        <?php else: ?>
                            <?php foreach ($criticalList as $cr): ?>
                                <a href="<?= base_url('staff/complaint.php?id=' . $cr['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2 px-3">
                                    <div class="text-truncate me-2">
                                        <span class="font-monospace text-primary fw-bold"><?= e($cr['reference']) ?></span>
                                        <div class="text-truncate text-navy"><?= e($cr['title']) ?></div>
                                    </div>
                                    <span class="badge bg-danger text-uppercase"><?= e($cr['status']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Auto-Closed Items -->
        <div class="col-md-6 col-lg-4">
            <div class="card-solvex border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-secondary mb-0"><i class="bi bi-robot me-1"></i> Auto-Closed (No Student Reply)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush small">
                        <?php if (empty($autoClosedList)): ?>
                            <div class="p-3 text-muted">No auto-closed tickets.</div>
                        <?php else: ?>
                            <?php foreach ($autoClosedList as $ac): ?>
                                <a href="<?= base_url('staff/complaint.php?id=' . $ac['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2 px-3">
                                    <div class="text-truncate me-2">
                                        <span class="font-monospace text-primary fw-bold"><?= e($ac['reference']) ?></span>
                                        <div class="text-truncate text-navy"><?= e($ac['title']) ?></div>
                                    </div>
                                    <span class="badge bg-light text-secondary border"><?= format_date($ac['closed_at'], 'd M') ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Recent Rejections -->
        <div class="col-md-6 col-lg-4">
            <div class="card-solvex border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-navy mb-0"><i class="bi bi-x-octagon me-1 text-danger"></i> Recent Rejections</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush small">
                        <?php if (empty($rejectionsList)): ?>
                            <div class="p-3 text-muted">No rejected complaints recorded.</div>
                        <?php else: ?>
                            <?php foreach ($rejectionsList as $rj): ?>
                                <a href="<?= base_url('staff/complaint.php?id=' . $rj['id']) ?>" class="list-group-item list-group-item-action p-2 px-3">
                                    <div class="d-flex justify-content-between">
                                        <span class="font-monospace text-primary fw-bold"><?= e($rj['reference']) ?></span>
                                        <span class="text-muted" style="font-size:0.7rem;"><?= format_date($rj['created_at'], 'd M') ?></span>
                                    </div>
                                    <div class="text-truncate text-navy fw-semibold"><?= e($rj['title']) ?></div>
                                    <div class="text-muted text-truncate fst-italic" style="font-size:0.75rem;">
                                        By <?= e($rj['actor_name'] ?? 'Staff') ?>: &ldquo;<?= e($rj['reason']) ?>&rdquo;
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Appeals Pending -->
        <div class="col-md-6 col-lg-4">
            <div class="card-solvex border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-primary mb-0"><i class="bi bi-arrow-repeat me-1"></i> Appeals Pending Review</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush small">
                        <?php if (empty($appealsList)): ?>
                            <div class="p-3 text-muted">No re-opened appeals pending.</div>
                        <?php else: ?>
                            <?php foreach ($appealsList as $ap): ?>
                                <a href="<?= base_url('staff/complaint.php?id=' . $ap['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2 px-3">
                                    <div class="text-truncate me-2">
                                        <span class="font-monospace text-primary fw-bold"><?= e($ap['reference']) ?></span>
                                        <div class="text-truncate text-navy"><?= e($ap['title']) ?></div>
                                        <div class="text-muted" style="font-size:0.7rem;"><?= e($ap['department_name']) ?></div>
                                    </div>
                                    <span class="badge bg-primary">Appealed</span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Chart.js Pinned CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Line Chart: 30-Day Trends
    const ctxTrend = document.getElementById('intakeResolutionChart');
    if (ctxTrend) {
        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [
                    {
                        label: 'Created',
                        data: <?= json_encode($createdTrends) ?>,
                        borderColor: '#0E9F8E',
                        backgroundColor: 'rgba(14, 159, 142, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Resolved',
                        data: <?= json_encode($resolvedTrends) ?>,
                        borderColor: '#0B2545',
                        backgroundColor: 'transparent',
                        borderDash: [5, 5],
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }

    // 2. Bar Chart: Category Distribution
    const ctxCat = document.getElementById('categoryDistributionChart');
    if (ctxCat) {
        new Chart(ctxCat, {
            type: 'bar',
            data: {
                labels: <?= json_encode($categoryLabels) ?>,
                datasets: [{
                    label: 'Total Tickets',
                    data: <?= json_encode($categoryCounts) ?>,
                    backgroundColor: '#0E9F8E',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 10 } } },
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }
});
</script>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
