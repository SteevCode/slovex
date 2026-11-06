<?php
/**
 * Solvex Report — Student Registration
 * 
 * 3-Step Multi-Stage Onboarding:
 * Step 1: University Register Number Verification
 * Step 2: Campus Email Address
 * Step 3: Secure Password Setup with Real-time Strength Meter
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

$error        = null;
$success      = null;
$debugLink    = null;
$registerNo   = '';
$email        = '';

if (is_post()) {
    csrf_verify();

    $registerNo      = strtoupper(post('register_no'));
    $email           = post('email');
    $password        = post('password');
    $passwordConfirm = post('password_confirm');

    // Validation
    if (empty($registerNo) || empty($email) || empty($password)) {
        $error = 'All fields are required. Please complete all 3 steps.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Passwords do not match. Please verify and try again.';
    } else {
        // Attempt registration
        $result = register_student($registerNo, $email, $password);

        if (!$result['success']) {
            $error = $result['message'];
        } else {
            // Success: send verification email
            $verifyUrl = base_url('verify.php?token=' . urlencode($result['token']));
            $emailSubject = 'Verify your Solvex Report Student Account';
            $emailBody = "
                <h2>Welcome to Solvex Report!</h2>
                <p>Hello,</p>
                <p>Your student account registration for Register No <strong>" . e($registerNo) . "</strong> has been initiated.</p>
                <p>Please click the button or link below to verify your email and activate your account (valid for 24 hours):</p>
                <p><a href=\"{$verifyUrl}\" style=\"background:#0E9F8E;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;\">Verify Email Address</a></p>
                <p>Or copy this URL into your browser:<br><a href=\"{$verifyUrl}\">{$verifyUrl}</a></p>
                <p>If you did not request this registration, please ignore this email.</p>
                <p>— The Solvex Report Team</p>
            ";

            $mailResult = send_mail($email, $emailSubject, $emailBody);
            $success = 'Account created successfully! We sent a verification link to ' . e($email) . '. Please click the link to activate your account.';

            // In local development with log mode, expose link for seamless testing
            if (APP_ENV === 'local') {
                $debugLink = $verifyUrl;
            }
        }
    }
}

$pageTitle = 'Student Registration';
$bodyClass = 'bg-light';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="auth-wrapper py-5">
    <div class="auth-split-layout">
        <div class="auth-side">
            <div>
                <a href="<?= base_url() ?>" class="d-inline-block mb-4 text-white text-decoration-none">
                    <img src="<?= asset_url('img/logo-light.svg') ?>" alt="Solvex Report" height="38">
                </a>
                <h2 class="h3 fw-bold text-white mb-3">Join Your Campus Community</h2>
                <p class="text-white-50 small mb-4">
                    Register with your official student registration number to report issues, vote on urgent fixes, and track repairs.
                </p>
                <div class="mb-4">
                    <img src="<?= asset_url('img/auth-side.webp') ?>" alt="Solvex Campus" class="img-fluid rounded-3 shadow-sm border border-light border-opacity-25" width="400" height="240" loading="eager">
                </div>
            </div>
            <div class="pt-3 border-top border-light border-opacity-25 small text-white-50">
                <i class="bi bi-shield-check text-warning me-1"></i> Verified student credentials safeguard campus integrity.
            </div>
        </div>

        <div class="auth-main">
            <div class="auth-logo d-md-none text-center mb-4">
                <a href="<?= base_url() ?>" aria-label="Solvex Report Home">
                    <img src="<?= asset_url('img/logo-full.svg') ?>" alt="Solvex Report" height="40">
                </a>
            </div>

            <h1 class="h3 fw-bold text-navy mb-1">Student Registration</h1>
            <p class="text-muted small mb-4">Verify your student identity to report and track issues</p>

        <?php if ($success): ?>
            <div class="alert-solvex success" role="alert">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div>
                    <strong>Verification Email Sent!</strong><br>
                    <?= $success ?>
                </div>
            </div>

            <?php if ($debugLink): ?>
                <div class="debug-link mt-3 mb-4">
                    <div class="fw-bold text-dark mb-1">
                        <i class="bi bi-terminal-fill me-1"></i> Local Dev Verification Link (MAIL_MODE=log):
                    </div>
                    <a href="<?= $debugLink ?>" class="fw-semibold">
                        <?= e($debugLink) ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="<?= base_url('login.php') ?>" class="btn-solvex btn-primary w-100 py-2">
                    <i class="bi bi-box-arrow-in-right"></i> Proceed to Login
                </a>
            </div>
        <?php else: ?>
            <!-- Progress Bar -->
            <div class="progress-bar-solvex" id="registrationProgress" aria-label="Registration Progress">
                <div class="step active" data-step-indicator="1" title="Step 1: Register No"></div>
                <div class="step" data-step-indicator="2" title="Step 2: Email"></div>
                <div class="step" data-step-indicator="3" title="Step 3: Password"></div>
            </div>

            <?php if ($error): ?>
                <div class="alert-solvex danger" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-5"></i>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= base_url('register.php') ?>" id="registerForm" novalidate>
                <?= csrf_field() ?>

                <!-- ── STEP 1: Register Number ───────────────────────── -->
                <div class="form-step" data-step="1">
                    <div class="form-group mb-4">
                        <label for="register_no" class="form-label-solvex">
                            University Register Number <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <i class="bi bi-card-heading"></i>
                            </span>
                            <input 
                                type="text" 
                                class="form-control-solvex form-control border-start-0 ps-0 text-uppercase" 
                                id="register_no" 
                                name="register_no" 
                                value="<?= e($registerNo) ?>" 
                                placeholder="e.g. 2024CS001" 
                                required 
                                pattern="^[A-Za-z0-9]{5,20}$"
                                autocomplete="off"
                                autofocus
                            >
                        </div>
                        <div class="form-text">
                            Must match your official college enrolment record (e.g. 2024CS001 to 2024CS030).
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <button type="button" class="btn-solvex btn-primary w-100 py-2" id="step1NextBtn">
                        Continue to Step 2 <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>

                <!-- ── STEP 2: Email Address ─────────────────────────── -->
                <div class="form-step" data-step="2" style="display: none;">
                    <div class="form-group mb-4">
                        <label for="email" class="form-label-solvex">
                            Personal or College Email <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input 
                                type="email" 
                                class="form-control-solvex form-control border-start-0 ps-0" 
                                id="email" 
                                name="email" 
                                value="<?= e($email) ?>" 
                                placeholder="student@college.edu or name@gmail.com" 
                                required 
                                autocomplete="email"
                            >
                        </div>
                        <div class="form-text">
                            We will send an activation verification link to this email.
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn-solvex btn-secondary flex-grow-1" id="step2BackBtn">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <button type="button" class="btn-solvex btn-primary flex-grow-1" id="step2NextBtn">
                            Continue <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                <!-- ── STEP 3: Password & Confirmation ───────────────── -->
                <div class="form-step" data-step="3" style="display: none;">
                    <div class="form-group mb-3">
                        <label for="password" class="form-label-solvex">
                            Create Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <i class="bi bi-shield-lock"></i>
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
                            Confirm Password <span class="text-danger">*</span>
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
                                placeholder="Repeat your password" 
                                required 
                                minlength="8"
                                autocomplete="new-password"
                            >
                        </div>
                        <div class="invalid-feedback" id="confirmFeedback"></div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn-solvex btn-secondary flex-grow-1" id="step3BackBtn">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <button type="submit" class="btn-solvex btn-primary flex-grow-1" id="submitRegBtn">
                            <i class="bi bi-check-lg me-1"></i> Complete Registration
                        </button>
                    </div>
                </div>
            </form>

            <div class="auth-divider">Already registered?</div>

            <div class="text-center">
                <a href="<?= base_url('login.php') ?>" class="btn-solvex btn-secondary w-100 py-2">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In to Existing Account
                </a>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    Solvex.multiStep.init(3);

    const step1Next = document.getElementById('step1NextBtn');
    const step2Next = document.getElementById('step2NextBtn');
    const step2Back = document.getElementById('step2BackBtn');
    const step3Back = document.getElementById('step3BackBtn');
    const regNoInput = document.getElementById('register_no');
    const emailInput = document.getElementById('email');
    const passInput  = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirm');
    const form       = document.getElementById('registerForm');

    // Password strength init
    Solvex.passwordStrength.init('#password', '#strengthBar', '#strengthLabel');

    if (step1Next) {
        step1Next.addEventListener('click', function() {
            const val = regNoInput.value.trim();
            if (!val) {
                regNoInput.classList.add('is-invalid');
                regNoInput.parentElement.nextElementSibling.nextElementSibling.textContent = 'Please enter your register number.';
                return;
            }
            regNoInput.classList.remove('is-invalid');
            regNoInput.classList.add('is-valid');
            Solvex.multiStep.showStep(2);
            emailInput.focus();
        });
    }

    if (step2Next) {
        step2Next.addEventListener('click', function() {
            const val = emailInput.value.trim();
            const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!val || !emailRe.test(val)) {
                emailInput.classList.add('is-invalid');
                emailInput.parentElement.nextElementSibling.nextElementSibling.textContent = 'Please enter a valid email address.';
                return;
            }
            emailInput.classList.remove('is-invalid');
            emailInput.classList.add('is-valid');
            Solvex.multiStep.showStep(3);
            passInput.focus();
        });
    }

    if (step2Back) {
        step2Back.addEventListener('click', function() {
            Solvex.multiStep.showStep(1);
        });
    }

    if (step3Back) {
        step3Back.addEventListener('click', function() {
            Solvex.multiStep.showStep(2);
        });
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            let valid = true;
            if (passInput.value.length < 8) {
                passInput.classList.add('is-invalid');
                valid = false;
            }
            if (confirmInput.value !== passInput.value) {
                confirmInput.classList.add('is-invalid');
                document.getElementById('confirmFeedback').textContent = 'Passwords do not match.';
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
