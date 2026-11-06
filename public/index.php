<?php
/**
 * Solvex Report — Campus Incident & Maintenance Ledger
 * 
 * Public entry page: overview, live metrics, operational procedure,
 * and recent public records ledger.
 */
define('SOLVEX_ROOT', dirname(__DIR__));

$pageTitle = 'Campus Incident & Maintenance Ledger';
$activeNav = 'home';
require_once SOLVEX_ROOT . '/includes/header.php';

// Fetch aggregate live statistics from database
$totalReported = 0;
$totalInProgress = 0;
$totalSolved   = 0;
$avgDaysToFix  = 0;
$recentComplaints = [];

try {
    $pdo = db();
    $totalReported = (int) $pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
    $totalInProgress = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('assigned', 'in_progress', 'on_hold')")->fetchColumn();
    $totalSolved   = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('resolved', 'closed')")->fetchColumn();
    
    $avgDays = $pdo->query("SELECT ROUND(AVG(DATEDIFF(resolved_at, created_at))) FROM complaints WHERE resolved_at IS NOT NULL")->fetchColumn();
    $avgDaysToFix = ($avgDays !== null && $avgDays !== false) ? max(1, (int)$avgDays) : 2;

    // Fetch latest 6 public complaints for the ledger
    $stmt = $pdo->query("
        SELECT c.id, c.reference, c.title, c.status, c.priority, c.created_at,
               cat.name AS category_name,
               d.name AS department_name
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        JOIN departments d ON c.department_id = d.id
        WHERE c.visibility = 'public' AND cat.sensitive = 0
        ORDER BY c.id DESC
        LIMIT 6
    ");
    $recentComplaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    error_log('Failed to fetch landing stats: ' . $ex->getMessage());
}
?>

<!-- ── Editorial Hero ──────────────────────────────────────── -->
<section class="hero-section py-5">
    <div class="container text-center">
        <div class="mx-auto mb-4" style="max-width: 600px;">
            <span class="badge bg-primary-subtle text-primary border mb-2 text-uppercase">Hostel Complaint Portal</span>
            <h1 class="display-6 fw-bold mb-2 text-dark">
                Hostel Maintenance &amp; Complaint System
            </h1>
            <p class="text-secondary small mb-4">
                Fast, transparent problem reporting and repair tracking for hostel residents.
            </p>

            <?php if ($currentUser): ?>
                <a href="<?= role_home($currentUser['role']) ?>" class="btn-solvex btn-primary py-2 px-4 fs-6">
                    <i class="bi bi-speedometer2 me-1"></i> Open <?= ucfirst(e($currentUser['role'])) ?> Dashboard
                </a>
            <?php else: ?>
                <div class="d-flex justify-content-center gap-2">
                    <a href="<?= base_url('login.php') ?>" class="btn-solvex btn-primary px-4 py-2">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Student Login
                    </a>
                    <a href="<?= base_url('register.php') ?>" class="btn-solvex btn-secondary px-4 py-2">
                        <i class="bi bi-person-plus-fill me-1"></i> Register Account
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ── Live Metrics Ledger ─────────────────────────────────── -->
<section class="stats-section">
    <div class="container">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <?= stat_block('Total Logged', number_format($totalReported), 'clipboard', 'primary') ?>
            </div>
            <div class="col-6 col-md-3">
                <?= stat_block('Under Triage', number_format($totalInProgress), 'clock', 'warning') ?>
            </div>
            <div class="col-6 col-md-3">
                <?= stat_block('Verified & Fixed', number_format($totalSolved), 'check-circle', 'success') ?>
            </div>
            <div class="col-6 col-md-3">
                <?= stat_block('Avg. Resolution Pace', $avgDaysToFix . ' Days', 'activity', 'info') ?>
            </div>
        </div>
    </div>
</section>

<!-- ── Recent Public Incidents ─────────────────────────────── -->
<section class="py-5">
    <div class="container">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1 text-dark fw-bold">Recent Hostel Maintenance Log</h2>
                <p class="small text-secondary mb-0">Latest complaints filed and resolved across hostel blocks.</p>
            </div>
            <a href="<?= base_url('login.php') ?>" class="btn-solvex btn-ghost btn-sm">
                Student Sign In <?= icon('arrow-right', 14) ?>
            </a>
        </div>

        <?php if (!empty($recentComplaints)): ?>
            <div class="card-solvex">
                <div class="table-responsive">
                    <table class="table-solvex mb-0">
                        <thead>
                            <tr>
                                <th style="width: 140px;">Reference</th>
                                <th>Incident Title</th>
                                <th>Category</th>
                                <th>Department</th>
                                <th style="width: 110px;">Status</th>
                                <th style="width: 130px;">Reported</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentComplaints as $c): ?>
                                <tr>
                                    <td>
                                        <a href="<?= base_url('complaint.php?ref=' . urlencode($c['reference'])) ?>" class="ref-id text-decoration-none">
                                            <?= e($c['reference']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?= base_url('complaint.php?ref=' . urlencode($c['reference'])) ?>" class="fw-semibold text-decoration-none">
                                            <?= e(truncate($c['title'], 60)) ?>
                                        </a>
                                    </td>
                                    <td class="text-secondary small">
                                        <?= e($c['category_name']) ?>
                                    </td>
                                    <td class="text-secondary small">
                                        <?= e($c['department_name']) ?>
                                    </td>
                                    <td>
                                        <?= status_tag($c['status']) ?>
                                    </td>
                                    <td class="text-secondary small">
                                        <?= days_since($c['created_at']) === 0 ? 'Today' : days_since($c['created_at']) . 'd ago' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="card-solvex p-4">
                <?= empty_state('No Open Public Incidents', 'There are currently zero open public complaints on record.', 'check-circle') ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── Operational Process ─────────────────────────────────── -->
<section class="py-4 border-top" style="background: var(--clr-sunken);">
    <div class="container py-3">
        <div class="mb-4">
            <h2 class="h5 mb-1">Operational Lifecycle</h2>
            <p class="small text-secondary mb-0">Standard campus incident intake, dispatch, and confirmation procedure.</p>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="card-solvex p-3 h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="ref-id">01</span>
                        <h3 class="h6 mb-0 fw-bold">Submit Details</h3>
                    </div>
                    <p class="small text-secondary mb-0">
                        Select the facility category, specify campus location (building, floor, room), and attach optional photo evidence.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card-solvex p-3 h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="ref-id">02</span>
                        <h3 class="h6 mb-0 fw-bold">Department Triage</h3>
                    </div>
                    <p class="small text-secondary mb-0">
                        The ticket is assigned to the responsible department queue (Civil, Electrical, IT, etc.) under defined SLA target days.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card-solvex p-3 h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="ref-id">03</span>
                        <h3 class="h6 mb-0 fw-bold">Student Sign-Off</h3>
                    </div>
                    <p class="small text-secondary mb-0">
                        Once technicians complete repairs, the reporting student confirms resolution before the incident is closed.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
