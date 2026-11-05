<?php
/**
 * Solvex Report — User Login
 * 
 * Supports authentication for:
 * - Students: Register Number + Password
 * - Staff / Admin: Staff Code or Email + Password
 */
define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';

init_session();

// If already logged in, redirect to their role home
if (is_logged_in()) {
    redirect(role_home($_SESSION['user_role'] ?? 'student'));
}

$error = null;
$identifier = '';

if (is_post()) {
    csrf_verify();

    $identifier = post('identifier');
    $password   = post('password');

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter both your identifier and password.';
    } else {
        $result = attempt_login($identifier, $password);

        if ($result['success']) {
            flash('success', 'Welcome back, ' . htmlspecialchars($_SESSION['user_name'] ?? '') . '!');
            redirect(role_home($_SESSION['user_role'] ?? 'student'));
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = 'Sign In';
$bodyClass = 'bg-light';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo">
            <a href="<?= base_url() ?>" aria-label="Solvex Report Home">
                <img src="<?= asset_url('img/logo-full.svg') ?>" alt="Solvex Report" height="44">
            </a>
        </div>

        <h1 class="auth-title h3 fw-bold">Sign In to Solvex</h1>
        <p class="auth-subtitle">Access your campus complaints or department queue</p>

        <?php if ($error): ?>
            <div class="alert-solvex danger" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= base_url('login.php') ?>" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="form-group mb-3">
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
                        placeholder="e.g. 2024CS001, STF001, or email" 
                        required 
                        autocomplete="username"
                        autofocus
                    >
                </div>
                <div class="form-text">
                    Students use your University Register No. Staff use your Staff ID or official email.
                </div>
                <div class="invalid-feedback"></div>
            </div>

            <div class="form-group mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label-solvex mb-0">
                        Password <span class="text-danger">*</span>
                    </label>
                    <a href="<?= base_url('forgot.php') ?>" class="small text-primary text-decoration-none">
                        Forgot password?
                    </a>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input 
                        type="password" 
                        class="form-control-solvex form-control border-start-0 ps-0" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password" 
                        required 
                        autocomplete="current-password"
                    >
                </div>
                <div class="invalid-feedback"></div>
            </div>

            <button type="submit" class="btn-solvex btn-primary w-100 py-2 mb-3">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>

        <div class="auth-divider">New to Solvex Report?</div>

        <div class="text-center">
            <a href="<?= base_url('register.php') ?>" class="btn-solvex btn-secondary w-100 py-2">
                <i class="bi bi-person-plus"></i> Register as a Student
            </a>
        </div>

        <div class="auth-footer">
            <small class="text-muted">
                Staff accounts are provisioned by Administration. 
                Need access? Contact <a href="mailto:admin@solvexreport.edu">admin@solvexreport.edu</a>
            </small>
        </div>
    </div>
</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
