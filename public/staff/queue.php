<?php
/**
 * Solvex Report — Staff Queue (Phase 1 Placeholder)
 */
define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/header.php';
require_role('staff', 'admin');

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
                            <h2 class="h3 fw-bold mb-1">Staff Queue — <?= e($user['full_name']) ?></h2>
                            <div class="text-muted small">
                                <span class="badge bg-indigo-subtle text-indigo border me-2">Staff Portal</span>
                                Staff Code: <strong><?= e($user['staff_code']) ?></strong> &bull; <?= e($user['email']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning border-0 rounded-3 mb-4 d-flex align-items-start gap-2">
                        <i class="bi bi-shield-lock-fill fs-5 mt-1"></i>
                        <div>
                            <strong>Protected Area:</strong> Only authenticated staff and administrators can access this view. Full department queue triage, assignment, and status management arrive in <strong>Phase 3</strong>.
                        </div>
                    </div>

                    <div class="placeholder-page py-4">
                        <div class="placeholder-icon">
                            <i class="bi bi-inbox-fill text-indigo"></i>
                        </div>
                        <h3 class="h4 fw-bold">Department Resolution Queue</h3>
                        <p class="text-muted mb-4">
                            Zendesk/Jira-inspired ticket table with SLA timer indicators, status change actions, and internal notes.
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
