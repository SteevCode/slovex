<?php
/**
 * Solvex Report — "I Am Also Affected" Toggle Endpoint
 * 
 * Allows students/users to upvote public complaints they are also experiencing.
 * Toggling off is supported (second click removes the vote).
 * Strictly prohibited on own complaints or private complaints.
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';

init_session();
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to register affected status.']);
    exit;
}

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!csrf_verify_ajax()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$complaintId = (int) post('complaint_id', '0');
if ($complaintId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID.']);
    exit;
}

$user = current_user();
$userId = (int) $user['id'];
$pdo = db();

// Fetch complaint details
$stmt = $pdo->prepare(
    'SELECT c.id, c.student_id, c.visibility, c.status, c.department_id, cat.sensitive
     FROM complaints c
     JOIN categories cat ON cat.id = c.category_id
     WHERE c.id = ?'
);
$stmt->execute([$complaintId]);
$complaint = $stmt->fetch();

if (!$complaint) {
    deny_complaint_not_found(true);
}

if (!can_view_complaint($user, $complaint) || $complaint['visibility'] !== 'public') {
    deny_complaint_not_found(true);
}

// Security Rule: Cannot vote on your own complaint
if ((int) $complaint['student_id'] === $userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You cannot mark yourself as affected on your own complaint.']);
    exit;
}

// Check if already affected
$stmt = $pdo->prepare('SELECT id FROM affected WHERE complaint_id = ? AND user_id = ?');
$stmt->execute([$complaintId, $userId]);
$existing = $stmt->fetch();

if ($existing) {
    // Toggle off: remove row
    $del = $pdo->prepare('DELETE FROM affected WHERE id = ?');
    $del->execute([$existing['id']]);
    $affected = false;
    $msg = 'Removed from affected count.';
} else {
    // Toggle on: insert row
    $ins = $pdo->prepare('INSERT INTO affected (complaint_id, user_id, created_at) VALUES (?, ?, NOW())');
    $ins->execute([$complaintId, $userId]);
    $affected = true;
    $msg = 'Marked as also affected by this issue.';
}

// Get updated count
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM affected WHERE complaint_id = ?');
$countStmt->execute([$complaintId]);
$newCount = (int) $countStmt->fetchColumn();

echo json_encode([
    'success'  => true,
    'affected' => $affected,
    'count'    => $newCount,
    'message'  => $msg,
]);
exit;
