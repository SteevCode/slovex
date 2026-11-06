<?php
/**
 * Solvex Report — Local Configuration Template
 * 
 * Instructions:
 * Copy this file to config.local.php and configure for your environment.
 * In Docker/CI environments, values can be supplied via environment variables.
 */

// Environment: 'local' or 'production' (Production is secure default)
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Demo tools (quick login helpers, ?demo_as= parameter, debug token views).
// ONLY honored when APP_ENV === 'local'. Ignored in production.
define('ENABLE_DEMO_TOOLS', filter_var(getenv('ENABLE_DEMO_TOOLS') ?: 'false', FILTER_VALIDATE_BOOLEAN));

// Trusted Proxies (comma-separated IP addresses that are allowed to set X-Forwarded-For)
define('TRUSTED_PROXIES', getenv('TRUSTED_PROXIES') ?: '');

// Base URL (smart detection for both php -S and XAMPP/Apache subdirectories)
if (!empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $subDir = '';
    if ($scriptDir && $scriptDir !== '.' && $scriptDir !== 'public') {
        $parts = explode('/', $scriptDir);
        $subDir = '/' . $parts[0];
    }
    define('BASE_URL', getenv('BASE_URL') ?: ($scheme . $host . $subDir));
} else {
    define('BASE_URL', getenv('BASE_URL') ?: 'http://127.0.0.1:8000');
}

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'solvex_report');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// Test Database Override
define('DB_NAME_TEST', getenv('DB_NAME_TEST') ?: 'solvex_test');

// SMTP / Mail Configuration
// Mode: 'smtp' or 'log' (log mode writes outgoing emails to storage/logs/mail.log)
define('MAIL_MODE', getenv('MAIL_MODE') ?: 'log');
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'noreply@solvexreport.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Solvex Report');
