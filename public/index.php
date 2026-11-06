<?php
/**
 * Solvex Report — Public Landing Page
 * 
 * Civic problem reporting and transparent tracking for campus communities.
 */
define('SOLVEX_ROOT', dirname(__DIR__));

$pageTitle = 'Campus Issue Resolution & Civic Tracking';
$activeNav = 'home';
require_once SOLVEX_ROOT . '/includes/header.php';

// Fetch aggregate live statistics from database (counts only, zero complaint text)
$totalReported = 0;
$totalSolved   = 0;
$avgDaysToFix  = 0;

try {
    $pdo = db();
    $totalReported = (int) $pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
    $totalSolved   = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('resolved', 'closed')")->fetchColumn();
    
    $avgDays = $pdo->query("SELECT ROUND(AVG(DATEDIFF(resolved_at, created_at))) FROM complaints WHERE resolved_at IS NOT NULL")->fetchColumn();
    $avgDaysToFix = ($avgDays !== null && $avgDays !== false) ? max(1, (int)$avgDays) : 3;
} catch (Exception $ex) {
    error_log('Failed to fetch landing stats: ' . $ex->getMessage());
}
?>

<!-- ── Hero Section ────────────────────────────────────────── -->
<section class="hero-section text-white">
    <div class="container hero-content">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-4" style="background: rgba(14, 159, 142, 0.15); border: 1px solid rgba(14, 159, 142, 0.3);">
                    <i class="bi bi-shield-check text-success"></i>
                    <span class="small fw-semibold text-light">Official Campus Accountability Platform</span>
                </div>
                
                <h1 class="display-4 fw-extrabold mb-4">
                    See a problem? <br>
                    <span class="text-gradient">Report it. Track it.</span><br>
                    Get it solved.
                </h1>
                
                <p class="hero-subtitle mb-5">
                    From broken taps and flickering lab lights to Wi-Fi outages and hostel mess issues. Solvex Report gives every campus issue a public, ageing record that holds departments accountable until it is fixed.
                </p>

                <div class="hero-actions">
                    <?php if ($currentUser): ?>
                        <a href="<?= role_home($currentUser['role']) ?>" class="btn-solvex btn-primary">
                            <i class="bi bi-speedometer2"></i> Open <?= ucfirst(e($currentUser['role'])) ?> Portal
                        </a>
                    <?php else: ?>
                        <a href="<?= base_url('register.php') ?>" class="btn-solvex btn-primary">
                            <i class="bi bi-megaphone-fill"></i> Report a Problem
                        </a>
                        <a href="<?= base_url('login.php') ?>" class="btn-solvex btn-secondary">
                            <i class="bi bi-box-arrow-in-right"></i> Sign In to Track
                        </a>
                    <?php endif; ?>
                    <a href="#how-it-works" class="btn-solvex btn-secondary">
                        <i class="bi bi-compass"></i> How It Works
                    </a>
                </div>
            </div>

            <div class="col-lg-5 d-none d-lg-block">
                <div class="position-relative">
                    <img src="<?= asset_url('img/hero.webp') ?>" alt="Solvex Report Campus Operations" class="img-fluid rounded-4 shadow-sm mb-3 border border-light" width="560" height="320" loading="eager">
                    <div class="card-solvex shadow-lg border-0 bg-white text-dark p-3 rounded-4 position-relative">
                        <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary-subtle text-primary fw-bold">SVX-2024-00142</span>
                                <span class="status-pill in_progress">In Progress</span>
                            </div>
                            <span class="days-chip warning">
                                <i class="bi bi-clock-history"></i> Open 2 days
                            </span>
                        </div>
                        
                        <h6 class="fw-bold text-navy mb-1">Water cooler leaking & low pressure</h6>
                        <p class="small text-muted mb-2">Academic Block 2 &bull; 3rd Floor East Wing</p>
                        
                        <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light mb-2">
                            <div class="small text-secondary">
                                <i class="bi bi-building me-1"></i> Dept: <strong>Maintenance & Civil</strong>
                            </div>
                            <span class="priority-tag medium">Medium</span>
                        </div>

                        <div class="timeline mt-2 pt-1" style="font-size: 0.8rem;">
                            <div class="timeline-item active">
                                <div class="timeline-time">Today, 10:15 AM</div>
                                <div class="timeline-content fw-semibold">Plumber dispatched with replacement copper valve</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Live Aggregate Statistics ───────────────────────────── -->
<section class="stats-section">
    <div class="container">
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon primary mx-auto">
                    <i class="bi bi-clipboard2-data-fill"></i>
                </div>
                <div class="stat-value">
                    <span class="counter-value" data-counter="<?= $totalReported ?>"><?= number_format($totalReported) ?></span>
                </div>
                <div class="stat-label">Total Problems Reported</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success mx-auto">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div class="stat-value">
                    <span class="counter-value" data-counter="<?= $totalSolved ?>"><?= number_format($totalSolved) ?></span>
                </div>
                <div class="stat-label">Verified &amp; Resolved</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning mx-auto">
                    <i class="bi bi-stopwatch-fill"></i>
                </div>
                <div class="stat-value">
                    <span class="counter-value" data-counter="<?= $avgDaysToFix ?>"><?= $avgDaysToFix ?></span> <span class="fs-4 text-muted">Days</span>
                </div>
                <div class="stat-label">Average Resolution Pace</div>
            </div>
        </div>
    </div>
