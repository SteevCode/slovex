<?php
/**
 * Solvex Report — Similar Complaints Endpoint
 * 
 * Searches for open public complaints matching the same category & location
 * filed within the last 14 days to prevent duplicate reports.
 */

define('SOLVEX_ROOT', dirname(__DIR__, 2));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';

init_session();
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$categoryId = (int) get('category_id', '0');
$locationId = (int) get('location_id', '0');

if ($categoryId <= 0) {
    echo json_encode(['success' => true, 'complaints' => []]);
    exit;
}

$pdo = db();

// Open public complaints in the last 14 days
$sql = '
    SELECT c.id, c.reference, c.title, c.status, c.created_at,
           (SELECT COUNT(*) FROM affected a WHERE a.complaint_id = c.id) AS affected_count,
           cat.name AS category_name,
           loc.name AS location_name
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    LEFT JOIN locations loc ON loc.id = c.location_id
    WHERE c.visibility = \'public\'
      AND cat.sensitive = 0
      AND c.status IN (\'submitted\', \'assigned\', \'in_progress\', \'on_hold\', \'reopened\')
      AND c.category_id = :cat_id
      AND c.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
';

$params = ['cat_id' => $categoryId];

if ($locationId > 0) {
    $sql .= ' AND c.location_id = :loc_id';
    $params['loc_id'] = $locationId;
}

$sql .= ' ORDER BY c.created_at DESC LIMIT 3';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$formatted = array_map(function ($item) {
    return [
        'id'             => (int) $item['id'],
        'reference'      => $item['reference'],
        'title'          => $item['title'],
        'status'         => $item['status'],
        'category_name'  => $item['category_name'],
        'location_name'  => $item['location_name'] ?? 'General Campus',
        'days_open'      => days_since($item['created_at']),
        'affected_count' => (int) $item['affected_count'],
        'view_url'       => base_url('complaint.php?ref=' . urlencode($item['reference'])),
    ];
}, $rows);

echo json_encode([
    'success'    => true,
    'count'      => count($formatted),
    'complaints' => $formatted,
]);
exit;
