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
    <div class="container">
        <div class="text-center mb-5" style="max-width: 640px; margin-left: auto; margin-right: auto;">
            <span class="badge bg-primary-subtle text-primary border mb-2 text-uppercase">Hostel Management System</span>
            <h1 class="display-6 fw-bold mb-2 text-dark">
                Hostel Maintenance &amp; Complaint System
            </h1>
            <p class="text-secondary small mb-0">
                Residential complaint reporting, warden triage, and maintenance tracking.
            </p>
        </div>

        <?php if ($currentUser): ?>
            <div class="text-center">
                <a href="<?= role_home($currentUser['role']) ?>" class="btn-solvex btn-primary py-2 px-4 fs-6">
                    <i class="bi bi-speedometer2 me-1"></i> Open <?= ucfirst(e($currentUser['role'])) ?> Dashboard
                </a>
            </div>
        <?php else: ?>
            <!-- Dual Portal Entry Cards -->
            <div class="row g-4 justify-content-center">
                <!-- 1. Student Portal Card -->
                <div class="col-md-5">
                    <div class="card shadow-sm border h-100 p-4" style="border-top: 4px solid var(--clr-brand-accent) !important; border-radius: 6px;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 46px; height: 46px; background: var(--clr-brand-accent); color: #fff;">
                                <i class="bi bi-mortarboard fs-4"></i>
                            </div>
                            <div>
                                <h2 class="h5 fw-bold text-dark mb-0">Student Portal</h2>
                                <span class="text-secondary small">Hostel Residents</span>
                            </div>
                        </div>
                        <p class="text-secondary small mb-4">
                            Submit maintenance complaints for your hostel room, track status, and verify completed fixes.
                        </p>
                        <div class="d-flex gap-2 mt-auto">
                            <a href="<?= base_url('login.php?role=student') ?>" class="btn-solvex btn-primary flex-fill text-center">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Student Login
                            </a>
                            <a href="<?= base_url('register.php') ?>" class="btn-solvex btn-secondary flex-fill text-center">
                                <i class="bi bi-person-plus-fill me-1"></i> Register
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 2. Faculty / Staff Portal Card -->
                <div class="col-md-5">
                    <div class="card shadow-sm border h-100 p-4" style="border-top: 4px solid #14213D !important; border-radius: 6px;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 46px; height: 46px; background: #14213D; color: #fff;">
                                <i class="bi bi-shield-check fs-4"></i>
                            </div>
                            <div>
                                <h2 class="h5 fw-bold text-dark mb-0">Faculty &amp; Staff Portal</h2>
                                <span class="text-secondary small">Warden &amp; Maintenance</span>
                            </div>
                        </div>
                        <p class="text-secondary small mb-4">
                            Warden review, complaint assignment, priority triage, and technician work execution.
                        </p>
                        <div class="mt-auto">
                            <a href="<?= base_url('login.php?role=staff') ?>" class="btn btn-dark w-100 py-2 fw-semibold text-center">
                                <i class="bi bi-shield-lock-fill me-1"></i> Staff / Warden Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3 pb-2 border-bottom">
            <div>
                <h2 class="h4 mb-1">Recent Public Records</h2>
                <p class="small text-secondary mb-0">Latest campus maintenance complaints open for public verification.</p>
            </div>
            <a href="<?= base_url('feed.php') ?>" class="btn-solvex btn-secondary btn-sm">
                View All Public Incidents <?= icon('arrow-right', 14) ?>
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
