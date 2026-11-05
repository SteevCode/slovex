<?php
/**
 * Solvex Report — Permission Engine
 * 
 * Central authorization authority for complaints, visibility,
 * reporter anonymity, and data sanitization.
 */

if (!defined('SOLVEX_ROOT')) {
    define('SOLVEX_ROOT', dirname(__DIR__));
}

require_once SOLVEX_ROOT . '/includes/helpers.php';

/**
 * Determine whether a user can view a given complaint.
 * 
 * The ONLY place complaint visibility is decided:
 * - Public: any logged-in user.
 * - Private: owner; staff whose department_id matches the complaint's; admin.
 * - Sensitive category (categories.sensitive=1): owner and admin only.
 * 
 * @param array|null $user The currently authenticated user (or null if guest)
 * @param array $complaint The complaint data (must include student_id, visibility, department_id, and category sensitive flag if joined)
 * @return bool True if authorized, false otherwise.
 */
function can_view_complaint(?array $user, array $complaint): bool
{
    if (!$user) {
        return false;
    }

    $userId       = (int) ($user['id'] ?? 0);
    $userRole     = $user['role'] ?? '';
    $userDeptId   = !empty($user['department_id']) ? (int) $user['department_id'] : null;

    $ownerId      = (int) ($complaint['student_id'] ?? 0);
    $compDeptId   = (int) ($complaint['department_id'] ?? 0);
    $visibility   = strtolower((string) ($complaint['visibility'] ?? 'public'));

    // Check if category is sensitive
    // The query joining complaints and categories may call it 'sensitive' or 'cat_sensitive' or 'is_sensitive'
    $isSensitive = false;
    if (isset($complaint['sensitive'])) {
        $isSensitive = (bool) $complaint['sensitive'];
    } elseif (isset($complaint['is_sensitive'])) {
        $isSensitive = (bool) $complaint['is_sensitive'];
    } elseif (!empty($complaint['category_id'])) {
        // Fallback: query category if not pre-joined
        static $catSensitiveCache = [];
        $catId = (int) $complaint['category_id'];
        if (!isset($catSensitiveCache[$catId])) {
            $stmt = db()->prepare('SELECT sensitive FROM categories WHERE id = ?');
            $stmt->execute([$catId]);
            $catSensitiveCache[$catId] = (bool) $stmt->fetchColumn();
        }
        $isSensitive = $catSensitiveCache[$catId];
    }

    // 1. Sensitive Category: ONLY owner and admin can view
    if ($isSensitive) {
        return ($userId === $ownerId || $userRole === 'admin');
    }

    // 2. Private Complaint: owner, admin, or staff of that department
    if ($visibility === 'private') {
        if ($userId === $ownerId || $userRole === 'admin') {
            return true;
        }
        if ($userRole === 'staff' && $userDeptId !== null && $userDeptId === $compDeptId) {
            return true;
        }
        return false;
    }

    // 3. Public Complaint: any logged-in user
    return true;
}

/**
 * Enforce can_view_complaint; if denied, render 404 (never 403) to prevent enumeration.
 */
function require_can_view_complaint(?array $user, array $complaint, bool $asJson = false): void
{
    if (!can_view_complaint($user, $complaint)) {
        deny_complaint_not_found($asJson);
    }
}

/**
 * Return HTTP 404 without revealing whether a complaint exists.
 */
function deny_complaint_not_found(bool $asJson = false): never
{
    http_response_code(404);
    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Not found.']);
        exit;
    }
    include SOLVEX_ROOT . '/public/errors/404.php';
    exit;
}

/**
 * Check if the user is authorized to see reporter identity (name, reg no, email, room, GPS).
 * Allowed: owner, staff of that department, admin.
 * Forbidden: other students, other department staff.
 */
function can_see_reporter(?array $user, array $complaint): bool
{
    if (!$user) {
        return false;
    }

    $userId     = (int) ($user['id'] ?? 0);
    $userRole   = $user['role'] ?? '';
    $userDeptId = !empty($user['department_id']) ? (int) $user['department_id'] : null;

    $ownerId    = (int) ($complaint['student_id'] ?? 0);
    $compDeptId = (int) ($complaint['department_id'] ?? 0);

    if ($userId === $ownerId || $userRole === 'admin') {
        return true;
    }

    if ($userRole === 'staff' && $userDeptId !== null && $userDeptId === $compDeptId) {
        return true;
    }

    return false;
}

/**
 * Check if user can modify complaint details (title, description).
 * Only allowed for the owner while status is 'submitted', or admin.
 */
function can_modify_complaint(?array $user, array $complaint): bool
{
    if (!$user) {
        return false;
    }

    $userId   = (int) ($user['id'] ?? 0);
    $userRole = $user['role'] ?? '';
    $ownerId  = (int) ($complaint['student_id'] ?? 0);
    $status   = $complaint['status'] ?? '';

    if ($userRole === 'admin') {
        return true;
    }

    if ($userId === $ownerId && $status === 'submitted') {
        return true;
    }

    return false;
}

/**
 * Check if user can post a public comment on a complaint.
 */
function can_comment(?array $user, array $complaint): bool
{
    if (!can_view_complaint($user, $complaint)) {
        return false;
    }

    // Complaints that are closed or rejected might still allow viewing history,
    // but commenting is open for active or resolved tickets
    $status = $complaint['status'] ?? '';
    if (in_array($status, ['rejected'], true)) {
        return false;
    }

    return true;
}

/**
 * Check if user can view internal staff comments.
 * Allowed: staff of the assigned department or admin only. Students never see internal notes.
 */
function can_view_internal_notes(?array $user, array $complaint): bool
{
    if (!$user) {
        return false;
    }

    if ($user['role'] === 'admin') {
        return true;
    }

    $userDeptId = !empty($user['department_id']) ? (int) $user['department_id'] : null;
    $compDeptId = (int) ($complaint['department_id'] ?? 0);

    if ($user['role'] === 'staff' && $userDeptId !== null && $userDeptId === $compDeptId) {
        return true;
    }

    return false;
}

/**
 * View-model sanitizer: strips reporter name, register number, email, room, and GPS
 * for anyone not permitted, replacing reporter name with "Anonymous student".
 * 
 * @param array $complaint Raw complaint record from DB
 * @param array|null $user Current authenticated user
 * @return array Sanitized complaint record
 */
function sanitize_complaint_for_view(array $complaint, ?array $user): array
{
    $sanitized = $complaint;

    // Check if user is permitted to see reporter identity
    if (!can_see_reporter($user, $complaint)) {
        // Strip reporter identification
        $sanitized['full_name']           = 'Anonymous student';
        $sanitized['reporter_name']       = 'Anonymous student';
        $sanitized['student_name']        = 'Anonymous student';
        $sanitized['register_no']         = null;
        $sanitized['student_register_no'] = null;
        $sanitized['email']               = null;
        $sanitized['student_email']       = null;

        // Strip GPS coordinates and accuracy
        $sanitized['lat']                 = null;
        $sanitized['lng']                 = null;
        $sanitized['gps_accuracy_m']      = null;

        // Strip room details if present in location title
        if (isset($sanitized['room_no'])) {
            $sanitized['room_no'] = null;
        }
    }

    return $sanitized;
}

/**
 * Generic authorization helper.
 */
function can(string $action, string $resource = '', ?int $resourceId = null): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    if ($user['role'] === 'admin') {
        return true;
    }

    return true;
}
