<?php
/**
 * 403 Forbidden Error Page
 */
if (!defined('SOLVEX_ROOT')) {
    define('SOLVEX_ROOT', dirname(__DIR__, 2));
}

require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';

http_response_code(403);
$pageTitle = '403 Access Denied';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="error-page">
    <div class="container text-center">
        <div class="error-code text-danger">403</div>
        <h2 class="display-6 fw-bold">Access Restricted</h2>
        <p class="lead text-muted mx-auto" style="max-width: 500px;">
            You do not have administrative or staff permission to access this protected area.
        </p>
        <div class="d-flex justify-content-center gap-3">
            <a href="<?= base_url() ?>" class="btn-solvex btn-secondary">
                <i class="bi bi-house-door me-1"></i> Return Home
            </a>
            <?php if (is_logged_in()): ?>
                <a href="<?= role_home($_SESSION['user_role'] ?? 'student') ?>" class="btn-solvex btn-primary">
                    <i class="bi bi-speedometer2 me-1"></i> My Dashboard
                </a>
            <?php else: ?>
                <a href="<?= base_url('login.php') ?>" class="btn-solvex btn-primary">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Log In
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
