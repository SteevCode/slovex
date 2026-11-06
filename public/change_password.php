<?php
/**
 * Solvex Report — Change Password (Universal for all roles)
 */
define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';

init_session();
require_login();

$user = current_user();
$userId = (int) $user['id'];
$pdo = db();
$error = null;

if (is_post()) {
    csrf_verify();

    $currentPass = post('current_password');
    $newPass     = post('new_password');
    $confirmPass = post('confirm_password');

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $currHash = $stmt->fetchColumn();

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $error = 'All fields are required.';
    } elseif (!password_verify($currentPass, $currHash)) {
        $error = 'Current password was entered incorrectly.';
    } elseif (strlen($newPass) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'New password and confirmation do not match.';
    } else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?');
        $upd->execute([$newHash, $userId]);

        flash('success', 'Your password has been updated successfully!');
        redirect(role_home($user['role']));
    }
}

$pageTitle = 'Change Password';
$bodyClass = 'bg-light';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card-solvex shadow-sm border-0 p-4">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-primary-subtle text-primary mb-2">
                        <i class="bi bi-shield-lock-fill fs-3"></i>
                    </div>
                    <h1 class="h4 fw-bold text-navy mb-1">Change Account Password</h1>
                    <p class="text-muted small">
                        <?= !empty($user['must_change_password']) ? 'Please choose a permanent password before continuing.' : 'Update your login password regularly to protect your account.' ?>
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="alert-solvex danger mb-4" role="alert">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= base_url('change_password.php') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="current_password" class="form-label-solvex">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control-solvex form-control" id="current_password" name="current_password" required autofocus autocomplete="current-password">
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label-solvex">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control-solvex form-control" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                        <div class="form-text">Minimum 8 characters. Include letters, numbers, or symbols.</div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label-solvex">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control-solvex form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn-solvex btn-primary w-100 py-2">
                        <i class="bi bi-check-lg me-1"></i> Update Password
                    </button>
                </form>

                <?php if (empty($user['must_change_password'])): ?>
                    <div class="text-center mt-3">
                        <a href="<?= role_home($user['role']) ?>" class="small text-muted text-decoration-none">
                            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>

