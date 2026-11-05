<?php
/**
 * Solvex Report — Admin Dashboard (Phase 1 Placeholder)
 */
define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/header.php';
require_role('admin');

$user = current_user();
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card-solvex shadow-sm border-0 mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="avatar-initials lg" style="background-color: <?= avatar_color($user['full_name']) ?>;">
                            <?= e(initials($user['full_name'])) ?>
                        </span>
                        <div>
                            <h2 class="h3 fw-bold mb-1">Administration Control Center</h2>
                            <div class="text-muted small">
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-2">Super Admin</span>
                                <?= e($user['full_name']) ?> &bull; <?= e($user['email']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-danger border-0 rounded-3 mb-4 d-flex align-items-start gap-2">
                        <i class="bi bi-shield-shaded fs-5 mt-1"></i>
                        <div>
                            <strong>Restricted Super-Admin Realm:</strong> Strict role checks protect this dashboard. Student and staff roles are denied access (HTTP 403). Full system analytics, department configuration, and audit trails deploy in <strong>Phase 3 &amp; 4</strong>.
                        </div>
                    </div>

                    <div class="placeholder-page py-4">
                        <div class="placeholder-icon">
                            <i class="bi bi-speedometer2 text-danger"></i>
                        </div>
                        <h3 class="h4 fw-bold">Admin Management Center</h3>
                        <p class="text-muted mb-4">
                            Stripe-style metrics, user permission management, category targets, and campus-wide resolution analytics.
                        </p>
                        <div class="d-flex gap-3 justify-content-center">
                            <a href="<?= base_url() ?>" class="btn-solvex btn-secondary">
                                <i class="bi bi-house-door me-1"></i> Public Landing
                            </a>
                            <a href="<?= base_url('logout.php') ?>" class="btn-solvex btn-ghost text-danger">
                                <i class="bi bi-box-arrow-right me-1"></i> Log Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
