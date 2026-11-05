<?php
/**
 * Solvex Report — Locations API Endpoint
 * 
 * Returns child locations for cascading dropdowns or complete hierarchical tree.
 * Usage:
 *   GET /api/locations.php                -> Top-level locations (parent_id IS NULL)
 *   GET /api/locations.php?parent_id=2   -> Children of location #2
 *   GET /api/locations.php?all=1         -> All locations indexed by parent_id
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

$pdo = db();

if (get('all') === '1') {
    $stmt = $pdo->query('SELECT id, parent_id, name, type FROM locations ORDER BY parent_id ASC, name ASC');
    $all = $stmt->fetchAll();
    echo json_encode(['success' => true, 'locations' => $all]);
    exit;
}

$parentId = get('parent_id');

if ($parentId === '' || $parentId === null || $parentId === '0') {
    $stmt = $pdo->query('SELECT id, parent_id, name, type FROM locations WHERE parent_id IS NULL ORDER BY name ASC');
} else {
    $stmt = $pdo->prepare('SELECT id, parent_id, name, type FROM locations WHERE parent_id = ? ORDER BY name ASC');
    $stmt->execute([(int) $parentId]);
}

$rows = $stmt->fetchAll();
echo json_encode(['success' => true, 'locations' => $rows]);
exit;
