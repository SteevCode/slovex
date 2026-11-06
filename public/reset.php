<?php
/**
 * Solvex Report — Password Reset
 * 
 * Verifies the single-use reset token and sets a new password.
 * Invalidates all other tokens for this user upon completion.
 */
define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';

init_session();

$token   = is_post() ? post('token') : get('token');
$error   = null;
$tokenOk = false;
$userId  = null;
$tokenId = null;

if (empty($token)) {
    $error = 'No reset token provided. Please use the link sent to your email.';
} else {
    $verifyResult = verify_reset_token($token);
    if (!$verifyResult['success']) {
        $error = $verifyResult['message'];
    } else {
        $tokenOk = true;
        $userId  = $verifyResult['user_id'];
        $tokenId = $verifyResult['token_id'];
    }
}

// Handle form submission
if (is_post() && $tokenOk) {
    csrf_verify();

    $newPassword     = post('password');
    $passwordConfirm = post('password_confirm');

    if (empty($newPassword) || strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif ($newPassword !== $passwordConfirm) {
        $error = 'Passwords do not match. Please verify and try again.';
    } else {
        $resetResult = reset_password($userId, $tokenId, $newPassword);

        if ($resetResult['success']) {
            flash('success', 'Your password has been reset successfully! You can now log in.');
            redirect(base_url('login.php'));
        } else {
            $error = $resetResult['message'];
        }
    }
}

$pageTitle = 'Reset Password';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="auth-wrapper py-5">
    <div class="auth-card" style="max-width: 480px;">
        <div class="auth-logo">
            <a href="<?= base_url() ?>" aria-label="Solvex Report Home">
                <img src="<?= asset_url('img/logo-with-text.png') ?>" alt="Solvex Report" height="44">
            </a>
        </div>

        <h1 class="auth-title h3 fw-bold">Set New Password</h1>
        <p class="auth-subtitle">Choose a secure password for your Solvex Report account</p>

        <?php if ($error): ?>
            <div class="alert-solvex danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div><?= e($error) ?></div>
            </div>

            <?php if (!$tokenOk): ?>
                <div class="text-center mt-4">
                    <a href="<?= base_url('forgot.php') ?>" class="btn-solvex btn-secondary w-100 py-2">
                        <i class="bi bi-arrow-repeat me-1"></i> Request a New Reset Link
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($tokenOk): ?>
            <form method="POST" action="<?= base_url('reset.php') ?>" id="resetForm" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="form-group mb-3">
                    <label for="password" class="form-label-solvex">
                        New Password <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input 
                            type="password" 
                            class="form-control-solvex form-control border-start-0 ps-0" 
                            id="password" 
                            name="password" 
                            placeholder="Min. 8 characters" 
                            required 
                            minlength="8"
                            autocomplete="new-password"
                            autofocus
                        >
                    </div>
                    <div class="strength-meter mt-2">
                        <div class="strength-meter-fill" id="strengthBar"></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="small text-muted">Strength:</span>
                        <span class="small fw-semibold" id="strengthLabel"></span>
                    </div>
                    <div class="invalid-feedback"></div>
                </div>

                <div class="form-group mb-4">
                    <label for="password_confirm" class="form-label-solvex">
                        Confirm New Password <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="bi bi-check2-circle"></i>
                        </span>
                        <input 
                            type="password" 
                            class="form-control-solvex form-control border-start-0 ps-0" 
                            id="password_confirm" 
                            name="password_confirm" 
                            placeholder="Re-enter your new password" 
                            required 
                            minlength="8"
                            autocomplete="new-password"
                        >
                    </div>
                    <div class="invalid-feedback" id="confirmFeedback"></div>
                </div>

                <button type="submit" class="btn-solvex btn-primary w-100 py-2 mb-3">
                    <i class="bi bi-shield-check"></i> Reset Password &amp; Log In
                </button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-3">
            <a href="<?= base_url('login.php') ?>" class="small text-muted text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Return to Sign In
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('password')) {
        Solvex.passwordStrength.init('#password', '#strengthBar', '#strengthLabel');

        const form = document.getElementById('resetForm');
        const pass = document.getElementById('password');
        const confirm = document.getElementById('password_confirm');
        const feedback = document.getElementById('confirmFeedback');

        form.addEventListener('submit', function(e) {
            let valid = true;
            if (pass.value.length < 8) {
                pass.classList.add('is-invalid');
                valid = false;
            }
            if (confirm.value !== pass.value) {
                confirm.classList.add('is-invalid');
                feedback.textContent = 'Passwords do not match.';
                valid = false;
            }
            if (!valid) {
                e.preventDefault();
            }
        });
    }
});
</script>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
