<?php
/**
 * Solvex Report — Student Registration
 * 
 * Clean, single-page student account registration:
 * - Register Number validation against student_master
 * - Email and password confirmation
 * - Email verification dispatch
 */

define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';

init_session();

if (is_logged_in()) {
    redirect(role_home($_SESSION['user_role'] ?? 'student'));
}

$error        = null;
$success      = null;
$debugLink    = null;
$registerNo   = '';
$email        = '';

if (is_post()) {
    csrf_verify();

    $registerNo      = strtoupper(trim(post('register_no', '')));
    $email           = strtolower(trim(post('email', '')));
    $password        = post('password', '');
    $passwordConfirm = post('password_confirm', '');

    if (empty($registerNo) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Passwords do not match.';
    } else {
        $result = register_student($registerNo, $email, $password);

        if (!$result['success']) {
            $error = $result['message'];
        } else {
            $verifyUrl = base_url('verify.php?token=' . urlencode($result['token']));
            $emailSubject = 'Verify your Solvex Report Student Account';
            $emailBody = "
                <h2>Welcome to Solvex Report!</h2>
                <p>Hello,</p>
                <p>Your student account registration for Register No <strong>" . e($registerNo) . "</strong> has been initiated.</p>
                <p>Please click the button or link below to verify your email and activate your account:</p>
                <p><a href=\"{$verifyUrl}\" style=\"background:#0E9F8E;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;\">Verify Email Address</a></p>
                <p>Or copy this URL into your browser:<br><a href=\"{$verifyUrl}\">{$verifyUrl}</a></p>
                <p>— Solvex Report Team</p>
            ";

            send_mail($email, $emailSubject, $emailBody);
            $success = 'Account created! We sent a verification link to ' . e($email) . '.';

            if (APP_ENV === 'local' && defined('ENABLE_DEMO_TOOLS') && ENABLE_DEMO_TOOLS) {
                $debugLink = $verifyUrl;
            }
        }
    }
}

$pageTitle = 'Student Registration';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-5 d-flex justify-content-center align-items-center" style="min-height: calc(100vh - 160px);">
    <div class="card shadow-sm border" style="max-width: 460px; width: 100%; border-top: 4px solid var(--clr-brand-accent) !important; border-radius: 6px;">
        <div class="card-body p-4 p-sm-5">

            <!-- Header Face -->
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2" 
                     style="width: 52px; height: 52px; background: var(--clr-brand-accent); color: #fff;">
                    <i class="bi bi-person-plus-fill fs-4"></i>
                </div>
                <h1 class="h4 fw-bold text-dark mb-1">Student Registration</h1>
                <p class="text-secondary small mb-0">Hostel Resident Self-Service Portal</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mb-3 small" role="alert">
                    <i class="bi bi-check-circle-fill flex-shrink-0 fs-5"></i>
                    <div><?= e($success) ?></div>
                </div>

                <?php if ($debugLink): ?>
                    <div class="p-3 bg-light border rounded mb-3 small">
                        <div class="fw-bold text-dark mb-1">Verification Link:</div>
                        <a href="<?= $debugLink ?>" class="text-break"><?= e($debugLink) ?></a>
                    </div>
                <?php endif; ?>

                <a href="<?= base_url('login.php?role=student') ?>" class="btn-solvex btn-primary w-100 py-2">
                    Proceed to Student Login &rarr;
                </a>
            <?php else: ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-3 small" role="alert">
                        <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= base_url('register.php') ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="register_no" class="form-label small fw-semibold text-dark mb-1">
                            Register Number <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control text-uppercase" 
                            id="register_no" 
                            name="register_no" 
                            value="<?= e($registerNo) ?>" 
                            placeholder="e.g. 2024CS100" 
                            required 
                            autocomplete="off"
                            autofocus
                        >
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold text-dark mb-1">
                            University Email <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="email" 
                            name="email" 
                            value="<?= e($email) ?>" 
                            placeholder="student@solvex.edu" 
                            required 
                            autocomplete="email"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label small fw-semibold text-dark mb-1">
                            Password <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            placeholder="Min 8 characters" 
                            required 
                            minlength="8"
                            autocomplete="new-password"
                        >
                    </div>

                    <div class="mb-4">
                        <label for="password_confirm" class="form-label small fw-semibold text-dark mb-1">
                            Confirm Password <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password_confirm" 
                            name="password_confirm" 
                            placeholder="Re-type password" 
                            required 
                            minlength="8"
                            autocomplete="new-password"
                        >
                    </div>

                    <button type="submit" class="btn-solvex btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-person-plus-fill me-1"></i> Register Student Account
                    </button>
                </form>

                <div class="pt-4 mt-4 border-top text-center small text-secondary">
                    Already registered? 
                    <a href="<?= base_url('login.php?role=student') ?>" class="fw-semibold ms-1 text-decoration-none">
                        Student Login &rarr;
                    </a>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
