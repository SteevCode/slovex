<?php
/**
 * Solvex Report — Local Configuration (git-ignored)
 * Copy config.local.example.php to config.local.php and fill in your values.
 */

// Environment: 'local' or 'production'
define('APP_ENV', 'local');

// Base URL (no trailing slash)
define('BASE_URL', 'http://localhost/solvex');

// Database
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'solvex_report');
define('DB_USER', 'root');
define('DB_PASS', '');

// SMTP / Mail
// Mode: 'smtp' or 'log'
define('MAIL_MODE', 'log');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM_EMAIL', 'noreply@solvexreport.com');
define('SMTP_FROM_NAME', 'Solvex Report');
