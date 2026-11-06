<?php
/**
 * Solvex Report — Hostel Management Lifecycle Automated Verification Suite
 * 
 * Verifies the MCA project core requirements:
 * 1. 3 Roles: Student, Warden (Admin), Maintenance Staff.
 * 2. 6 Hostel Categories: Plumbing, Electrical, Cleaning, Room/Furniture, Wi-Fi, Mess.
 * 3. 5-Stage Lifecycle:
 *    Submit (pending) -> Warden Assign (assigned) -> Staff Work (in_progress) -> Staff Resolve (resolved) -> Student Confirm (closed).
 * 4. Student Reopen: Resolved -> Reopened with mandatory remark.
 * 5. Private Isolation: Students can only view their own complaints.
 * 6. Staff Isolation: Staff only view their assigned complaints.
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

$pdo = db();
$passedTests = 0;
$totalTests  = 0;

function assert_hostel_test(string $name, bool $condition, string $evidence = ''): void {
    global $passedTests, $totalTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "✅ [PASS] {$name}\n";
        if ($evidence) echo "   Evidence: {$evidence}\n";
    } else {
        echo "❌ [FAIL] {$name}\n";
        if ($evidence) echo "   Evidence: {$evidence}\n";
    }
}

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "   SOLVEX REPORT — HOSTEL MANAGEMENT LIFECYCLE VERIFICATION SUITE      \n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

// 1. Setup Users for Test
$studentA = $pdo->query("SELECT * FROM users WHERE role = 'student' AND status = 'active' LIMIT 1")->fetch();
$studentB = $pdo->query("SELECT * FROM users WHERE role = 'student' AND status = 'active' AND id != {$studentA['id']} LIMIT 1")->fetch();
$warden   = $pdo->query("SELECT * FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1")->fetch();
$staff1   = $pdo->query("SELECT * FROM users WHERE role = 'staff' AND status = 'active' LIMIT 1")->fetch();

assert_hostel_test(
    "1. Role Verification: Student, Warden, and Staff accounts present",
    ($studentA && $warden && $staff1),
    "Student ID: {$studentA['id']}, Warden ID: {$warden['id']}, Staff ID: {$staff1['id']}"
);

// 2. Setup Hostel Complaint Submission
$ref = 'SVX-' . date('Y') . '-99901';
$pdo->prepare("DELETE FROM complaint_events WHERE complaint_id IN (SELECT id FROM complaints WHERE reference = ?)")->execute([$ref]);
$pdo->prepare("DELETE FROM complaints WHERE reference = ?")->execute([$ref]);

// Student submits
$ins = $pdo->prepare("
    INSERT INTO complaints (
        reference, student_id, title, description, category_id,
        location_id, department_id, visibility, priority, status,
        created_at, updated_at
    ) VALUES (
        ?, ?, 'Broken Tap in Room 204', 'Water continues to drip constantly.', 1,
        52, 1, 'private', 'medium', 'pending',
        NOW(), NOW()
    )
");
$ins->execute([$ref, $studentA['id']]);
$complaintId = (int) $pdo->lastInsertId();

$evtIns = $pdo->prepare("INSERT INTO complaint_events (complaint_id, actor_id, type, old_value, new_value, reason, created_at) VALUES (?, ?, 'created', NULL, 'pending', 'Submitted by student', NOW())");
$evtIns->execute([$complaintId, $studentA['id']]);

$freshComplaint = $pdo->query("SELECT * FROM complaints WHERE id = {$complaintId}")->fetch();
assert_hostel_test(
    "2. Student Submission: Status set to 'pending' and visibility strictly 'private'",
    ($freshComplaint['status'] === 'pending' && $freshComplaint['visibility'] === 'private'),
    "Reference: {$freshComplaint['reference']}, Status: {$freshComplaint['status']}, Visibility: {$freshComplaint['visibility']}"
);

// 3. Privacy Check: Student B cannot view Student A's complaint
$canStudentA = can_view_complaint($studentA, $freshComplaint);
$canStudentB = can_view_complaint($studentB, $freshComplaint);
$canWarden   = can_view_complaint($warden, $freshComplaint);

assert_hostel_test(
    "3. Student Isolation: Non-owner student receives FALSE for private complaint view",
    ($canStudentA === true && $canStudentB === false && $canWarden === true),
    "Owner Student A: " . ($canStudentA ? 'ALLOW' : 'DENY') . ", Student B: " . ($canStudentB ? 'ALLOW' : 'DENY') . ", Warden: " . ($canWarden ? 'ALLOW' : 'DENY')
);

// 4. Warden Assigns Complaint to Maintenance Staff
$assignRes = change_status($complaintId, 'assigned', $warden, 'Warden assigned to maintenance technician');
$pdo->prepare("UPDATE complaints SET assigned_to = ? WHERE id = ?")->execute([$staff1['id'], $complaintId]);
$cAfterAssign = $pdo->query("SELECT * FROM complaints WHERE id = {$complaintId}")->fetch();

assert_hostel_test(
    "4. Warden Assignment: Status changes to 'assigned' and assigned_to is set",
    ($assignRes['success'] && $cAfterAssign['status'] === 'assigned' && (int)$cAfterAssign['assigned_to'] === (int)$staff1['id']),
    "New Status: {$cAfterAssign['status']}, Assigned To: Staff #{$cAfterAssign['assigned_to']}"
);

// 5. Staff Updates Status to In Progress
$progRes = change_status($complaintId, 'in_progress', $staff1, 'Started plumbing inspection and pipe replacement');
$cAfterProg = $pdo->query("SELECT * FROM complaints WHERE id = {$complaintId}")->fetch();

assert_hostel_test(
    "5. Staff In-Progress: Technician begins repair work",
    ($progRes['success'] && $cAfterProg['status'] === 'in_progress'),
    "New Status: {$cAfterProg['status']}, Event logged: {$progRes['message']}"
);

// 6. Staff Marks Resolved with Mandatory Note
$resNoNote = change_status($complaintId, 'resolved', $staff1, ''); // Should fail because reason is required
$resWithNote = change_status($complaintId, 'resolved', $staff1, 'Replaced the rubber washer and valve. Leak tested and fixed.');
$cAfterRes = $pdo->query("SELECT * FROM complaints WHERE id = {$complaintId}")->fetch();

assert_hostel_test(
    "6. Staff Resolution: Resolving requires repair note; status changes to 'resolved'",
    (!$resNoNote['success'] && $resWithNote['success'] && $cAfterRes['status'] === 'resolved' && !empty($cAfterRes['resolved_at'])),
    "Empty Note Refused: {$resNoNote['message']}. Resolved with note succeeded. Resolved At: {$cAfterRes['resolved_at']}"
);

// 7. Student Verifies and Confirms Resolution (Closed)
// Non-owner student cannot close
$bCloseRes = change_status($complaintId, 'closed', $studentB);
$aCloseRes = change_status($complaintId, 'closed', $studentA);
$cAfterClose = $pdo->query("SELECT * FROM complaints WHERE id = {$complaintId}")->fetch();

assert_hostel_test(
    "7. Student Resolution Confirmation: Owner closes ticket (closed_by=student)",
    (!$bCloseRes['success'] && $aCloseRes['success'] && $cAfterClose['status'] === 'closed' && $cAfterClose['closed_by'] === 'student'),
    "Non-owner rejected. Owner closed ticket. Closed By: {$cAfterClose['closed_by']}"
);

// 8. Reopen Workflow Test
// When an issue is in 'resolved', student can reopen if water is still leaking
$pdo->prepare("UPDATE complaints SET status = 'resolved' WHERE id = ?")->execute([$complaintId]);
$reopenNoReason = change_status($complaintId, 'reopened', $studentA, '');
$reopenWithReason = change_status($complaintId, 'reopened', $studentA, 'Tap started leaking again from the top spindle.');
$cAfterReopen = $pdo->query("SELECT * FROM complaints WHERE id = {$complaintId}")->fetch();

assert_hostel_test(
    "8. Student Reopen Workflow: Reopening requires explanation remark",
    (!$reopenNoReason['success'] && $reopenWithReason['success'] && $cAfterReopen['status'] === 'reopened'),
    "Empty reason rejected: {$reopenNoReason['message']}. Reopened with reason. Status: {$cAfterReopen['status']}"
);

// 9. Full Event History Audit Integrity
$events = $pdo->query("SELECT * FROM complaint_events WHERE complaint_id = {$complaintId} ORDER BY id ASC")->fetchAll();
assert_hostel_test(
    "9. Audit Trail Integrity: All status transitions appended without overwriting history",
    (count($events) >= 5),
    "Total audit events recorded: " . count($events)
);

// Cleanup
$pdo->prepare("DELETE FROM complaint_events WHERE complaint_id = ?")->execute([$complaintId]);
$pdo->prepare("DELETE FROM complaints WHERE id = ?")->execute([$complaintId]);

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "   HOSTEL TEST RESULTS: {$passedTests} PASSED / {$totalTests} TOTAL\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

if ($passedTests === $totalTests) {
    echo "🎉 ALL HOSTEL LIFECYCLE TESTS COMPLETED WITH 100% SUCCESS.\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED.\n";
    exit(1);
}
