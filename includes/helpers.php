<?php
/**
 * Solvex Report — Helper Functions
 * 
 * Utility functions used across the application.
 */

/**
 * Escape output for HTML to prevent XSS.
 * Use this for ALL user-generated content displayed in HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Get the base URL for the application.
 */
function base_url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Get the URL for a public asset.
 */
function asset_url(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

/**
 * Redirect to a URL and exit.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Set a flash message in the session.
 */
function flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash messages.
 * @return array<array{type: string, message: string}>
 */
function get_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Check if the current request is POST.
 */
function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Get a POST value, trimmed. Returns default if not set.
 */
function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

/**
 * Get a GET value, trimmed.
 */
function get(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

/**
 * Generate a cryptographically secure random token (hex).
 */
function generate_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Hash a token for storage (SHA-256).
 */
function hash_token(string $token): string
{
    return hash('sha256', $token);
}

/**
 * Format a datetime string for display.
 */
function format_date(string $datetime, string $format = 'd M Y, h:i A'): string
{
    $dt = new DateTime($datetime, new DateTimeZone('Asia/Kolkata'));
    return $dt->format($format);
}

/**
 * Calculate the number of days between a date and now.
 */
function days_since(string $datetime): int
{
    $then = new DateTime($datetime, new DateTimeZone('Asia/Kolkata'));
    $now  = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    return max(0, $now->diff($then)->days);
}

/**
 * Get the user's IP address.
 */
function get_ip(): string
{
    // On shared hosting behind a proxy, check forwarded headers
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Generate initials from a full name (for avatar).
 */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
    return strtoupper(mb_substr($name, 0, 2));
}

/**
 * Generate a consistent color from a string (for avatar backgrounds).
 */
function avatar_color(string $name): string
{
    $colors = [
        '#0E9F8E', '#0B2545', '#F59E0B', '#6366F1',
        '#EC4899', '#14B8A6', '#8B5CF6', '#F97316',
        '#06B6D4', '#84CC16',
    ];
    $index = crc32($name) % count($colors);
    return $colors[abs($index)];
}

/**
 * Truncate a string to a given length.
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}
