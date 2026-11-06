<?php
/**
 * Solvex Report — Master Idempotent Installer (scripts/install.php)
 * 
 * Sets up database schema, migrations, seed data, and initial admin account.
 * 
 * Usage:
 *   php scripts/install.php
 *   php scripts/install.php --demo     (Seeds demo tickets & demo users)
 *   php scripts/install.php --force    (Re-applies even in production)
 */

if (!defined('SOLVEX_ROOT')) {
    define('SOLVEX_ROOT', dirname(__DIR__));
}

if (!file_exists(SOLVEX_ROOT . '/config.local.php')) {
    if (file_exists(SOLVEX_ROOT . '/config.local.example.php')) {
        copy(SOLVEX_ROOT . '/config.local.example.php', SOLVEX_ROOT . '/config.local.php');
        echo "Created config.local.php from template.\n";
    } else {
        fwrite(STDERR, "Error: config.local.example.php not found.\n");
        exit(1);
    }
}

require_once SOLVEX_ROOT . '/includes/config.php';

$isDemo  = in_array('--demo', $argv, true);
$isForce = in_array('--force', $argv, true);

echo "═════════════════════════════════════════════════════════════\n";
echo "   SOLVEX REPORT — MASTER DATABASE INSTALLER & MIGRATOR      \n";
echo "═════════════════════════════════════════════════════════════\n";

// 1. Connect to MySQL Server
$dbHost = DB_HOST;
$dbPort = DB_PORT;
$dbName = (getenv('SOLVEX_ENV') === 'test') ? (defined('DB_NAME_TEST') ? DB_NAME_TEST : 'solvex_test') : DB_NAME;
$dbUser = DB_USER;
$dbPass = DB_PASS;

echo "Connecting to MySQL server at {$dbHost}:{$dbPort}... ";
try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    echo "Connected.\n";
} catch (PDOException $e) {
    echo "FAILED.\n";
    fwrite(STDERR, "Error: Unable to connect to MySQL: " . $e->getMessage() . "\n");
    exit(1);
}

// 2. Ensure Database Exists
echo "Ensuring database `{$dbName}` exists... ";
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$dbName}`");
echo "OK.\n";

// 3. Schema Migrations Table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `migration`  VARCHAR(255) NOT NULL,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Helper function to execute and record SQL file
function run_sql_migration(PDO $pdo, string $name, string $filePath): void {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM `schema_migrations` WHERE `migration` = ?');
    $stmt->execute([$name]);
    if ((int)$stmt->fetchColumn() > 0) {
        echo "   • Migration '{$name}': Already applied.\n";
        return;
    }

    if (!file_exists($filePath)) {
        fwrite(STDERR, "Error: Migration file not found: {$filePath}\n");
        exit(1);
    }

    echo "   • Applying migration '{$name}'... ";
    $sql = file_get_contents($filePath);
    $pdo->exec($sql);

    $ins = $pdo->prepare('INSERT INTO `schema_migrations` (`migration`, `applied_at`) VALUES (?, NOW())');
    $ins->execute([$name]);
    echo "Done.\n";
}

// 4. Apply Core Schema & Base Seeds
echo "Checking database migrations:\n";
run_sql_migration($pdo, '001_schema.sql', SOLVEX_ROOT . '/database/schema.sql');
run_sql_migration($pdo, '002_seed.sql', SOLVEX_ROOT . '/database/seed.sql');

// 5. Ensure System Settings
$pdo->exec("INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('maintenance_last_run', '2000-01-01 00:00:00')");

// 6. Ensure Master Administrator Account Exists
$adminStmt = $pdo->query("SELECT id FROM `users` WHERE `role` = 'admin' AND `status` = 'active' LIMIT 1");
$adminExists = (bool) $adminStmt->fetch();

if (!$adminExists) {
    echo "No active administrator account found. Creating initial admin (admin@solvex.edu)... ";
    $adminPass = password_hash('Admin@123', PASSWORD_DEFAULT);
    $insAdmin = $pdo->prepare("
        INSERT INTO `users` (`staff_code`, `full_name`, `email`, `password_hash`, `role`, `status`, `email_verified_at`, `must_change_password`, `created_at`)
        VALUES ('ADM001', 'System Administrator', 'admin@solvex.edu', ?, 'admin', 'active', NOW(), 0, NOW())
    ");
    $insAdmin->execute([$adminPass]);
    echo "Created.\n";
    echo "   Credentials: admin@solvex.edu / Admin@123\n";
} else {
    echo "Active administrator account verified.\n";
}

// 7. Seed Demo Data if Requested or Local Environment
if ($isDemo || (defined('ENABLE_DEMO_TOOLS') && ENABLE_DEMO_TOOLS && $isDemo)) {
    echo "Running realistic demo seed script (database/demo_seed.php)...\n";
    include SOLVEX_ROOT . '/database/demo_seed.php';
}

echo "\n✅ Installation and migration completed successfully!\n";
exit(0);
