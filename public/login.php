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
            flash('success', 'Welcome back, ' . ($_SESSION['user_name'] ?? '') . '!');
            redirect(role_home($_SESSION['user_role'] ?? 'student'));
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = 'Sign In';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-split-layout">
        <div class="auth-side">
            <div>
                <a href="<?= base_url() ?>" class="d-inline-block mb-3 text-decoration-none">
                    <img src="<?= asset_url('img/logo-with-text.png') ?>" alt="Solvex Report" height="36">
                </a>
                <h2 class="h5 fw-bold mb-2">Campus Incident System</h2>
                <p class="text-secondary small mb-4">
                    Official platform for college facilities, hostel maintenance, and transparent department triage.
                </p>
                <div class="border rounded p-3 bg-white mb-3 small">
                    <div class="fw-semibold mb-2">Login Credentials:</div>
                    <div class="text-secondary mb-1">&bull; <strong>Student:</strong> Register number (e.g. <code>2024CS100</code>)</div>
                    <div class="text-secondary mb-1">&bull; <strong>Staff:</strong> Staff code (e.g. <code>STF001</code>)</div>
                    <div class="text-secondary">&bull; <strong>Admin:</strong> Admin email (e.g. <code>admin@solvex.edu</code>)</div>
                </div>
            </div>
            <div class="pt-3 border-top small text-secondary">
                Emergency Facilities: <strong class="text-danger"><?= e(EMERGENCY_CONTACT_PHONE) ?></strong>
            </div>
        </div>

        <div class="auth-main">
            <div class="auth-logo d-md-none text-center mb-4">
                <a href="<?= base_url() ?>" aria-label="Solvex Report Home">
                    <img src="<?= asset_url('img/logo-with-text.png') ?>" alt="Solvex Report" height="42">
                </a>
            </div>

            <h1 class="h3 fw-bold text-navy mb-1">Sign In to Solvex</h1>
            <p class="text-muted small mb-4">Access your campus complaints or department queue</p>

            <?php if ($error): ?>
                <div class="alert-solvex danger mb-4" role="alert">
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

            <div class="auth-footer mt-4 pt-3 border-top">
                <small class="text-muted">
                    Staff accounts are provisioned by Administration. 
                    Need access? Contact <a href="mailto:admin@solvexreport.edu">admin@solvexreport.edu</a>
                </small>
            </div>
        </div>
    </div>
</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
