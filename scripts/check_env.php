<?php
/**
 * Solvex Report — Environment & Database Health Check
 * 
 * Verifies MySQL connection, initializes schema and seeds if needed.
 * Returns:
 *   Exit code 0: Ready to launch server
 *   Exit code 1: Fatal configuration / environment error
 *   Exit code 2: MySQL daemon not running
 */

define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';

// 1. Check directories
$dirs = [
    SOLVEX_ROOT . '/storage/logs',
    SOLVEX_ROOT . '/storage/uploads/complaints'
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// 2. Test MySQL Connection
try {
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "❌ Cannot connect to MySQL on " . DB_HOST . ":" . DB_PORT . "\n");
    fwrite(STDERR, "   " . $e->getMessage() . "\n");
    exit(2);
}

// 3. Ensure database exists
$dbName = DB_NAME;
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$dbName}`");

// 4. Check if tables exist
$stmt = $pdo->query("SHOW TABLES LIKE 'complaints'");
$hasComplaintsTable = (bool) $stmt->fetch();

if (!$hasComplaintsTable) {
    echo "⚙️ Initializing database schema...\n";
    $schemaSql = file_get_contents(SOLVEX_ROOT . '/database/schema.sql');
    $pdo->exec($schemaSql);

    if (file_exists(SOLVEX_ROOT . '/database/migrate_phase3.sql')) {
        $migrateSql = file_get_contents(SOLVEX_ROOT . '/database/migrate_phase3.sql');
        $pdo->exec($migrateSql);
    }
    echo "✅ Schema initialized.\n";
}

// 5. Check if seeded
$cCount = (int) $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
if ($cCount === 0) {
    echo "🌱 Seeding demo dataset for live presentation...\n";
    require_once SOLVEX_ROOT . '/database/demo_seed.php';
}

echo "✅ Database and storage environment ready (" . $cCount . " complaints in database).\n";
exit(0);

