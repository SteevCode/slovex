<?php
/**
 * Solvex Report — Email Verification
 * 
 * Verifies the single-use email verification token,
 * activates the student account, and provides a direct path to log in.
 */
define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';

init_session();

$token = get('token');
$verified = false;
$message = '';

if (empty($token)) {
    $message = 'No verification token provided. Please check your verification email link.';
} else {
    $result = verify_email_token($token);
    $verified = $result['success'];
    $message = $result['message'];
}

$pageTitle = $verified ? 'Account Activated' : 'Verification Issue';
$bodyClass = 'bg-light';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="auth-wrapper py-5">
    <div class="auth-card text-center" style="max-width: 480px;">
        <div class="auth-logo">
            <a href="<?= base_url() ?>" aria-label="Solvex Report Home">
                <img src="<?= asset_url('img/logo-with-text.png') ?>" alt="Solvex Report" height="44">
            </a>
        </div>

        <?php if ($verified): ?>
            <div class="mb-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success mb-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-patch-check-fill fs-1"></i>
                </div>
                <h1 class="auth-title h3 fw-bold">Email Verified!</h1>
                <p class="auth-subtitle mb-0">Your Solvex Report student account is now active and ready.</p>
            </div>

            <div class="alert-solvex success text-start mb-4" role="alert">
                <i class="bi bi-check2-circle fs-5"></i>
                <div><?= e($message) ?></div>
            </div>

            <a href="<?= base_url('login.php') ?>" class="btn-solvex btn-primary w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Your Account
            </a>
        <?php else: ?>
            <div class="mb-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger mb-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-shield-x fs-1"></i>
                </div>
                <h1 class="auth-title h3 fw-bold">Verification Failed</h1>
                <p class="auth-subtitle mb-0">We could not activate your student account.</p>
            </div>

            <div class="alert-solvex danger text-start mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div><?= e($message) ?></div>
            </div>

            <div class="d-flex flex-column gap-2">
                <a href="<?= base_url('register.php') ?>" class="btn-solvex btn-primary w-100 py-2">
                    <i class="bi bi-arrow-repeat me-1"></i> Try Registering Again
                </a>
                <a href="<?= base_url('login.php') ?>" class="btn-solvex btn-secondary w-100 py-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Go to Sign In
                </a>
            </div>
        <?php endif; ?>

        <div class="auth-footer mt-4">
            <small class="text-muted">
                Need help? Contact the campus helpdesk at <a href="mailto:helpdesk@solvexreport.edu">helpdesk@solvexreport.edu</a>
            </small>
        </div>
    </div>
</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
