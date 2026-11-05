<?php
/**
 * Solvex Report — Create Admin User (CLI Script)
 * 
 * Usage:
 *   Interactive:
 *     php scripts/create_admin.php
 *   With arguments:
 *     php scripts/create_admin.php --name="Admin User" --email="admin@solvex.edu" --code="ADM001" --password="Password123"
 */

// Must run from CLI
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

// Bootstrap
define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';

echo "╔══════════════════════════════════════════════╗\n";
echo "║     Solvex Report — Create Admin User        ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

// Helper for interactive reading from stdin
function read_cli_input(string $prompt): string {
    echo $prompt;
    if (function_exists('readline') && stream_isatty(STDIN)) {
        return trim((string) readline(''));
    }
    $line = fgets(STDIN);
    return $line !== false ? trim($line) : '';
}

// Parse command line options if provided
$opts = getopt('', ['name::', 'email::', 'code::', 'password::']);

$fullName  = $opts['name'] ?? null;
$email     = $opts['email'] ?? null;
$staffCode = $opts['code'] ?? null;
$password  = $opts['password'] ?? null;

// Prompt interactively if not supplied via options
if (empty($fullName)) {
    $fullName = read_cli_input('Full Name: ');
}
if (empty(trim((string)$fullName))) {
    die("Error: Full name is required.\n");
}

if (empty($email)) {
    $email = read_cli_input('Email: ');
}
if (empty(trim((string)$email)) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Error: A valid email is required.\n");
}

if (empty($staffCode)) {
    $staffCode = read_cli_input('Staff Code (e.g., ADM001): ');
}
if (empty(trim((string)$staffCode))) {
    die("Error: Staff code is required.\n");
}

if (empty($password)) {
    $password = read_cli_input('Password (min 8 chars): ');
    $confirm  = read_cli_input('Confirm Password: ');
    if ($password !== $confirm) {
        die("Error: Passwords do not match.\n");
    }
}

if (strlen((string)$password) < 8) {
    die("Error: Password must be at least 8 characters.\n");
}

// Check for existing admin with same email or staff code
$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR staff_code = ?');
$stmt->execute([trim($email), trim($staffCode)]);
if ($stmt->fetch()) {
    die("Error: A user with this email or staff code already exists.\n");
}

// Create the admin
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO users (staff_code, full_name, email, password_hash, role, status, email_verified_at, created_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
);
$stmt->execute([
    trim($staffCode),
    trim($fullName),
    trim($email),
    $passwordHash,
    'admin',
    'active',
]);

$adminId = $pdo->lastInsertId();

echo "\n✅ Admin user created successfully!\n";
echo "   ID:         {$adminId}\n";
echo "   Name:       " . trim($fullName) . "\n";
echo "   Email:      " . trim($email) . "\n";
echo "   Staff Code: " . trim($staffCode) . "\n";
echo "   Role:       admin\n\n";
echo "You can now log in at: " . base_url('login.php') . "\n";
