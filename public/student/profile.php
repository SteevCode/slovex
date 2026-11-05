<?php
/**
 * Solvex Report — Student Profile & Security
 * 
 * Displays student academic details and provides password change facility.
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';

init_session();
require_role('student');

$user   = current_user();
$userId = (int) $user['id'];
$pdo    = db();

// Fetch student master record if available
$smStmt = $pdo->prepare('SELECT full_name, department, year, hosteller FROM student_master WHERE register_no = ?');
$smStmt->execute([$user['register_no']]);
$studentMaster = $smStmt->fetch();

$error   = null;
$success = null;

if (is_post() && post('action') === 'change_password') {
    csrf_verify();

    $currentPass = post('current_password');
    $newPass     = post('new_password');
    $confirmPass = post('confirm_password');

    // Fetch user password_hash
    $uStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $uStmt->execute([$userId]);
    $currHash = $uStmt->fetchColumn();

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $error = 'All password fields are required.';
    } elseif (!password_verify($currentPass, $currHash)) {
        $error = 'Your current password was entered incorrectly.';
    } elseif (strlen($newPass) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'New password and confirmation do not match.';
    } else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $updStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $updStmt->execute([$newHash, $userId]);
        $success = 'Your password has been updated successfully!';
    }
}

$pageTitle = 'Student Profile';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h1 class="h3 fw-bold text-navy mb-4">Student Profile &amp; Settings</h1>

            <?php if ($error): ?>
                <div class="alert-solvex danger mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-solvex success mb-4" role="alert">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <div><?= e($success) ?></div>
                </div>
            <?php endif; ?>

            <!-- Profile Info Card -->
            <div class="card-solvex border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold text-navy mb-0"><i class="bi bi-person-badge me-2 text-primary"></i> Academic Enrolment Details</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="avatar-initials lg" style="background-color: <?= avatar_color($user['full_name']) ?>;">
                            <?= e(initials($user['full_name'])) ?>
                        </span>
                        <div>
                            <h4 class="h5 fw-bold text-navy mb-1"><?= e($user['full_name']) ?></h4>
                            <div class="text-muted small">
                                <span class="badge bg-primary-subtle text-primary border me-1">Verified Student</span>
                                <?= e($user['email']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 small">
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="text-muted">Register Number</div>
                                <div class="fw-bold text-navy font-monospace fs-6"><?= e($user['register_no']) ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="text-muted">Department</div>
                                <div class="fw-bold text-navy"><?= e($studentMaster['department'] ?? 'Computer Science') ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="text-muted">Academic Year</div>
                                <div class="fw-bold text-navy">Year <?= (int)($studentMaster['year'] ?? 1) ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="text-muted">Hosteller Status</div>
                                <div class="fw-bold text-navy">
                                    <?= !empty($studentMaster['hosteller']) ? '<span class="text-primary"><i class="bi bi-house-check me-1"></i> Hosteller</span>' : '<span class="text-muted">Day Scholar</span>' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Password Card -->
            <div class="card-solvex border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold text-navy mb-0"><i class="bi bi-shield-lock me-2 text-primary"></i> Change Password</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="<?= base_url('student/profile.php') ?>" data-validate novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group mb-3">
                            <label for="current_password" class="form-label-solvex">Current Password <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" id="current_password" class="form-control-solvex" required autocomplete="current-password">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="new_password" class="form-label-solvex">New Password <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" id="new_password" class="form-control-solvex" minlength="8" required autocomplete="new-password">
                            <div class="strength-meter mt-2">
                                <div class="strength-meter-fill" id="newStrengthBar"></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <span class="small text-muted">Strength:</span>
                                <span class="small fw-semibold" id="newStrengthLabel"></span>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group mb-4">
                            <label for="confirm_password" class="form-label-solvex">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control-solvex" minlength="8" required autocomplete="new-password">
                            <div class="invalid-feedback"></div>
                        </div>

                        <button type="submit" class="btn-solvex btn-primary py-2 px-4">
                            <i class="bi bi-check-lg me-1"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('new_password')) {
        Solvex.passwordStrength.init('#new_password', '#newStrengthBar', '#newStrengthLabel');
    }
});
</script>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
