<?php
/**
 * Solvex Report — Development Router for PHP Built-In Web Server
 * 
 * Usage:
 *   php -S 127.0.0.1:8000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$publicDir = str_ends_with(__DIR__, 'public') ? __DIR__ : __DIR__ . '/public';
$path = $publicDir . $uri;

// 1. If physical static file or directory with index.php exists, serve it
if ($uri !== '/' && file_exists($path)) {
    if (is_dir($path)) {
        $index = rtrim($path, '/') . '/index.php';
        if (file_exists($index)) {
            require $index;
            return;
        }
    } else {
        return false; // Built-in server serves static file directly
    }
}

// 2. Root directory -> index.php
if ($uri === '/' || $uri === '') {
    require $publicDir . '/index.php';
    return;
}

// 3. Fallback: custom 404 error page
http_response_code(404);
require $publicDir . '/errors/404.php';

