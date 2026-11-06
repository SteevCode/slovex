<?php
/**
 * Solvex Report — Database Privacy & Anonymization Utility (scripts/anonymize_data.php)
 * 
 * Anonymizes old disabled user records and scrubs personal data in compliance
 * with campus privacy and DPDP/GDPR principles.
 * 
 * Usage:
 *   php scripts/anonymize_data.php --dry-run
 *   php scripts/anonymize_data.php --days=365
 */

define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';

$isDryRun = in_array('--dry-run', $argv, true);
$days = 365;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--days=')) {
        $days = max(30, (int)substr($arg, 7));
    }
}

echo "═════════════════════════════════════════════════════════════\n";
echo "   SOLVEX REPORT — PRIVACY & ANONYMIZATION UTILITY           \n";
echo "═════════════════════════════════════════════════════════════\n";
echo "Cutoff period: {$days} days | Mode: " . ($isDryRun ? "DRY-RUN (No changes applied)" : "LIVE EXECUTION") . "\n\n";

$pdo = db();
$cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

// Find eligible disabled accounts
$stmt = $pdo->prepare("
    SELECT id, register_no, staff_code, full_name, email, created_at
    FROM users
    WHERE status = 'disabled' AND created_at < ?
");
$stmt->execute([$cutoff]);
$candidates = $stmt->fetchAll();

$count = count($candidates);
echo "Found {$count} candidate disabled account(s) older than {$days} days.\n";

if ($count === 0) {
    echo "Zero accounts qualify for anonymization. Database is clean.\n";
    exit(0);
}

foreach ($candidates as $u) {
    $maskedId = "anon_" . substr(hash('sha256', (string)$u['id']), 0, 8);
    $anonName = "Anonymized User {$u['id']}";
    $anonEmail = "{$maskedId}@anonymized.local";

    echo " • User #{$u['id']} ({$u['full_name']} / {$u['email']}) -> {$anonEmail}\n";

    if (!$isDryRun) {
        $upd = $pdo->prepare("
            UPDATE users
            SET full_name = ?,
                email = ?,
                register_no = NULL,
                staff_code = NULL,
                password_hash = 'DISABLED_ANONYMIZED'
            WHERE id = ?
        ");
        $upd->execute([$anonName, $anonEmail, $u['id']]);

        // Record in admin_log
        $logStmt = $pdo->prepare("
            INSERT INTO admin_log (actor_id, action, target, detail, created_at)
            VALUES (NULL, 'privacy_anonymize', ?, ?, NOW())
        ");
        $logStmt->execute(["user:{$u['id']}", "Anonymized user account under privacy policy ({$days} days)"]);
    }
}

if ($isDryRun) {
    echo "\nDry-run complete. Re-run without --dry-run to apply changes.\n";
} else {
    echo "\n✅ Successfully anonymized {$count} user account(s).\n";
}
exit(0);

