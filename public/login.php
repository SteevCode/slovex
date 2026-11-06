<?php
/**
 * Solvex Report — Unified Authentication Portal
 * 
 * Supports distinct faces for:
 * - Student Login (Default)
 * - Faculty / Staff / Warden Login
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

// Determine active portal face ('student' or 'staff')
$role = get('role', 'student');
if (!in_array($role, ['student', 'staff'], true)) {
    $role = 'student';
}

if (is_post()) {
    csrf_verify();

    $identifier = trim(post('identifier', ''));
    $password   = post('password', '');
    $rolePost   = post('role', 'student');
    if (in_array($rolePost, ['student', 'staff'], true)) {
        $role = $rolePost;
    }

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter both your ID and password.';
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

$isStaff = ($role === 'staff');
$pageTitle = $isStaff ? 'Faculty & Staff Login' : 'Student Login';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-5 d-flex justify-content-center align-items-center" style="min-height: calc(100vh - 160px);">
    <div class="card shadow-sm border" style="max-width: 440px; width: 100%; border-top: 4px solid <?= $isStaff ? '#14213D' : 'var(--clr-brand-accent)' ?> !important; border-radius: 6px;">
        <div class="card-body p-4 p-sm-5">
            
            <!-- Quick Portal Switcher Pills -->
            <div class="d-flex rounded p-1 mb-4 bg-light border" role="tablist">
                <a href="<?= base_url('login.php?role=student') ?>" 
                   class="btn btn-sm flex-fill text-center text-decoration-none rounded <?= !$isStaff ? 'btn-primary shadow-sm fw-bold' : 'btn-light text-secondary' ?>">
                    <i class="bi bi-mortarboard-fill me-1"></i> Student
                </a>
                <a href="<?= base_url('login.php?role=staff') ?>" 
                   class="btn btn-sm flex-fill text-center text-decoration-none rounded <?= $isStaff ? 'btn-dark shadow-sm fw-bold' : 'btn-light text-secondary' ?>">
                    <i class="bi bi-shield-lock-fill me-1"></i> Faculty &amp; Staff
                </a>
            </div>

            <!-- Header Face -->
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2" 
                     style="width: 52px; height: 52px; background: <?= $isStaff ? '#14213D' : 'var(--clr-brand-accent)' ?>; color: #fff;">
                    <i class="bi <?= $isStaff ? 'bi-shield-check fs-4' : 'bi-mortarboard fs-4' ?>"></i>
                </div>
                <h1 class="h4 fw-bold text-dark mb-1">
                    <?= $isStaff ? 'Faculty &amp; Staff Login' : 'Student Login' ?>
                </h1>
                <p class="text-secondary small mb-0">
                    <?= $isStaff ? 'Hostel Warden &amp; Maintenance Portal' : 'Hostel Complaint &amp; Maintenance Portal' ?>
                </p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-3 small" role="alert">
                    <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="<?= base_url('login.php?role=' . urlencode($role)) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="role" value="<?= e($role) ?>">

                <div class="mb-3">
                    <label for="identifier" class="form-label small fw-semibold text-dark mb-1">
                        <?= $isStaff ? 'Staff ID or Email' : 'Register Number' ?>
                    </label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="identifier" 
                        name="identifier" 
                        value="<?= e($identifier) ?>" 
                        placeholder="<?= $isStaff ? 'STF001 or email' : '2024CS100' ?>" 
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

                <button type="submit" class="btn <?= $isStaff ? 'btn-dark' : 'btn-solvex btn-primary' ?> w-100 py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </form>

            <!-- Bottom Role Links -->
            <div class="pt-4 mt-4 border-top text-center small">
                <?php if ($isStaff): ?>
                    <span class="text-secondary">Are you a student resident?</span>
                    <a href="<?= base_url('login.php?role=student') ?>" class="fw-semibold ms-1 text-decoration-none">
                        Student Login &rarr;
                    </a>
                <?php else: ?>
                    <div class="mb-2">
                        <span class="text-secondary">New student?</span>
                        <a href="<?= base_url('register.php') ?>" class="fw-semibold ms-1 text-decoration-none">
                            Register here
                        </a>
                    </div>
                    <div>
                        <span class="text-secondary">Hostel staff or warden?</span>
                        <a href="<?= base_url('login.php?role=staff') ?>" class="fw-semibold ms-1 text-decoration-none">
                            Staff Login &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
