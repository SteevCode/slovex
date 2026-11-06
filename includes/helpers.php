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

/**
 * Get consistent inline SVG icon for all 10 problem categories.
 * Accepts category ID, category name, or icon class string.
 */
function category_icon_svg(int|string $identifier, int $size = 20, string $class = '', string $color = 'currentColor'): string
{
    $id = is_numeric($identifier) ? (int)$identifier : 0;
    $str = strtolower((string)$identifier);

    // Map by string/icon/name if ID not provided
    if ($id <= 0) {
        if (str_contains($str, 'droplet') || str_contains($str, 'plumb') || str_contains($str, 'water')) {
            $id = 1;
        } elseif (str_contains($str, 'house') || str_contains($str, 'furnit') || str_contains($str, 'civil')) {
            $id = 2;
        } elseif (str_contains($str, 'lightning') || str_contains($str, 'electric')) {
            $id = 3;
        } elseif (str_contains($str, 'wifi') || str_contains($str, 'internet')) {
            $id = 4;
        } elseif (str_contains($str, 'display') || str_contains($str, 'computer') || str_contains($str, 'printer')) {
            $id = 5;
        } elseif (str_contains($str, 'cup') || str_contains($str, 'food')) {
            $id = 6;
        } elseif (str_contains($str, 'exclamation') || str_contains($str, 'hygiene')) {
            $id = 7;
        } elseif (str_contains($str, 'building') || str_contains($str, 'hostel')) {
            $id = 8;
        } elseif (str_contains($str, 'search') || str_contains($str, 'lost')) {
            $id = 9;
        } elseif (str_contains($str, 'shield-lock') || str_contains($str, 'safety') || str_contains($str, 'harass')) {
            $id = 10;
        }
    }

    $classAttr = !empty($class) ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';

    $paths = match ($id) {
        // 1. Plumbing / Water
        1 => '<path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>',
        // 2. Furniture / Civil
        2 => '<path d="M4 18v3M20 18v3M4 11h16M4 14h16M7 11V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v4"/>',
        // 3. Electrical Issue
        3 => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        // 4. Internet / Wi-Fi
        4 => '<path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/>',
        // 5. Computer / Printer
        5 => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
        // 6. Food Quality
        6 => '<path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8zM6 2v3M10 2v3M14 2v3"/>',
        // 7. Mess Hygiene
        7 => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM12 8v4M12 16h.01"/>',
        // 8. Hostel Issue
        8 => '<path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>',
        // 9. Lost & Found
        9 => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        // 10. Safety / Harassment
        10 => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><rect x="9" y="10" width="6" height="5" rx="1"/><path d="M10 10V8a2 2 0 0 1 4 0v2"/>',
        // Default / Generic Tag
        default => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82zM7 7h.01"/>'
    };

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $classAttr . ' aria-hidden="true">' . $paths . '</svg>';
}
