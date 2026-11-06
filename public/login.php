<?php
/**
 * Solvex Report — Student Login
 * 
 * Primary login interface for hostel students.
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

$error      = null;
$identifier = '';

if (is_post()) {
    csrf_verify();

    $identifier = trim(post('identifier', ''));
    $password   = post('password', '');

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter both your Register Number and password.';
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

$pageTitle = 'Student Login';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-5 d-flex justify-content-center align-items-center" style="min-height: calc(100vh - 160px);">
    <div class="card shadow-sm border" style="max-width: 420px; width: 100%; border-top: 4px solid var(--clr-brand-accent) !important; border-radius: 6px;">
        <div class="card-body p-4 p-sm-5">

            <!-- Student Face Header -->
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2" 
                     style="width: 52px; height: 52px; background: var(--clr-brand-accent); color: #fff;">
                    <i class="bi bi-mortarboard-fill fs-4"></i>
                </div>
                <h1 class="h4 fw-bold text-dark mb-1">Student Login</h1>
                <p class="text-secondary small mb-0">Hostel Maintenance &amp; Complaint Portal</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-3 small" role="alert">
                    <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Student Login Form -->
            <form method="POST" action="<?= base_url('login.php') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="identifier" class="form-label small fw-semibold text-dark mb-1">
                        Register Number
                    </label>
                    <input 
                        type="text" 
                        class="form-control text-uppercase" 
                        id="identifier" 
                        name="identifier" 
                        value="<?= e($identifier) ?>" 
                        placeholder="2024CS100" 
                        required 
                        autocomplete="username"
                        autofocus
                    >
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label small fw-semibold text-dark mb-0">
                            Password
                        </label>
                        <a href="<?= base_url('forgot.php') ?>" class="small text-decoration-none">
                            Forgot?
                        </a>
                    </div>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="password" 
                        name="password" 
                        placeholder="Password" 
                        required 
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit" class="btn btn-solvex btn-primary w-100 py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </form>

            <div class="pt-4 mt-4 border-top text-center small text-secondary">
                New student? 
                <a href="<?= base_url('register.php') ?>" class="fw-semibold ms-1 text-decoration-none">
                    Register here
                </a>
            </div>

        </div>
    </div>
</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
