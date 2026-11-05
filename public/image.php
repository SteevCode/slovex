<?php
/**
 * Solvex Report — Secure Image Proxy Endpoint
 * 
 * Serves complaint attachments only after verifying can_view_complaint().
 * Prevents direct file exposure and sends strict security headers.
 * Supports on-demand thumbnail generation (&thumb=1).
 */

define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';
require_once SOLVEX_ROOT . '/includes/upload.php';

init_session();
$user = current_user();

$imageId = (int) get('id', '0');
$isThumb = (get('thumb', '0') === '1');

if ($imageId <= 0) {
    http_response_code(404);
    include SOLVEX_ROOT . '/public/errors/404.php';
    exit;
}

$pdo = db();

// Fetch image joined with complaint and category metadata for authorization
$stmt = $pdo->prepare(
    'SELECT ci.id, ci.complaint_id, ci.stored_name, ci.mime, ci.size,
            c.student_id, c.visibility, c.department_id, c.status,
            cat.sensitive
     FROM complaint_images ci
     JOIN complaints c ON c.id = ci.complaint_id
     JOIN categories cat ON cat.id = c.category_id
     WHERE ci.id = ?'
);
$stmt->execute([$imageId]);
$record = $stmt->fetch();

if (!$record) {
    http_response_code(404);
    include SOLVEX_ROOT . '/public/errors/404.php';
    exit;
}

// Enforce permission: returns 404 if user cannot view the complaint
require_can_view_complaint($user, $record);

// File location check
$filePath = UPLOAD_PATH . '/' . basename($record['stored_name']);
if (!file_exists($filePath)) {
    http_response_code(404);
    include SOLVEX_ROOT . '/public/errors/404.php';
    exit;
}

// Thumbnail request
if ($isThumb) {
    $thumbData = create_thumbnail($filePath, 360, 270);
    if ($thumbData !== false) {
        header('Content-Type: ' . $record['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        header('Content-Length: ' . strlen($thumbData));
        echo $thumbData;
        exit;
    }
}

// Full size image
header('Content-Type: ' . $record['mime']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