</section>

<!-- ── How It Works ────────────────────────────────────────── -->
<section class="how-it-works" id="how-it-works">
    <div class="container">
        <div class="section-heading">
            <span class="section-badge">Simple 3-Step Flow</span>
            <h2>How Solvex Report Solves Campus Problems</h2>
            <p>From instant submission to closure verification, every step is open, transparent, and prompt.</p>
        </div>

        <div class="steps-grid">
            <!-- Step 1 -->
            <div class="step-card">
                <div class="step-number">1</div>
                <div class="step-icon mb-3">
                    <img src="<?= asset_url('img/how-report.webp') ?>" alt="Spot and report issue" width="72" height="72" loading="lazy">
                </div>
                <h3>Spot &amp; Report</h3>
                <p>
                    Pick a campus department and category, specify the exact building, floor or room, take photo proof, and submit in seconds.
                </p>
            </div>

            <!-- Step 2 -->
            <div class="step-card">
                <div class="step-number">2</div>
                <div class="step-icon mb-3">
                    <img src="<?= asset_url('img/how-track.webp') ?>" alt="Public ageing and triage" width="72" height="72" loading="lazy">
                </div>
                <h3>Public Ageing &amp; Triage</h3>
                <p>
                    Every complaint receives a public reference number and an ageing badge. Tickets are routed directly to the department queue with clear SLA countdowns.
                </p>
            </div>

            <!-- Step 3 -->
            <div class="step-card">
                <div class="step-number">3</div>
                <div class="step-icon mb-3">
                    <img src="<?= asset_url('img/how-resolved.webp') ?>" alt="Resolve and verify fix" width="72" height="72" loading="lazy">
                </div>
                <h3>Resolve &amp; Verify</h3>
                <p>
                    Maintenance staff marks work completed with fix details. The reporting student verifies the resolution before closing, creating an indelible record.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ── Why Solvex Section ──────────────────────────────────── -->
<section class="why-section" id="why-solvex">
    <div class="container">
        <div class="section-heading">
            <span class="section-badge">Built For Campus Trust</span>
            <h2>Why Solvex Report is Different</h2>
            <p>A civic platform designed for accountability, student safety, and institutional pride.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon bg-primary-subtle text-primary">
                    <i class="bi bi-stopwatch"></i>
                </div>
                <div>
                    <h4 class="fw-bold">Public Ageing Counter</h4>
                    <p>
                        "Open for 12 days" chips ensure urgent issues don't vanish in private email threads. Campus administrators see real delays before they escalate.
                    </p>
                </div>
            </div>

            <div class="feature-card">
                <div class="feature-icon bg-warning-subtle text-warning">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div>
                    <h4 class="fw-bold">Private &amp; Sensitive Protection</h4>
                    <p>
                        Harassment, security, and hostel sensitive complaints are automatically forced private, restricted exclusively to authorized warden and security officials.
                    </p>
                </div>
            </div>

            <div class="feature-card">
                <div class="feature-icon bg-info-subtle text-info">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <h4 class="fw-bold">Immutable Audit Timeline</h4>
                    <p>
                        Every status transition, assignment change, and hold reasoning is stamped into an append-only event log. Zero silent deletions or untracked changes.
                    </p>
                </div>
            </div>

            <div class="feature-card">
                <div class="feature-icon bg-success-subtle text-success">
                    <i class="bi bi-phone"></i>
                </div>
                <div>
                    <h4 class="fw-bold">Mobile-First Campus Usability</h4>
                    <p>
                        Optimized for students walking between hostel blocks and lecture halls. Fast photo capture, precise location cascades, and responsive on all screens.
                    </p>
                </div>
            </div>
        </div>

        <!-- Call to Action Banner -->
        <div class="mt-5 p-5 rounded-4 text-center text-white" style="background: linear-gradient(135deg, var(--clr-navy) 0%, var(--clr-primary-700) 100%);">
            <h3 class="fw-bold mb-3">Ready to improve campus facilities?</h3>
            <p class="lead text-light mb-4 mx-auto" style="max-width: 600px;">
                Join students and faculty across campus working together for faster repairs, safer corridors, and accountable administration.
            </p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="<?= base_url('register.php') ?>" class="btn-solvex btn-accent btn-lg">
                    <i class="bi bi-person-plus-fill"></i> Register as Student
                </a>
                <a href="<?= base_url('login.php') ?>" class="btn-solvex btn-secondary btn-lg text-white border-white">
                    <i class="bi bi-box-arrow-in-right"></i> Staff / Admin Portal
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
