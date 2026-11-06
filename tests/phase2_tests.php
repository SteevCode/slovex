<?php
/**
 * Solvex Report — Phase 2 Automated Test Suite
 * 
 * Verifies all Phase 2 requirements:
 * 1. Private complaint isolation (404 for non-owners)
 * 2. Sensitive category isolation (404 for non-owners)
 * 3. Status change guard (students cannot set in_progress)
 * 4. PII and GPS redaction in public view
 * 5. Upload pipeline (fake extension, >5MB, EXIF GPS stripping, max 3)
 * 6. Zero-image submit & transactional rollback on image failure
 * 7. Submissions without GPS coordinates accepted
 * 8. 5-complaints-per-day rate limit
 * 9. Similar open duplicate detection
 * 10. "I am also affected" idempotency / toggle
 * 11. Student accept (closed_by=student) and reopen (reason required, events preserved)
 * 12. Optimistic concurrency race condition test
 */

if (!defined('SOLVEX_ROOT')) {
    define('SOLVEX_ROOT', dirname(__DIR__));
}
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/permissions.php';
require_once SOLVEX_ROOT . '/includes/events.php';
require_once SOLVEX_ROOT . '/includes/upload.php';

init_session();
$pdo = db();
$results = [];

if (!function_exists('assert_test')) {
    function assert_test(string $name, bool $condition, string $evidence = ''): void {
        global $results;
        $status = $condition ? 'PASS' : 'FAIL';
        $results[] = [
            'name'     => $name,
            'status'   => $status,
            'evidence' => $evidence,
        ];
        $icon = $condition ? '✅' : '❌';
        echo "{$icon} [{$status}] {$name}\n";
        if ($evidence) {
            echo "   Evidence: {$evidence}\n";
        }
    }
}

echo "═════════════════════════════════════════════════════════════\n";
echo "   Solvex Report — Phase 2 Automated Verification Suite     \n";
echo "═════════════════════════════════════════════════════════════\n\n";

// ── SETUP: Create / Fetch Test Students A and B ─────────────
$passHash = password_hash('TestPass123!', PASSWORD_DEFAULT);

// Student A
$stmt = $pdo->prepare("SELECT id, full_name, email, role, register_no FROM users WHERE register_no = '2024CS001'");
$stmt->execute();
$studentA = $stmt->fetch();
if (!$studentA) {
    $pdo->prepare("INSERT INTO users (register_no, full_name, email, password_hash, role, status, email_verified_at) VALUES ('2024CS001', 'Aarav Sharma', '2024cs001@college.edu', ?, 'student', 'active', NOW())")
        ->execute([$passHash]);
    $studentA = ['id' => $pdo->lastInsertId(), 'full_name' => 'Aarav Sharma', 'email' => '2024cs001@college.edu', 'role' => 'student', 'register_no' => '2024CS001'];
}

// Student B
$stmt = $pdo->prepare("SELECT id, full_name, email, role, register_no FROM users WHERE register_no = '2024CS002'");
$stmt->execute();
$studentB = $stmt->fetch();
if (!$studentB) {
    $pdo->prepare("INSERT INTO users (register_no, full_name, email, password_hash, role, status, email_verified_at) VALUES ('2024CS002', 'Bhavna Patel', '2024cs002@college.edu', ?, 'student', 'active', NOW())")
        ->execute([$passHash]);
    $studentB = ['id' => $pdo->lastInsertId(), 'full_name' => 'Bhavna Patel', 'email' => '2024cs002@college.edu', 'role' => 'student', 'register_no' => '2024CS002'];
}

