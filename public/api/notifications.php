<?php
/**
 * Solvex Report — Notifications API
 * 
 * Polling endpoint for navbar badge and mark-read interactions.
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';

// Passive session initialization for read-only polling calls (doesn't refresh idle timer)
$isPoll = ($_SERVER['REQUEST_METHOD'] === 'GET');
init_session($isPoll);
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
    exit;
}

$user   = current_user();
$userId = (int) $user['id'];
$pdo    = db();
$action = get('action', post('action', 'count'));

// 1. Get Unread Count & Latest 5 notifications
if ($action === 'count' || $action === 'latest') {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $countStmt->execute([$userId]);
    $unreadCount = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT n.id, n.complaint_id, n.message, n.is_read, n.created_at, c.reference
         FROM notifications n
         LEFT JOIN complaints c ON c.id = n.complaint_id
         WHERE n.user_id = ?
         ORDER BY n.created_at DESC
         LIMIT 5'
    );
    $stmt->execute([$userId]);
    $latest = $stmt->fetchAll();

    $formatted = array_map(function ($row) {
        return [
            'id'           => (int) $row['id'],
            'complaint_id' => $row['complaint_id'] ? (int) $row['complaint_id'] : null,
            'reference'    => $row['reference'] ?? null,
            'message'      => $row['message'],
            'is_read'      => (bool) $row['is_read'],
            'created_at'   => $row['created_at'],
            'url'          => $row['reference'] ? base_url('complaint.php?ref=' . urlencode($row['reference'])) : null,
        ];
    }, $latest);

    echo json_encode([
        'success'       => true,
        'unread_count'  => $unreadCount,
        'notifications' => $formatted,
    ]);
    exit;
}

// 2. Mark Single Notification as Read
if ($action === 'mark_read') {
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

    $notifId = (int) post('id', '0');
    if ($notifId > 0) {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$notifId, $userId]);
    }

    echo json_encode(['success' => true]);
    exit;
}

// 3. Mark All Notifications as Read
if ($action === 'mark_all_read') {
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

    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
exit;
