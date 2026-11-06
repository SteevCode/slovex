<?php
/**
 * Solvex Report — User Login
 * 
 * Supports authentication for:
 * - Students: Register Number + Password (Primary)
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

$error      = null;
$identifier = '';
$portal     = get('portal', 'student') === 'staff' ? 'staff' : 'student';

if (is_post()) {
    csrf_verify();

    $identifier = post('identifier');
    $password   = post('password');
    $portalPost = post('portal', 'student');
    if ($portalPost === 'staff') {
        $portal = 'staff';
    }

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

$pageTitle = 'Hostel Login';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="auth-wrapper py-5">
    <div class="auth-split-layout shadow-sm border">
        <!-- Sidebar Information -->
        <div class="auth-side p-4 p-lg-5">
            <div>
                <a href="<?= base_url() ?>" class="d-inline-block mb-3 text-decoration-none">
                    <img src="<?= asset_url('img/logo-with-text.png') ?>" alt="Solvex Report" height="38">
                </a>
                <h2 class="h5 fw-bold mb-2 text-dark">Hostel Maintenance Portal</h2>
                <p class="text-secondary small mb-4">
                    Official residential issue reporting, warden triage, and repair tracking for student rooms and common amenities.
                </p>

                <div class="border rounded-2 p-3 bg-white mb-4 small">
                    <div class="fw-bold text-dark mb-2"><i class="bi bi-info-circle text-primary me-1"></i> Sign-In Credentials</div>
                    <div class="mb-2 pb-2 border-bottom">
                        <div class="fw-semibold text-primary"><i class="bi bi-mortarboard me-1"></i> Student Portal (Primary)</div>
                        <div class="text-secondary">Enter your <strong>University Register Number</strong> (e.g. <code>2024CS100</code>) or registered email.</div>
                    </div>
                    <div>
                        <div class="fw-semibold text-dark"><i class="bi bi-shield-check me-1"></i> Staff &amp; Warden Portal</div>
                        <div class="text-secondary">Maintenance staff use <strong>Staff ID</strong> (e.g. <code>STF001</code>). Wardens use official admin email.</div>
                    </div>
                </div>
            </div>

            <div class="pt-3 border-top small text-secondary">
                Emergency Hostel Assistance: <strong class="text-danger"><?= e(EMERGENCY_CONTACT_PHONE) ?></strong>
            </div>
        </div>

        <!-- Main Form Area -->
        <div class="auth-main p-4 p-lg-5">
            <div class="auth-logo d-md-none text-center mb-4">
                <a href="<?= base_url() ?>" aria-label="Solvex Report Home">
                    <img src="<?= asset_url('img/logo-with-text.png') ?>" alt="Solvex Report" height="40">
                </a>
            </div>

            <!-- Role Selector Switcher -->
            <div class="d-flex rounded p-1 mb-4 bg-light border" role="tablist" aria-label="Login Role Selection">
                <button type="button" 
                        class="btn btn-sm flex-fill fw-bold rounded <?= $portal === 'student' ? 'btn-primary shadow-sm' : 'btn-light text-secondary' ?>" 
                        id="btnPortalStudent" 
                        onclick="selectLoginPortal('student')">
                    <i class="bi bi-mortarboard-fill me-1"></i> Student Login
                </button>
                <button type="button" 
                        class="btn btn-sm flex-fill fw-semibold rounded <?= $portal === 'staff' ? 'btn-primary shadow-sm' : 'btn-light text-secondary' ?>" 
                        id="btnPortalStaff" 
                        onclick="selectLoginPortal('staff')">
                    <i class="bi bi-shield-lock-fill me-1"></i> Staff / Warden
                </button>
            </div>

            <!-- Dynamic Headers -->
            <div class="mb-4">
                <span class="badge bg-primary-subtle text-primary border mb-2" id="portalBadge">
                    <i class="bi bi-building-check me-1"></i> Hostel Resident Portal
                </span>
                <h1 class="h3 fw-bold text-dark mb-1" id="loginHeading">Student Hostel Login</h1>
                <p class="text-secondary small mb-0" id="loginSubtitle">
                    Sign in with your register number to submit room issues or verify completed repairs.
                </p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= base_url('login.php') ?>" data-validate novalidate id="loginForm">
                <?= csrf_field() ?>
                <input type="hidden" name="portal" id="portalInput" value="<?= e($portal) ?>">

                <div class="form-group mb-3">
                    <label for="identifier" class="form-label fw-semibold text-dark small" id="identifierLabel">
                        University Register Number <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-secondary border-end-0" id="identifierIconContainer">
                            <i class="bi bi-mortarboard" id="identifierIcon"></i>
                        </span>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="identifier" 
                            name="identifier" 
                            value="<?= e($identifier) ?>" 
                            placeholder="e.g. 2024CS100 or student email" 
                            required 
                            autocomplete="username"
                            autofocus
                        >
                    </div>
                    <div class="form-text small text-secondary mt-1" id="identifierHelp">
                        Enter your university Register Number (e.g. 2024CS100) or registered email address.
                    </div>
                    <div class="invalid-feedback"></div>
                </div>

                <div class="form-group mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label fw-semibold text-dark small mb-0">
                            Password <span class="text-danger">*</span>
                        </label>
                        <a href="<?= base_url('forgot.php') ?>" class="small text-primary text-decoration-none">
                            Forgot password?
                        </a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-secondary border-end-0">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            placeholder="Enter your password" 
                            required 
                            autocomplete="current-password"
                        >
                    </div>
                    <div class="invalid-feedback"></div>
                </div>

                <button type="submit" class="btn-solvex btn-primary w-100 py-2 mb-3" id="submitBtn">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Student Sign In
                </button>
            </form>

            <!-- Student Registration Callout -->
            <div id="studentRegistrationBlock" class="<?= $portal === 'staff' ? 'd-none' : '' ?>">
                <div class="auth-divider my-3">New Hostel Resident?</div>
                <div class="text-center">
                    <a href="<?= base_url('register.php') ?>" class="btn-solvex btn-secondary w-100 py-2">
                        <i class="bi bi-person-plus-fill me-1"></i> Register Student Account
                    </a>
                </div>
            </div>

            <!-- Staff Info Callout -->
            <div id="staffNoticeBlock" class="<?= $portal === 'staff' ? '' : 'd-none' ?> mt-3">
                <div class="p-3 bg-light rounded-2 border text-center small text-secondary">
                    <i class="bi bi-info-circle me-1 text-primary"></i> Maintenance technician and warden accounts are provisioned by central administration.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectLoginPortal(portal) {
    const btnStudent = document.getElementById('btnPortalStudent');
    const btnStaff   = document.getElementById('btnPortalStaff');
    const portalBadge= document.getElementById('portalBadge');
    const heading    = document.getElementById('loginHeading');
    const subtitle   = document.getElementById('loginSubtitle');
    const idLabel    = document.getElementById('identifierLabel');
    const idInput    = document.getElementById('identifier');
    const idHelp     = document.getElementById('identifierHelp');
    const idIcon     = document.getElementById('identifierIcon');
    const submitBtn  = document.getElementById('submitBtn');
    const studentReg = document.getElementById('studentRegistrationBlock');
    const staffNotice= document.getElementById('staffNoticeBlock');
    const portalInput= document.getElementById('portalInput');

    if (portal === 'staff') {
        portalInput.value = 'staff';
        btnStudent.className = 'btn btn-sm flex-fill fw-semibold rounded btn-light text-secondary';
        btnStaff.className   = 'btn btn-sm flex-fill fw-bold rounded btn-primary shadow-sm';

        portalBadge.innerHTML = '<i class="bi bi-shield-lock me-1"></i> Administration & Maintenance';
        portalBadge.className = 'badge bg-secondary-subtle text-secondary border mb-2';

        heading.textContent  = 'Staff & Warden Login';
        subtitle.textContent = 'Authorized access for hostel wardens and maintenance department technicians.';
        idLabel.innerHTML    = 'Staff Code or Official Email <span class="text-danger">*</span>';
        idInput.placeholder  = 'e.g. STF001 or warden@solvexreport.edu';
        idHelp.textContent   = 'Technicians use your Staff Code (STF...). Wardens use your official admin email.';
        idIcon.className     = 'bi bi-person-badge';
        submitBtn.innerHTML  = '<i class="bi bi-shield-lock-fill me-1"></i> Staff / Warden Sign In';

        studentReg.classList.add('d-none');
        staffNotice.classList.remove('d-none');
    } else {
        portalInput.value = 'student';
        btnStudent.className = 'btn btn-sm flex-fill fw-bold rounded btn-primary shadow-sm';
        btnStaff.className   = 'btn btn-sm flex-fill fw-semibold rounded btn-light text-secondary';

        portalBadge.innerHTML = '<i class="bi bi-building-check me-1"></i> Hostel Resident Portal';
        portalBadge.className = 'badge bg-primary-subtle text-primary border mb-2';

        heading.textContent  = 'Student Hostel Login';
        subtitle.textContent = 'Sign in with your register number to submit room issues or verify completed repairs.';
        idLabel.innerHTML    = 'University Register Number <span class="text-danger">*</span>';
        idInput.placeholder  = 'e.g. 2024CS100 or student email';
        idHelp.textContent   = 'Enter your university Register Number (e.g. 2024CS100) or registered email address.';
        idIcon.className     = 'bi bi-mortarboard';
        submitBtn.innerHTML  = '<i class="bi bi-box-arrow-in-right me-1"></i> Student Sign In';

        studentReg.classList.remove('d-none');
        staffNotice.classList.add('d-none');
    }
}

// Initial setup if portal=staff in URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('portal') === 'staff') {
        selectLoginPortal('staff');
    }
});
</script>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