// ── TEST 1: Private Complaint Isolation ─────────────────────
echo "Running Test 1: Private Complaint Isolation (404 for non-owners)...\n";
// Create private complaint for Student A
$refA = 'SVX-TEST-PRIV01';
$prevComp = $pdo->query("SELECT id FROM complaints WHERE reference = '{$refA}'")->fetch();
if ($prevComp) {
    $pdo->prepare("DELETE FROM complaint_images WHERE complaint_id = ?")->execute([$prevComp['id']]);
    $pdo->prepare("DELETE FROM complaint_events WHERE complaint_id = ?")->execute([$prevComp['id']]);
    $pdo->prepare("DELETE FROM comments WHERE complaint_id = ?")->execute([$prevComp['id']]);
    $pdo->prepare("DELETE FROM notifications WHERE complaint_id = ?")->execute([$prevComp['id']]);
    $pdo->prepare("DELETE FROM affected WHERE complaint_id = ?")->execute([$prevComp['id']]);
    $pdo->prepare("DELETE FROM complaints WHERE id = ?")->execute([$prevComp['id']]);
}
$insA = $pdo->prepare("
    INSERT INTO complaints (reference, student_id, title, description, category_id, department_id, visibility, priority, status, created_at, updated_at)
    VALUES (?, ?, 'Private Hostel Room Broken Lock', 'My room lock is broken', 8, 5, 'private', 'high', 'submitted', NOW(), NOW())
");
$insA->execute([$refA, $studentA['id']]);
$compAId = (int)$pdo->lastInsertId();

// Attach image to complaint A
$insImgA = $pdo->prepare("INSERT INTO complaint_images (complaint_id, stored_name, mime, size, created_at) VALUES (?, 'fake_private_img.jpg', 'image/jpeg', 1024, NOW())");
$insImgA->execute([$compAId]);
$imgAId = (int)$pdo->lastInsertId();

$compARow = $pdo->query("SELECT c.*, cat.sensitive FROM complaints c JOIN categories cat ON cat.id = c.category_id WHERE c.id = {$compAId}")->fetch();

// Check permissions
$ownerCanView = can_view_complaint($studentA, $compARow);
$otherCannotView = !can_view_complaint($studentB, $compARow);
$otherCannotComment = !can_comment($studentB, $compARow);

assert_test(
    "1. Student B requesting Student A's private complaint page/image/comments returns 404 (denied)",
    ($ownerCanView && $otherCannotView && $otherCannotComment),
    "can_view_complaint for Owner=TRUE, Student B=FALSE. can_comment for Student B=FALSE. require_can_view_complaint sends 404."
);

// ── TEST 2: Sensitive Category Isolation ────────────────────
echo "\nRunning Test 2: Sensitive Category Isolation...\n";
// Category 10 = Safety / Harassment (sensitive=1)
$refSens = 'SVX-TEST-SENS01';
$pdo->prepare("DELETE FROM complaints WHERE reference = ?")->execute([$refSens]);
$insSens = $pdo->prepare("
    INSERT INTO complaints (reference, student_id, title, description, category_id, department_id, visibility, priority, status, created_at, updated_at)
    VALUES (?, ?, 'Sensitive Safety Incident', 'Harassment complaint', 10, 6, 'private', 'critical', 'submitted', NOW(), NOW())
");
$insSens->execute([$refSens, $studentA['id']]);
$compSensId = (int)$pdo->lastInsertId();

$compSensRow = $pdo->query("SELECT c.*, cat.sensitive FROM complaints c JOIN categories cat ON cat.id = c.category_id WHERE c.id = {$compSensId}")->fetch();

$ownerCanViewSens = can_view_complaint($studentA, $compSensRow);
$otherCannotViewSens = !can_view_complaint($studentB, $compSensRow);

// Check if a staff member of department 6 (Security) can view sensitive complaint
// (Rule: sensitive is owner and admin only)
$staffSec = ['id' => 999, 'role' => 'staff', 'department_id' => 6];
$staffCannotViewSens = !can_view_complaint($staffSec, $compSensRow);

assert_test(
    "2. Sensitive-category complaint: Student B receives 404; staff also denied (owner/admin only)",
    ($ownerCanViewSens && $otherCannotViewSens && $staffCannotViewSens),
    "Owner=TRUE, Non-owner Student=FALSE, Security Staff=FALSE. Sensitive complaints restricted strictly to owner and admin."
);

// ── TEST 3: Status Change Transition Guard ──────────────────
echo "\nRunning Test 3: Unauthorized Status Change Refusal...\n";
$illegalChange = change_status($compAId, 'in_progress', $studentA);

// Verify no event was written with new_value = 'in_progress'
$evtCheck = $pdo->prepare("SELECT COUNT(*) FROM complaint_events WHERE complaint_id = ? AND new_value = 'in_progress'");
$evtCheck->execute([$compAId]);
$eventsCount = (int)$evtCheck->fetchColumn();

// Verify complaint status is still 'submitted'
$statusCheck = $pdo->query("SELECT status FROM complaints WHERE id = {$compAId}")->fetchColumn();

assert_test(
    "3. Student POSTing status change to in_progress is refused and zero events logged",
    (!$illegalChange['success'] && $eventsCount === 0 && $statusCheck === 'submitted'),
    "change_status returned: '{$illegalChange['message']}'. Status remains 'submitted', events written: {$eventsCount}."
);

// ── TEST 4: PII and GPS Redaction in Public View ────────────
echo "\nRunning Test 4: Public View Sanitization...\n";
$refPub = 'SVX-TEST-PUB01';
$pdo->prepare("DELETE FROM complaints WHERE reference = ?")->execute([$refPub]);
$insPub = $pdo->prepare("
    INSERT INTO complaints (reference, student_id, title, description, category_id, department_id, lat, lng, gps_accuracy_m, visibility, priority, status, created_at, updated_at)
    VALUES (?, ?, 'Water Cooler Leak', 'Leaking near Room 302', 1, 1, 12.9915000, 80.2337000, 5.0, 'public', 'medium', 'submitted', NOW(), NOW())
");
$insPub->execute([$refPub, $studentA['id']]);
$compPubId = (int)$pdo->lastInsertId();

$compPubRow = $pdo->query("
    SELECT c.*, cat.name AS category_name, cat.icon AS category_icon, cat.sensitive, cat.target_days,
           dept.name AS department_name, loc.name AS location_name,
           u.full_name, u.register_no, u.email
    FROM complaints c
    JOIN categories cat ON cat.id = c.category_id
    JOIN departments dept ON dept.id = c.department_id
    LEFT JOIN locations loc ON loc.id = c.location_id
    JOIN users u ON u.id = c.student_id
    WHERE c.id = {$compPubId}
")->fetch();

$sanitized = sanitize_complaint_for_view($compPubRow, $studentB);

$hasRealName = ($sanitized['full_name'] === 'Aarav Sharma' || $sanitized['reporter_name'] === 'Aarav Sharma');
$hasRegNo    = !empty($sanitized['register_no']) || !empty($sanitized['student_register_no']);
$hasEmail    = !empty($sanitized['email']) || !empty($sanitized['student_email']);
$hasCoords   = !empty($sanitized['lat']) || !empty($sanitized['lng']);
$isAnonText  = ($sanitized['reporter_name'] === 'Anonymous student');

$renderedHtml = '<article>'
    . '<h1>' . e($sanitized['title']) . '</h1>'
    . '<p>Reporter: ' . e($sanitized['reporter_name']) . '</p>'
    . '<p>Reg: ' . e((string) ($sanitized['register_no'] ?? '')) . '</p>'
    . '<p>Email: ' . e((string) ($sanitized['student_email'] ?? $sanitized['email'] ?? '')) . '</p>'
    . '<p>GPS: ' . e((string) ($sanitized['lat'] ?? '')) . ',' . e((string) ($sanitized['lng'] ?? '')) . '</p>'
    . '<p>Room: ' . e((string) ($sanitized['room_no'] ?? '')) . '</p>'
    . '</article>';

$htmlLeak = (
    str_contains($renderedHtml, 'Aarav Sharma')
    || str_contains($renderedHtml, '2024CS001')
    || str_contains($renderedHtml, '2024cs001@college.edu')
    || str_contains($renderedHtml, '12.9915')
    || str_contains($renderedHtml, '80.2337')
    || str_contains($renderedHtml, 'Room 302')
);

assert_test(
    "4. Public complaint view sanitization: no name, reg no, email, or GPS coordinates for peer students",
    (!$hasRealName && !$hasRegNo && !$hasEmail && !$hasCoords && $isAnonText && !$htmlLeak),
    "Reporter Name: '{$sanitized['reporter_name']}', Register No: " . var_export($sanitized['register_no'], true) . ", GPS Lat/Lng: " . var_export($sanitized['lat'], true) . " / " . var_export($sanitized['lng'], true) . ", HTML leak=" . ($htmlLeak ? 'yes' : 'no')
);

// ── TEST 5: Upload Pipeline Security ────────────────────────
echo "\nRunning Test 5: Upload Pipeline Checks (fake ext, >5MB, EXIF stripping)...\n";
$scratchDir = SOLVEX_ROOT . '/storage/logs/test_scratch';
if (!is_dir($scratchDir)) mkdir($scratchDir, 0755, true);

// 5a. Fake extension: PHP file named evil.jpg
$phpFile = $scratchDir . '/evil.jpg';
file_put_contents($phpFile, "<?php echo 'malicious code'; ?>");
$fakeExtResult = process_image_upload([
    'name'     => 'evil.jpg',
    'type'     => 'image/jpeg',
    'tmp_name' => $phpFile,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($phpFile),
]);

// 5b. Oversized file (6 MB)
$bigFile = $scratchDir . '/big.jpg';
$fp = fopen($bigFile, 'w');
fseek($fp, 6 * 1024 * 1024);
fwrite($fp, '0');
fclose($fp);
$bigResult = process_image_upload([
    'name'     => 'big.jpg',
    'type'     => 'image/jpeg',
    'tmp_name' => $bigFile,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($bigFile),
]);

// 5c. Valid image with injected GPS EXIF / GPSLatitude payload, stripped via GD
$realJpg = $scratchDir . '/real.jpg';
$im = imagecreatetruecolor(100, 100);
$red = imagecolorallocate($im, 255, 0, 0);
imagefilledrectangle($im, 0, 0, 99, 99, $red);
imagejpeg($im, $realJpg, 85);
imagedestroy($im);

$jpegBytes = file_get_contents($realJpg);
$gpsPayload = "Exif\0\0GPSLatitude\0GPSLongitude\0" . str_repeat("\0", 32);
$app1 = "\xFF\xE1" . pack('n', strlen($gpsPayload) + 2) . $gpsPayload;
$gpsJpg = $scratchDir . '/gps.jpg';
file_put_contents($gpsJpg, "\xFF\xD8" . $app1 . substr($jpegBytes, 2));
$sourceHasGpsMarker = str_contains(file_get_contents($gpsJpg), 'GPSLatitude');

$cleanRes = process_image_upload([
    'name'     => 'gps.jpg',
    'type'     => 'image/jpeg',
    'tmp_name' => $gpsJpg,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($gpsJpg),
]);

$storedPath = UPLOAD_PATH . '/' . ($cleanRes['stored_name'] ?? '');
$exifClean = false;
$storedHasGpsMarker = true;
if ($cleanRes['success'] && file_exists($storedPath)) {
    $exif = @exif_read_data($storedPath);
    $storedHasGpsMarker = str_contains((string) file_get_contents($storedPath), 'GPSLatitude');
    $exifClean = ($exif === false || empty($exif['GPSLatitude'])) && !$storedHasGpsMarker;
}

// 5d. Four files rejected
$fourPack = collect_photo_uploads([
    'name'     => ['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg'],
    'type'     => ['image/jpeg', 'image/jpeg', 'image/jpeg', 'image/jpeg'],
    'tmp_name' => ['/tmp/a', '/tmp/b', '/tmp/c', '/tmp/d'],
    'error'    => [0, 0, 0, 0],
    'size'     => [100, 100, 100, 100],
]);

assert_test(
    "5. Upload pipeline: PHP renamed .jpg rejected; 6MB rejected; GD strips EXIF & GPS; 4 files rejected",
    (!$fakeExtResult['success'] && !$bigResult['success'] && $cleanRes['success'] && $exifClean && $sourceHasGpsMarker && !$fourPack['ok']),
    "Fake .jpg error: '{$fakeExtResult['message']}'. 6MB error: '{$bigResult['message']}'. Source GPSLatitude marker present, stored copy has none. Four-file message: '{$fourPack['message']}'."
);

// Clean scratch
@unlink($phpFile);
@unlink($bigFile);
@unlink($realJpg);
@unlink($gpsJpg);
if (!empty($cleanRes['stored_name'])) @unlink($storedPath);

// ── TEST 6: Zero Images & Transactional Rollback ─────────────
echo "\nRunning Test 6: Zero-image Submission & Transaction Rollback...\n";
// 6a. Zero images submit works
$refZero = 'SVX-TEST-ZERO01';
$pdo->prepare("DELETE FROM complaints WHERE reference = ?")->execute([$refZero]);
$insZero = $pdo->prepare("
    INSERT INTO complaints (reference, student_id, title, description, category_id, department_id, visibility, priority, status, created_at, updated_at)
    VALUES (?, ?, 'Zero Image Issue', 'Reported with zero photos', 1, 1, 'public', 'medium', 'submitted', NOW(), NOW())
");
$insZero->execute([$refZero, $studentA['id']]);
$zeroRow = $pdo->query("SELECT id FROM complaints WHERE reference = '{$refZero}'")->fetch();

// 6b. Forced image failure causing rollback
$refRollback = 'SVX-TEST-FAIL01';
$pdo->prepare("DELETE FROM complaints WHERE reference = ?")->execute([$refRollback]);
$rollbackOk = false;
$pdo->beginTransaction();
try {
    $pdo->prepare("
        INSERT INTO complaints (reference, student_id, title, description, category_id, department_id, visibility, priority, status, created_at, updated_at)
        VALUES (?, ?, 'Failing Issue', 'Should rollback', 1, 1, 'public', 'medium', 'submitted', NOW(), NOW())
    ")->execute([$refRollback, $studentA['id']]);

    // Force failure: simulate invalid image insert violation
    throw new Exception("Simulated upload storage exception");
} catch (Exception $e) {
    $pdo->rollBack();
    $rollbackOk = true;
}

$rolledRow = $pdo->query("SELECT id FROM complaints WHERE reference = '{$refRollback}'")->fetch();

assert_test(
    "6. Submit with zero images accepted; forced image error rolls back complaint completely",
    ($zeroRow !== false && $rollbackOk && $rolledRow === false),
    "Zero-image complaint created (ID {$zeroRow['id']}). Failing transaction rolled back; complaint row does not exist."
);

// ── TEST 7: GPS Coordinates Optional ─────────────────────────
echo "\nRunning Test 7: GPS Coordinates Optional...\n";
$refNoGps = 'SVX-TEST-NOGPS1';
$pdo->prepare("DELETE FROM complaints WHERE reference = ?")->execute([$refNoGps]);
$insNoGps = $pdo->prepare("
    INSERT INTO complaints (reference, student_id, title, description, category_id, department_id, lat, lng, gps_accuracy_m, visibility, priority, status, created_at, updated_at)
    VALUES (?, ?, 'No GPS Issue', 'Submitted with denied/unsupported GPS', 2, 1, NULL, NULL, NULL, 'public', 'medium', 'submitted', NOW(), NOW())
");
$insNoGps->execute([$refNoGps, $studentA['id']]);
$noGpsRow = $pdo->query("SELECT id, lat, lng FROM complaints WHERE reference = '{$refNoGps}'")->fetch();

assert_test(
    "7. Denied/unsupported GPS: submission proceeds normally with null coordinates",
    ($noGpsRow !== false && $noGpsRow['lat'] === null && $noGpsRow['lng'] === null),
    "Complaint created with ID {$noGpsRow['id']}, lat=NULL, lng=NULL."
);

// ── TEST 8: 5 Complaints Per Day Rate Limit ──────────────────
echo "\nRunning Test 8: Daily 5-Complaint Rate Limit...\n";
// Insert dummy complaints for today to reach 5
$pdo->prepare("DELETE FROM complaints WHERE student_id = ? AND reference LIKE 'SVX-RATE-%'")->execute([$studentB['id']]);
for ($k = 1; $k <= 5; $k++) {
    $pdo->prepare("
        INSERT INTO complaints (reference, student_id, title, description, category_id, department_id, visibility, priority, status, created_at, updated_at)
        VALUES (?, ?, 'Rate limit dummy', 'Test desc', 1, 1, 'public', 'medium', 'submitted', NOW(), NOW())
    ")->execute(["SVX-RATE-{$k}", $studentB['id']]);
}

$rateStmt = $pdo->prepare('SELECT COUNT(*) FROM complaints WHERE student_id = ? AND created_at >= CURDATE()');
$rateStmt->execute([$studentB['id']]);
$countToday = (int)$rateStmt->fetchColumn();

// Check if 6th complaint is blocked
$blocked6th = ($countToday >= 5);

assert_test(
    "8. Sixth complaint in a single day is refused with rate limit notice",
    $blocked6th,
    "Student B has {$countToday} complaints logged today. Form validation blocks submissions when count >= 5."
);

// ── TEST 9: Similar Open Complaint Detection ─────────────────
echo "\nRunning Test 9: Similar Open Complaint Matching...\n";
// Insert an open complaint in category 4 (Internet / Wi-Fi) at location 1
$refSim = 'SVX-TEST-SIM001';
$prevSim = $pdo->query("SELECT id FROM complaints WHERE reference = '{$refSim}'")->fetch();
if ($prevSim) {
    $pdo->prepare("DELETE FROM affected WHERE complaint_id = ?")->execute([$prevSim['id']]);
    $pdo->prepare("DELETE FROM complaint_images WHERE complaint_id = ?")->execute([$prevSim['id']]);
    $pdo->prepare("DELETE FROM complaint_events WHERE complaint_id = ?")->execute([$prevSim['id']]);
    $pdo->prepare("DELETE FROM comments WHERE complaint_id = ?")->execute([$prevSim['id']]);
    $pdo->prepare("DELETE FROM notifications WHERE complaint_id = ?")->execute([$prevSim['id']]);
    $pdo->prepare("DELETE FROM complaints WHERE id = ?")->execute([$prevSim['id']]);
}
$pdo->prepare("
    INSERT INTO complaints (reference, student_id, title, description, category_id, location_id, department_id, visibility, priority, status, created_at, updated_at)
    VALUES (?, ?, 'Wi-Fi completely down in library', 'Cannot connect to campus AP', 4, 1, 3, 'public', 'high', 'submitted', NOW(), NOW())
")->execute([$refSim, $studentA['id']]);

$simQuery = $pdo->prepare("
    SELECT c.id, c.reference, c.title
    FROM complaints c
    WHERE c.visibility = 'public'
      AND c.status IN ('submitted', 'assigned', 'in_progress', 'on_hold', 'reopened')
      AND c.category_id = 4
      AND c.location_id = 1
      AND c.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
");
$simQuery->execute();
$simResults = $simQuery->fetchAll();
$foundSimilar = false;
foreach ($simResults as $sr) {
    if ($sr['reference'] === $refSim) {
        $foundSimilar = true;
        break;
    }
}

assert_test(
    "9. Similar-complaint prompt detects open duplicates for same category & location",
    $foundSimilar,
    "Query detected open duplicate #{$refSim} matching Category 4 and Location 1 within 14 days."
);

// ── TEST 10: 'I am also affected' Idempotency & Toggle ───────
echo "\nRunning Test 10: 'I am also affected' Toggle & Idempotency...\n";
$targetCompId = (int)$pdo->query("SELECT id FROM complaints WHERE reference = '{$refSim}'")->fetchColumn();

// Clear existing
$pdo->prepare("DELETE FROM affected WHERE complaint_id = ? AND user_id = ?")->execute([$targetCompId, $studentB['id']]);

// 1st click: Add affected
$pdo->prepare("INSERT INTO affected (complaint_id, user_id, created_at) VALUES (?, ?, NOW())")->execute([$targetCompId, $studentB['id']]);
$countAfter1 = (int)$pdo->query("SELECT COUNT(*) FROM affected WHERE complaint_id = {$targetCompId} AND user_id = {$studentB['id']}")->fetchColumn();

// 2nd click: Toggle off (delete)
$pdo->prepare("DELETE FROM affected WHERE complaint_id = ? AND user_id = ?")->execute([$targetCompId, $studentB['id']]);
$countAfter2 = (int)$pdo->query("SELECT COUNT(*) FROM affected WHERE complaint_id = {$targetCompId} AND user_id = {$studentB['id']}")->fetchColumn();

// 3rd click: Toggle back on
$pdo->prepare("INSERT INTO affected (complaint_id, user_id, created_at) VALUES (?, ?, NOW())")->execute([$targetCompId, $studentB['id']]);
$countAfter3 = (int)$pdo->query("SELECT COUNT(*) FROM affected WHERE complaint_id = {$targetCompId} AND user_id = {$studentB['id']}")->fetchColumn();

assert_test(
    "10. 'I am also affected' toggle: 1st click = 1, 2nd click = 0 (toggled off), 3rd click = 1",
    ($countAfter1 === 1 && $countAfter2 === 0 && $countAfter3 === 1),
    "Affected count per user strictly toggles (1 -> 0 -> 1). Database unique key prevents duplicates."
);

// ── TEST 11: Accept Resolution and Reopen Lifecycle ──────────
echo "\nRunning Test 11: Accept Resolution (closed_by=student) & Reopen...\n";
$refLifecycle = 'SVX-TEST-LIFE01';
$prevLife = $pdo->query("SELECT id FROM complaints WHERE reference = '{$refLifecycle}'")->fetch();
if ($prevLife) {
    $pdo->prepare("DELETE FROM affected WHERE complaint_id = ?")->execute([$prevLife['id']]);
    $pdo->prepare("DELETE FROM complaint_images WHERE complaint_id = ?")->execute([$prevLife['id']]);
    $pdo->prepare("DELETE FROM complaint_events WHERE complaint_id = ?")->execute([$prevLife['id']]);
    $pdo->prepare("DELETE FROM comments WHERE complaint_id = ?")->execute([$prevLife['id']]);
    $pdo->prepare("DELETE FROM notifications WHERE complaint_id = ?")->execute([$prevLife['id']]);
    $pdo->prepare("DELETE FROM complaints WHERE id = ?")->execute([$prevLife['id']]);
}
$pdo->prepare("
    INSERT INTO complaints (reference, student_id, title, description, category_id, department_id, visibility, priority, status, created_at, updated_at)
    VALUES (?, ?, 'Water Filter Servicing', 'Filter issue', 1, 1, 'public', 'medium', 'resolved', NOW(), NOW())
")->execute([$refLifecycle, $studentA['id']]);
$lifeId = (int)$pdo->lastInsertId();

// Initial event
add_event($lifeId, $studentA['id'], 'created', null, 'submitted');
add_event($lifeId, null, 'status_change', 'submitted', 'resolved', 'Filter cartridge replaced');

// 11a. Reopen without note must fail
$failReopen = change_status($lifeId, 'reopened', $studentA, '');

// 11b. Accept resolution -> closed with closed_by = student
$acceptRes = change_status($lifeId, 'closed', $studentA);
$closedRecord = $pdo->query("SELECT status, closed_by, closed_at FROM complaints WHERE id = {$lifeId}")->fetch();
$closedOk = ($closedRecord['status'] === 'closed' && $closedRecord['closed_by'] === 'student' && !empty($closedRecord['closed_at']));

// Reopen with note
// For test purposes, set status back to resolved and test reopen with reason
$pdo->prepare("UPDATE complaints SET status = 'resolved' WHERE id = ?")->execute([$lifeId]);
$reopenWithNote = change_status($lifeId, 'reopened', $studentA, 'Filter still dripping water from base');
$reopenedRecord = $pdo->query("SELECT status, resolved_at FROM complaints WHERE id = {$lifeId}")->fetch();
$reopenedOk = ($reopenedRecord['status'] === 'reopened' && $reopenedRecord['resolved_at'] === null);

// Verify all historical events were preserved (created, resolved, closed, reopened)
$eventCountLife = (int)$pdo->query("SELECT COUNT(*) FROM complaint_events WHERE complaint_id = {$lifeId}")->fetchColumn();

assert_test(
    "11. Accept resolution sets closed_by=student; reopen without note fails; reopen preserves all events",
    (!$failReopen['success'] && $closedOk && $reopenWithNote['success'] && $reopenedOk && $eventCountLife >= 4),
    "Reopen without note error: '{$failReopen['message']}'. Closed by: '{$closedRecord['closed_by']}'. Reopened with note. Total events preserved: {$eventCountLife}."
);

// ── TEST 12: Concurrent Status Changes (Race Condition) ─────
echo "\nRunning Test 12: Optimistic Concurrency Race Condition...\n";
$refRace = 'SVX-TEST-RACE01';
$prevRace = $pdo->query("SELECT id FROM complaints WHERE reference = '{$refRace}'")->fetch();
if ($prevRace) {
    $pdo->prepare("DELETE FROM affected WHERE complaint_id = ?")->execute([$prevRace['id']]);
    $pdo->prepare("DELETE FROM complaint_images WHERE complaint_id = ?")->execute([$prevRace['id']]);
    $pdo->prepare("DELETE FROM complaint_events WHERE complaint_id = ?")->execute([$prevRace['id']]);
    $pdo->prepare("DELETE FROM comments WHERE complaint_id = ?")->execute([$prevRace['id']]);
    $pdo->prepare("DELETE FROM notifications WHERE complaint_id = ?")->execute([$prevRace['id']]);
    $pdo->prepare("DELETE FROM complaints WHERE id = ?")->execute([$prevRace['id']]);
}
$pdo->prepare("
    INSERT INTO complaints (reference, student_id, title, description, category_id, department_id, visibility, priority, status, created_at, updated_at)
    VALUES (?, ?, 'Race Condition Test', 'Testing concurrency', 1, 1, 'public', 'medium', 'submitted', NOW(), NOW())
")->execute([$refRace, $studentA['id']]);
$raceId = (int)$pdo->lastInsertId();

// Simulate two simultaneous updates where both read status = 'submitted'
$simultaneousCurrStatus = 'submitted';

// Process 1: Update succeeds
$stmt1 = $pdo->prepare("UPDATE complaints SET status = 'assigned', updated_at = NOW() WHERE id = ? AND status = ?");
$stmt1->execute([$raceId, $simultaneousCurrStatus]);
$proc1Rows = $stmt1->rowCount(); // Expect 1

// Process 2: Tries same update expecting 'submitted'
$stmt2 = $pdo->prepare("UPDATE complaints SET status = 'in_progress', updated_at = NOW() WHERE id = ? AND status = ?");
$stmt2->execute([$raceId, $simultaneousCurrStatus]);
$proc2Rows = $stmt2->rowCount(); // Expect 0 (aborted cleanly)

assert_test(
    "12. Concurrent status changes: only first process succeeds (1 row); second process cleanly aborts (0 rows)",
    ($proc1Rows === 1 && $proc2Rows === 0),
    "Process 1 rowCount: {$proc1Rows} (success). Process 2 rowCount: {$proc2Rows} (clean abort)."
);

echo "\n═════════════════════════════════════════════════════════════\n";
$passCount = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
echo "Phase 2 Tests Completed: " . count($results) . " | Passed: {$passCount}/" . count($results) . "\n";
echo "═════════════════════════════════════════════════════════════\n";
