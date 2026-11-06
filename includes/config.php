<?php
/**
 * Solvex Report — Global Configuration
 * 
 * Loads config.local.php for credentials, sets timezone,
 * error handling, and application constants.
 */

// Prevent direct access
if (!defined('SOLVEX_ROOT')) {
    define('SOLVEX_ROOT', dirname(__DIR__));
}

// Load local config (credentials, environment)
$localConfig = SOLVEX_ROOT . '/config.local.php';
if (!file_exists($localConfig)) {
    http_response_code(500);
    die('Configuration file config.local.php is missing. Please copy config.local.example.php to config.local.php and configure your environment before running Solvex Report.');
}
require_once $localConfig;

// ── Timezone ───────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ── Error Handling ─────────────────────────────────────────
$logDir = SOLVEX_ROOT . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

if (APP_ENV === 'local') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/php_error.log');

// Application Constants
define('APP_NAME', 'Solvex Report');
define('APP_VERSION', '1.0.0');
define('EMERGENCY_CONTACT_PHONE', getenv('EMERGENCY_PHONE') ?: '+91 44 2257 8888');

// Session idle timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Auth token expiry
define('VERIFY_TOKEN_EXPIRY', 86400);  // 24 hours
define('RESET_TOKEN_EXPIRY', 1800);    // 30 minutes

// Login throttling
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// Paths
define('STORAGE_PATH', SOLVEX_ROOT . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
define('LOG_PATH', STORAGE_PATH . '/logs');
define('INCLUDES_PATH', SOLVEX_ROOT . '/includes');

// Ensure storage dirs exist
foreach ([UPLOAD_PATH, LOG_PATH] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Session GC alignment
if (ini_get('session.gc_maxlifetime') < SESSION_TIMEOUT) {
    @ini_set('session.gc_maxlifetime', (string)SESSION_TIMEOUT);
}

// ── Security Headers (emitted for all non-CLI responses) ──────
if (!headers_sent() && PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ── Global Exception Handler ──────────────────────────────────
set_exception_handler(function (Throwable $e) {
    error_log('Uncaught Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (defined('APP_ENV') && APP_ENV === 'local') {
        echo '<div style="background:#fee2e2;border:1px solid #ef4444;color:#991b1b;padding:15px;margin:20px;border-radius:6px;font-family:monospace;">';
        echo '<strong>[Solvex Dev Error]</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '<br><br>';
        echo '<strong>File:</strong> ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ':' . $e->getLine() . '<br>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
        echo '</div>';
    } else {
        $errorPage = SOLVEX_ROOT . '/public/errors/500.php';
        if (file_exists($errorPage)) {
            include $errorPage;
        } else {
            echo 'An internal error occurred. Please try again later.';
        }
    }
    exit(1);
});
