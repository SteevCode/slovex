<?php
/**
 * Solvex Report — Notifications View
 * 
 * Displays full list of notifications for the logged-in user.
 * Supports marking individual or all items as read.
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';

init_session();
require_login();

$user   = current_user();
$userId = (int) $user['id'];
$pdo    = db();

if (is_post() && post('action') === 'mark_all_read') {
    csrf_verify();
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    flash('success', 'All notifications marked as read.');
    redirect(base_url('student/notifications.php'));
}

if (is_post() && post('action') === 'mark_one_read') {
    csrf_verify();
    $nid = (int) post('id', '0');
    if ($nid > 0) {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$nid, $userId]);
    }
    redirect(base_url('student/notifications.php'));
}

// Fetch all notifications with complaint reference
$stmt = $pdo->prepare('
    SELECT n.*, c.reference, c.title AS complaint_title
    FROM notifications n
    LEFT JOIN complaints c ON c.id = n.complaint_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 100
');
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notifications';
require_once SOLVEX_ROOT . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold text-navy mb-1">Notifications</h1>
                    <p class="text-muted small mb-0">Updates on your complaint status changes and replies.</p>
                </div>

                <?php if (!empty($notifications)): ?>
                    <form method="POST" action="<?= base_url('student/notifications.php') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn-solvex btn-secondary btn-sm">
                            <i class="bi bi-check2-all me-1"></i> Mark All as Read
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card-solvex border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state py-5 text-center">
                            <div class="empty-icon mx-auto mb-3">
                                <i class="bi bi-bell-slash fs-2 text-muted"></i>
                            </div>
                            <h4 class="h5 fw-bold text-navy">No notifications</h4>
                            <p class="text-muted small">You will receive notifications when department staff updates your complaint.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $n): ?>
                                <div class="list-group-item p-3 d-flex align-items-start gap-3 <?= !$n['is_read'] ? 'bg-primary-50' : '' ?>">
                                    <div class="mt-1">
                                        <i class="bi <?= !$n['is_read'] ? 'bi-circle-fill text-primary' : 'bi-check-circle text-muted' ?>" style="font-size: 0.65rem;"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="small fw-semibold text-navy mb-1">
                                            <?= e($n['message']) ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-3 text-muted" style="font-size: 0.75rem;">
                                            <span><?= format_date($n['created_at']) ?></span>
                                            <?php if (!empty($n['reference'])): ?>
                                                <a href="<?= base_url('complaint.php?ref=' . urlencode($n['reference'])) ?>" class="text-primary text-decoration-none fw-semibold">
                                                    View #<?= e($n['reference']) ?> <i class="bi bi-arrow-right"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!$n['is_read']): ?>
                                                <form method="POST" action="<?= base_url('student/notifications.php') ?>" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="mark_one_read">
                                                    <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                                    <button type="submit" class="btn btn-link p-0 small text-decoration-none">Mark read</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once SOLVEX_ROOT . '/includes/footer.php'; ?>
