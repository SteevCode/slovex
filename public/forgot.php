<?php
/**
 * Solvex Report — Forgot Password
 * 
 * Requests a secure password reset link.
 * Implements strict user enumeration protection with generic responses.
 */
define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';
require_once SOLVEX_ROOT . '/includes/mailer.php';

init_session();

if (is_logged_in()) {
    redirect(role_home($_SESSION['user_role'] ?? 'student'));
}

$message   = null;
$debugLink = null;
$identifier = '';

if (is_post()) {
    csrf_verify();

    $identifier = post('identifier');

    if (empty($identifier)) {
        $message = 'Please enter your register number, staff code, or email address.';
    } else {
        $result = create_reset_token($identifier);

        // If a matching user was found and a token generated, send the email
        if (!empty($result['token']) && !empty($result['email'])) {
            $resetUrl = base_url('reset.php?token=' . urlencode($result['token']));
            $emailSubject = 'Password Reset Request — Solvex Report';
            $emailBody = "
                <h2>Solvex Report Password Reset</h2>
                <p>Hello,</p>
                <p>We received a request to reset the password for your Solvex Report account.</p>
                <p>Click the link below to set a new password (valid for 30 minutes, single use):</p>
                <p><a href=\"{$resetUrl}\" style=\"background:#0E9F8E;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;\">Reset Password</a></p>
                <p>Or copy this URL into your browser:<br><a href=\"{$resetUrl}\">{$resetUrl}</a></p>
                <p>If you did not request this password reset, please ignore this email. Your existing password remains secure.</p>
                <p>— The Solvex Report Team</p>
            ";

            send_mail($result['email'], $emailSubject, $emailBody);

            if (APP_ENV === 'local' && defined('ENABLE_DEMO_TOOLS') && ENABLE_DEMO_TOOLS) {
                $debugLink = $resetUrl;
            }
        }

        // Always show generic message to prevent account enumeration
        $message = 'If the details match an account, a reset link has been sent.';
    }
}

$pageTitle = 'Forgot Password';
$bodyClass = 'bg-light';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="auth-wrapper py-5">
    <div class="auth-card" style="max-width: 480px;">
        <div class="auth-logo">
            <a href="<?= base_url() ?>" aria-label="Solvex Report Home">
                <img src="<?= asset_url('img/logo-with-text.png') ?>" alt="Solvex Report" height="44">
            </a>
        </div>

        <h1 class="auth-title h3 fw-bold">Password Recovery</h1>
        <p class="auth-subtitle">Enter your details to receive a secure password reset link</p>

        <?php if ($message): ?>
            <div class="alert-solvex info" role="alert">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <div><?= e($message) ?></div>
            </div>

            <?php if ($debugLink): ?>
                <div class="debug-link mt-3 mb-4">
                    <div class="fw-bold text-dark mb-1">
                        <i class="bi bi-terminal-fill me-1"></i> Local Dev Reset Link (MAIL_MODE=log):
                    </div>
                    <a href="<?= $debugLink ?>" class="fw-semibold">
                        <?= e($debugLink) ?>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="<?= base_url('forgot.php') ?>" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="form-group mb-4">
                <label for="identifier" class="form-label-solvex">
                    Register Number / Staff Code / Email <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-person"></i>
                    </span>
                    <input 
                        type="text" 
                        class="form-control-solvex form-control border-start-0 ps-0" 
                        id="identifier" 
                        name="identifier" 
                        value="<?= e($identifier) ?>" 
                        placeholder="e.g. 2024CS001, ADM001, or email" 
                        required 
                        autocomplete="username"
                        autofocus
                    >
                </div>
                <div class="form-text">
                    We will send a 30-minute single-use reset link to the account's registered email address.
                </div>
                <div class="invalid-feedback"></div>
            </div>

            <button type="submit" class="btn-solvex btn-primary w-100 py-2 mb-3">
                <i class="bi bi-envelope-paper"></i> Send Reset Link
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="<?= base_url('login.php') ?>" class="small text-muted text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Remembered your password? Back to Sign In
            </a>
        </div>
    </div>
</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
