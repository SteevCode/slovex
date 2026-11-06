<?php
/**
 * Solvex Report — Development Router for PHP Built-In Web Server
 * 
 * Usage:
 *   php -S 127.0.0.1:8000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$publicDir = str_ends_with(__DIR__, 'public') ? __DIR__ : __DIR__ . '/public';

// Normalize URI
$path = $publicDir . $uri;

// 1. Root directory -> public/index.php
if ($uri === '/' || $uri === '') {
    require $publicDir . '/index.php';
    return;
}

// 2. If it's a directory, check for directory index.php
if (is_dir($path)) {
    $index = rtrim($path, '/') . '/index.php';
    if (file_exists($index)) {
        require $index;
        return;
    }
}

// 3. If file exists in public/
if (file_exists($path) && is_file($path)) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    // If it's a PHP file, execute it
    if ($ext === 'php') {
        require $path;
        return;
    }

    // Static asset MIME mapping
    $mimes = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'application/javascript; charset=UTF-8',
        'json'  => 'application/json; charset=UTF-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'map'   => 'application/json',
    ];

    $contentType = $mimes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($path));
    readfile($path);
    return;
}

// 4. Try appending .php if someone requests clean URLs like /feed or /login
if (file_exists($path . '.php') && is_file($path . '.php')) {
    require $path . '.php';
    return;
}

// 5. Fallback: custom 404 error page
http_response_code(404);
if (file_exists($publicDir . '/errors/404.php')) {
    require $publicDir . '/errors/404.php';
} else {
    echo '404 Not Found';
}
