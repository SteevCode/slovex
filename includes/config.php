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
    $exampleConfig = SOLVEX_ROOT . '/config.local.example.php';
    if (file_exists($exampleConfig)) {
        copy($exampleConfig, $localConfig);
    } else {
        die('Configuration file missing and config.local.example.php not found.');
    }
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

// ── Application Constants ──────────────────────────────────
define('APP_NAME', 'Solvex Report');
define('APP_VERSION', '1.0.0');

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
