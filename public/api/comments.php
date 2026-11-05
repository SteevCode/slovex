<?php
/**
 * Solvex Report — Public comments JSON endpoint
 *
 * Returns non-internal comments for a complaint after can_view_complaint().
 * Unauthorized access yields HTTP 404 (never 403).
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';

init_session();

$complaintId = (int) get('id', get('complaint_id', '0'));
if ($complaintId <= 0 || !is_logged_in()) {
    deny_complaint_not_found(false);
}

$user = current_user();
$pdo  = db();

$stmt = $pdo->prepare(
    'SELECT c.*, cat.sensitive
     FROM complaints c
     JOIN categories cat ON cat.id = c.category_id
     WHERE c.id = ?'
);
$stmt->execute([$complaintId]);
$complaint = $stmt->fetch();

if (!$complaint) {
    deny_complaint_not_found(false);
}

require_can_view_complaint($user, $complaint, false);

$canSeeInternal = can_view_internal_notes($user, $complaint);
$sql = '
    SELECT c.id, c.body, c.is_internal, c.created_at, c.user_id,
           u.full_name AS author_name, u.role AS author_role
    FROM comments c
    JOIN users u ON u.id = c.user_id
    WHERE c.complaint_id = ? ' . ($canSeeInternal ? '' : 'AND c.is_internal = 0') . '
    ORDER BY c.created_at ASC
';
$comStmt = $pdo->prepare($sql);
$comStmt->execute([$complaintId]);
$rows = $comStmt->fetchAll();

$canSeeReporter = can_see_reporter($user, $complaint);
$ownerId = (int) $complaint['student_id'];

$payload = array_map(static function (array $row) use ($canSeeReporter, $ownerId): array {
    $author = $row['author_name'];
    if ((int) $row['user_id'] === $ownerId && !$canSeeReporter) {
        $author = 'Anonymous student';
    }
    return [
        'id'          => (int) $row['id'],
        'body'        => $row['body'],
        'created_at'  => $row['created_at'],
        'author_name' => $author,
        'author_role' => $row['author_role'],
    ];
}, $rows);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'comments' => $payload]);
exit;
