<?php
/**
 * Solvex Report — Portable Environment Auto-Setup & Health Verifier
 * 
 * Performs 100% automated dependency detection, environment validation,
 * database setup, schema migration, and demo asset synthesis.
 * 
 * Exit codes:
 *   0: All dependencies satisfied & ready to run
 *   1: Critical missing dependency that requires manual resolution
 *   2: MySQL is offline (launcher should attempt starting daemon)
 */

define('SOLVEX_ROOT', dirname(__DIR__));

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "   SOLVEX REPORT — AUTOMATIC PORTABLE SETUP & DEPENDENCY CHECK        \n";
echo "═══════════════════════════════════════════════════════════════════════\n";

// ── 1. PHP Version Verification ─────────────────────────────────
$phpVersion = PHP_VERSION;
echo "[1/6] Checking PHP Runtime (Current: {$phpVersion})... ";
if (version_compare($phpVersion, '8.1.0', '<')) {
    echo "❌ INCOMPATIBLE\n";
    fwrite(STDERR, "Error: Solvex Report requires PHP 8.1.0 or higher (PHP 8.2+ recommended).\n");
    exit(1);
}
echo "✅ OK\n";

// ── 2. PHP Extension Verification ──────────────────────────────
echo "[2/6] Verifying Required PHP Extensions...\n";
$requiredExts = [
    'pdo'       => 'PDO Core Database Layer',
    'pdo_mysql' => 'MySQL/MariaDB PDO Driver',
    'gd'        => 'GD Image Processing Library (EXIF stripping, WebP)',
    'fileinfo'  => 'MIME Type Magic Byte Detection',
    'mbstring'  => 'Multibyte String Operations',
    'json'      => 'JSON Serialization',
];

$missingExts = [];
foreach ($requiredExts as $ext => $label) {
    if (!extension_loaded($ext)) {
        $missingExts[] = [$ext, $label];
        echo "      ❌ Missing: ext-{$ext} ({$label})\n";
    } else {
        echo "      ✅ ext-{$ext} ({$label})\n";
    }
}

if (!empty($missingExts)) {
    echo "\n⚠️  Some required PHP extensions are not loaded.\n";
    $iniPath = php_ini_loaded_file();
    if ($iniPath) {
        echo "   Configuration file: {$iniPath}\n";
        echo "   To fix, open php.ini and uncomment the following lines:\n";
        foreach ($missingExts as [$ext, $lbl]) {
            echo "     extension={$ext}\n";
        }
    }
    exit(1);
}

// ── 3. Configuration & Directory Initialization ────────────────
echo "[3/6] Initializing Storage Directories & Configuration... ";
$dirs = [
    SOLVEX_ROOT . '/storage/logs',
    SOLVEX_ROOT . '/storage/uploads/complaints',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            echo "❌ FAILED\n";
            fwrite(STDERR, "Error: Failed to create storage directory: {$dir}\n");
            exit(1);
        }
    }
}

$localConfig = SOLVEX_ROOT . '/config.local.php';
if (!file_exists($localConfig)) {
    $exampleConfig = SOLVEX_ROOT . '/config.local.example.php';
    if (file_exists($exampleConfig)) {
        copy($exampleConfig, $localConfig);
        echo "(auto-generated config.local.php) ";
    }
}
require_once SOLVEX_ROOT . '/includes/config.php';
echo "✅ OK\n";

// ── 4. Verify Static Image Assets ──────────────────────────────
echo "[4/6] Verifying WebP Vector Assets & Seed Resources... ";
$requiredAssets = [
    SOLVEX_ROOT . '/public/assets/img/hero.webp',
    SOLVEX_ROOT . '/public/assets/img/auth-side.webp',
    SOLVEX_ROOT . '/public/assets/img/empty-no-complaints.webp',
    SOLVEX_ROOT . '/public/assets/img/how-report.webp',
];

$needImageBuild = false;
foreach ($requiredAssets as $assetPath) {
    if (!file_exists($assetPath)) {
        $needImageBuild = true;
        break;
    }
}

if ($needImageBuild && file_exists(SOLVEX_ROOT . '/scripts/build_images.php')) {
    echo "Synthesizing vector assets... ";
    ob_start();
    include SOLVEX_ROOT . '/scripts/build_images.php';
    ob_end_clean();
}
echo "✅ OK\n";

// ── 5. Test MySQL Connectivity ─────────────────────────────────
echo "[5/6] Connecting to Database (" . DB_HOST . ":" . DB_PORT . ")... ";
try {
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
} catch (PDOException $e) {
    echo "❌ OFFLINE\n";
    echo "      Database connection could not be established.\n";
    echo "      Detail: " . $e->getMessage() . "\n";
    exit(2);
}
echo "✅ Connected\n";

// ── 6. Schema Initialization & Seed Verification ───────────────
echo "[6/6] Verifying Database Schema & Seed Data... ";
try {
    $dbName = DB_NAME;
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");

    $hasComplaints = (bool) $pdo->query("SHOW TABLES LIKE 'complaints'")->fetch();

    if (!$hasComplaints) {
        echo "Creating schema tables... ";
        $schemaSql = file_get_contents(SOLVEX_ROOT . '/database/schema.sql');
        $pdo->exec($schemaSql);

        if (file_exists(SOLVEX_ROOT . '/database/migrate_phase3.sql')) {
            $migrateSql = file_get_contents(SOLVEX_ROOT . '/database/migrate_phase3.sql');
            $pdo->exec($migrateSql);
        }
    } else {
        // Ensure Phase 3 tables exist
        $hasSettings = (bool) $pdo->query("SHOW TABLES LIKE 'settings'")->fetch();
        if (!$hasSettings && file_exists(SOLVEX_ROOT . '/database/migrate_phase3.sql')) {
            $migrateSql = file_get_contents(SOLVEX_ROOT . '/database/migrate_phase3.sql');
            $pdo->exec($migrateSql);
        }
    }

    $cCount = (int) $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
    if ($cCount === 0) {
        echo "Seeding demo records... ";
        require_once SOLVEX_ROOT . '/database/demo_seed.php';
        $cCount = (int) $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
    }
} catch (Exception $e) {
    echo "❌ FAILED\n";
    fwrite(STDERR, "Database setup error: " . $e->getMessage() . "\n");
    exit(1);
}

echo "✅ OK ({$cCount} records ready)\n";
echo "───────────────────────────────────────────────────────────────────────\n";
echo "🎉 SYSTEM ENVIRONMENT IS FULLY PREPARED & READY TO LAUNCH.\n";
exit(0);

