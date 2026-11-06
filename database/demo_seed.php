<?php
/**
 * Solvex Report — Realistic Demonstration Seed Generator (Phase 4)
 * 
 * Generates ~25 realistic students and ~45 complaints spread over the last 60 days
 * using legitimate lifecycle transitions and realistic campus scenarios.
 * 
 * Supports `--reset` command line flag.
 */

define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/events.php';
require_once SOLVEX_ROOT . '/includes/upload.php';

$pdo = db();
$isReset = in_array('--reset', $argv, true);
$isForce = in_array('--force', $argv, true);

if (defined('APP_ENV') && APP_ENV !== 'local' && !$isForce) {
    die("ABORTED: demo_seed.php cannot run in " . (defined('APP_ENV') ? APP_ENV : 'non-local') . " environment without --force flag.\n");
}

echo "═════════════════════════════════════════════════════════════\n";
echo "   Solvex Report — Realistic Demo Seed Generator (Phase 4)   \n";
echo "═════════════════════════════════════════════════════════════\n";

if ($isReset) {
    echo "Resetting demo tickets and comments...\n";
    $pdo->query("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->query("TRUNCATE TABLE affected");
    $pdo->query("TRUNCATE TABLE comments");
    $pdo->query("TRUNCATE TABLE complaint_events");
    $pdo->query("TRUNCATE TABLE complaint_images");
    $pdo->query("TRUNCATE TABLE complaints");
    $pdo->query("SET FOREIGN_KEY_CHECKS = 1");
    echo "Tables truncated cleanly.\n";
}

// 1. Ensure Standard Demo Accounts
$stdPass = password_hash('Student@123', PASSWORD_DEFAULT);
$stfPass = password_hash('Staff@123', PASSWORD_DEFAULT);
$admPass = password_hash('Admin@123', PASSWORD_DEFAULT);

// Demo Student
$pStudent = $pdo->query("SELECT id FROM users WHERE email = 'student@solvex.edu'")->fetch();
if (!$pStudent) {
    $pdo->prepare("INSERT INTO users (register_no, full_name, email, password_hash, role, status, email_verified_at) VALUES ('2024CS100', 'Aarav Patel', 'student@solvex.edu', ?, 'student', 'active', NOW())")
        ->execute([$stdPass]);
}

// Demo Staff
$pStaff = $pdo->query("SELECT id FROM users WHERE email = 'staff@solvex.edu' OR staff_code = 'STF001'")->fetch();
if (!$pStaff) {
    $pdo->prepare("INSERT INTO users (staff_code, full_name, email, password_hash, role, department_id, status, email_verified_at) VALUES ('STF001', 'Rajesh Kumar', 'staff@solvex.edu', ?, 'staff', 1, 'active', NOW())")
        ->execute([$stfPass]);
} else {
    $pdo->prepare("UPDATE users SET email = 'staff@solvex.edu', password_hash = ?, status = 'active' WHERE id = ?")->execute([$stfPass, $pStaff['id']]);
}

// Demo Admin
$pAdmin = $pdo->query("SELECT id FROM users WHERE email = 'admin@solvex.edu' OR staff_code = 'ADM001'")->fetch();
if (!$pAdmin) {
    $pdo->prepare("INSERT INTO users (staff_code, full_name, email, password_hash, role, status, email_verified_at) VALUES ('ADM001', 'Chief Warden & Admin', 'admin@solvex.edu', ?, 'admin', 'active', NOW())")
        ->execute([$admPass]);
} else {
    $pdo->prepare("UPDATE users SET email = 'admin@solvex.edu', password_hash = ?, status = 'active' WHERE id = ?")->execute([$admPass, $pAdmin['id']]);
}

// 2. Fetch Active Student and Staff Pools
$students = $pdo->query("SELECT id, full_name, email, role, register_no FROM users WHERE role = 'student' AND status = 'active'")->fetchAll();
if (count($students) < 15) {
    // Seed Indian student names
    $names = [
        ['2024CS011', 'Diya Sharma', 'diya@solvex.edu'],
        ['2024CS012', 'Vihaan Reddy', 'vihaan@solvex.edu'],
        ['2024EC013', 'Ananya Iyer', 'ananya@solvex.edu'],
        ['2024EC014', 'Arjun Nambiar', 'arjun@solvex.edu'],
        ['2024ME015', 'Rohan Verma', 'rohan@solvex.edu'],
        ['2024ME016', 'Kavya Nair', 'kavya@solvex.edu'],
        ['2023CS017', 'Kabir Mukherjee', 'kabir@solvex.edu'],
        ['2023EC018', 'Tanvi Deshmukh', 'tanvi@solvex.edu'],
        ['2023ME019', 'Dhruv Menon', 'dhruv@solvex.edu'],
        ['2024CV020', 'Saanvi Kulkarni', 'saanvi@solvex.edu'],
        ['2024CV021', 'Aakash Bhatt', 'aakash@solvex.edu'],
        ['2024EE022', 'Sneha Pillai', 'sneha@solvex.edu'],
        ['2024EE023', 'Pranav Agarwal', 'pranav@solvex.edu'],
        ['2024IT024', 'Nikhil Joshi', 'nikhil@solvex.edu'],
        ['2024IT025', 'Divya Chauhan', 'divya@solvex.edu'],
    ];
    foreach ($names as $n) {
        $pdo->prepare("INSERT IGNORE INTO users (register_no, full_name, email, password_hash, role, status, email_verified_at) VALUES (?, ?, ?, ?, 'student', 'active', NOW())")
            ->execute([$n[0], $n[1], $n[2], $stdPass]);
    }
    $students = $pdo->query("SELECT id, full_name, email, role, register_no FROM users WHERE role = 'student'")->fetchAll();
}

$staffUsers = $pdo->query("SELECT id, full_name, department_id, role FROM users WHERE role = 'staff'")->fetchAll();
$adminUser  = $pdo->query("SELECT id, full_name, role FROM users WHERE role = 'admin' LIMIT 1")->fetch();

// 3. Realistic Scenarios Array (~45 complaints)
$scenarios = [
    // Longest Unresolved (30-50 days open)
    [
        'title' => 'Corridor ceiling plaster peeling off and falling',
        'desc'  => 'Large chunks of ceiling plaster fell near Room 302 after heavy rains. Poses a danger to walking students.',
        'cat'   => 2, 'loc' => 17, 'dept' => 1, 'pri' => 'high', 'vis' => 'public', 'days_ago' => 48,
        'status'=> 'in_progress', 'assign' => true, 'events' => ['created', 'assigned', 'in_progress']
    ],
    [
        'title' => 'Main library 2nd floor air conditioning unit leaking water',
        'desc'  => 'Condensation dripping on study desks. Books are at risk of water damage.',
        'cat'   => 1, 'loc' => 3, 'dept' => 1, 'pri' => 'medium', 'vis' => 'public', 'days_ago' => 42,
        'status'=> 'in_progress', 'assign' => true, 'events' => ['created', 'assigned', 'in_progress']
    ],
    [
        'title' => 'Playground high-mast light pole flickering continuously',
        'desc'  => 'The floodlight near the basketball court keeps flickering at night causing vision strain.',
        'cat'   => 3, 'loc' => 7, 'dept' => 2, 'pri' => 'medium', 'vis' => 'public', 'days_ago' => 38,
        'status'=> 'on_hold', 'hold_days' => 5, 'assign' => true, 'events' => ['created', 'assigned', 'in_progress', 'on_hold']
    ],
    [
        'title' => 'Hostel Block B ground floor water cooler dispensing muddy water',
        'desc'  => 'The purification filter has not been serviced. Water color is brownish.',
        'cat'   => 1, 'loc' => 20, 'dept' => 1, 'pri' => 'high', 'vis' => 'public', 'days_ago' => 34,
        'status'=> 'in_progress', 'assign' => true, 'events' => ['created', 'assigned', 'in_progress']
    ],
    [
        'title' => 'Hostel Wi-Fi access point reboot loop on 3rd floor',
        'desc'  => 'Signal drops every 3 minutes. Unable to attend evening online coding assessments.',
        'cat'   => 4, 'loc' => 22, 'dept' => 3, 'pri' => 'high', 'vis' => 'public', 'days_ago' => 31,
        'status'=> 'submitted', 'assign' => false, 'events' => ['created']
    ],

    // Overdue Tickets (Age > Target SLA)
    [
        'title' => 'Broken glass window pane in Physics Lab room 202',
        'desc'  => 'Cracked window panel rattling during wind. Risk of breaking inward.',
        'cat'   => 2, 'loc' => 16, 'dept' => 1, 'pri' => 'high', 'vis' => 'public', 'days_ago' => 14,
        'status'=> 'assigned', 'assign' => true, 'events' => ['created', 'assigned']
    ],
    [
        'title' => 'Computer Lab projector showing severe green tint',
        'desc'  => 'HDMI/VGA cable socket is damaged. Professors cannot present lecture slides.',
        'cat'   => 5, 'loc' => 13, 'dept' => 3, 'pri' => 'medium', 'vis' => 'public', 'days_ago' => 10,
        'status'=> 'in_progress', 'assign' => true, 'events' => ['created', 'assigned', 'in_progress']
    ],
    [
        'title' => 'Mess table row 4 wooden bench collapsed',
        'desc'  => 'Supporting leg broke during lunch rush. Multiple students seated fell.',
        'cat'   => 2, 'loc' => 4, 'dept' => 1, 'pri' => 'medium', 'vis' => 'public', 'days_ago' => 8,
        'status'=> 'submitted', 'assign' => false, 'events' => ['created']
    ],

    // Critical Open
    [
        'title' => 'Exposed sparking live electrical wire in 1st floor washroom',
        'desc'  => 'Switchboard cover plate is detached with exposed 230V wiring next to water sink basin.',
        'cat'   => 3, 'loc' => 23, 'dept' => 2, 'pri' => 'critical', 'vis' => 'public', 'days_ago' => 2,
        'status'=> 'in_progress', 'assign' => true, 'events' => ['created', 'assigned', 'in_progress']
    ],
    [
        'title' => 'Hostel emergency exit door chain jammed shut',
        'desc'  => 'Fire exit route door locked from outside. Safety hazard in case of evacuation.',
        'cat'   => 10, 'loc' => 5, 'dept' => 6, 'pri' => 'critical', 'vis' => 'private', 'days_ago' => 1,
        'status'=> 'assigned', 'assign' => true, 'events' => ['created', 'assigned']
    ],

    // Resolved Awaiting Student Confirmation
    [
        'title' => 'Leaking washroom tap replaced on 2nd floor',
        'desc'  => 'Continuous dripping water tap wasting water throughout the night.',
        'cat'   => 1, 'loc' => 26, 'dept' => 1, 'pri' => 'medium', 'vis' => 'public', 'days_ago' => 4,
        'status'=> 'resolved', 'resolved_days_ago' => 2, 'assign' => true, 'events' => ['created', 'assigned', 'in_progress', 'resolved']
    ],
    [
        'title' => 'Canteen juice vending machine short-circuit fixed',
        'desc'  => 'Vending machine tripped the main breaker.',
        'cat'   => 3, 'loc' => 4, 'dept' => 2, 'pri' => 'high', 'vis' => 'public', 'days_ago' => 6,
        'status'=> 'resolved', 'resolved_days_ago' => 1, 'assign' => true, 'events' => ['created', 'assigned', 'in_progress', 'resolved']
    ],
    [
        'title' => 'Chemistry lab exhaust fan repaired',
        'desc'  => 'Fume hood exhaust motor was not rotating.',
        'cat'   => 3, 'loc' => 19, 'dept' => 2, 'pri' => 'high', 'vis' => 'public', 'days_ago' => 5,
        'status'=> 'resolved', 'resolved_days_ago' => 3, 'assign' => true, 'events' => ['created', 'assigned', 'in_progress', 'resolved']
    ],

    // Auto-Closed (resolved > 5 days ago, student did not reply)
    [
        'title' => 'Door handle latch repaired in Room 101',
        'desc'  => 'Door latch was sticking.',
        'cat'   => 2, 'loc' => 11, 'dept' => 1, 'pri' => 'low', 'vis' => 'public', 'days_ago' => 18,
        'status'=> 'closed', 'closed_by' => 'auto', 'assign' => true, 'events' => ['created', 'assigned', 'in_progress', 'resolved', 'closed']
    ],
    [
        'title' => 'Replaced fluorescent tube in corridor',
        'desc'  => 'Dark corridor at night.',
        'cat'   => 3, 'loc' => 24, 'dept' => 2, 'pri' => 'medium', 'vis' => 'public', 'days_ago' => 22,
        'status'=> 'closed', 'closed_by' => 'auto', 'assign' => true, 'events' => ['created', 'assigned', 'in_progress', 'resolved', 'closed']
    ],

    // Student Accepted Closed Tickets
    [
        'title' => 'Restored high-speed LAN port connection in CS Lab',
        'desc'  => 'Ethernet socket 14 had broken pin.',
        'cat'   => 4, 'loc' => 13, 'dept' => 3, 'pri' => 'medium', 'vis' => 'public', 'days_ago' => 12,
        'status'=> 'closed', 'closed_by' => 'student', 'assign' => true, 'events' => ['created', 'assigned', 'in_progress', 'resolved', 'closed']
    ],
    [
        'title' => 'Mess dinner food temperature issue addressed',
        'desc'  => 'Dinner served lukewarm.',
        'cat'   => 6, 'loc' => 4, 'dept' => 4, 'pri' => 'medium', 'vis' => 'public', 'days_ago' => 15,
        'status'=> 'closed', 'closed_by' => 'student', 'assign' => true, 'events' => ['created', 'assigned', 'in_progress', 'resolved', 'closed']
    ],

    // Rejected with Reason
    [
        'title' => 'Request personal gaming console internet unblock',
        'desc'  => 'Whitelist MAC address for PlayStation network on campus subnet.',
        'cat'   => 4, 'loc' => 5, 'dept' => 3, 'pri' => 'low', 'vis' => 'private', 'days_ago' => 20,
        'status'=> 'rejected', 'reason' => 'Campus IT policy strictly prohibits unauthorized gaming hardware on educational subnet.',
        'assign'=> true, 'events' => ['created', 'rejected']
    ],
    [
        'title' => 'Noise from construction during scheduled classes',
        'desc'  => 'Repaving noise near block 2.',
        'cat'   => 8, 'loc' => 2, 'dept' => 5, 'pri' => 'medium', 'vis' => 'public', 'days_ago' => 25,
        'status'=> 'rejected', 'reason' => 'Mandatory civil maintenance approved by Dean of Planning; work limited to scheduled lunch hours.',
        'assign'=> true, 'events' => ['created', 'rejected']
    ],

    // Appealed Ticket (rejected then reopened)
    [
        'title' => 'Hostel hot water geyser timer shut off too early',
        'desc'  => 'Geyser shuts off at 7:30 AM before morning lab students wake up.',
        'cat'   => 8, 'loc' => 5, 'dept' => 5, 'pri' => 'medium', 'vis' => 'public', 'days_ago' => 28,
        'status'=> 'reopened', 'assign' => true, 'events' => ['created', 'rejected', 'reopened']
    ],

    // Duplicate Pair
    [
        'title' => 'Mess water dispenser leaking clean water continuously',
        'desc'  => 'Water pooling near tray.',
        'cat'   => 1, 'loc' => 4, 'dept' => 1, 'pri' => 'medium', 'vis' => 'public', 'days_ago' => 16,
        'status'=> 'in_progress', 'assign' => true, 'events' => ['created', 'assigned', 'in_progress']
    ],
    [
        'title' => 'Water dispenser tray overflowing in mess hall',
        'desc'  => 'Same cooler dripping water on dining floor.',
        'cat'   => 1, 'loc' => 4, 'dept' => 1, 'pri' => 'medium', 'vis' => 'public', 'days_ago' => 15,
        'status'=> 'duplicate', 'assign' => true, 'events' => ['created', 'duplicate']
    ],
];

// Additional standard randomized complaints to reach ~45 items
$prefixes = ['Broken', 'Faulty', 'Damaged', 'Uncleaned', 'Flickering', 'Missing', 'Intermittent'];
$assets   = ['ceiling fan', 'projector screen', 'washroom latch', 'staircase handrail', 'water filter tap', 'Ethernet cable', 'notice board glass', 'street light'];
$locationsList = [11, 12, 13, 14, 15, 16, 23, 24, 25, 26, 27, 28, 35, 36, 37];

for ($k = 0; $k < 23; $k++) {
    $p = $prefixes[$k % count($prefixes)];
    $a = $assets[$k % count($assets)];
    $scenarios[] = [
        'title'    => "{$p} {$a} in campus block",
        'desc'     => "Routine maintenance needed for {$a}. Observed earlier this week during classes.",
        'cat'      => ($k % 5) + 1,
        'loc'      => $locationsList[$k % count($locationsList)],
        'dept'     => (($k % 3) + 1),
        'pri'      => ($k % 4 === 0) ? 'high' : 'medium',
        'vis'      => ($k % 5 === 0) ? 'private' : 'public',
        'days_ago' => rand(1, 55),
        'status'   => ['submitted', 'assigned', 'in_progress', 'resolved', 'closed'][$k % 5],
        'assign'   => true,
        'events'   => ['created', 'assigned', 'in_progress']
    ];
}

$createdCount = 0;
$seqYear = date('Y');

foreach ($scenarios as $idx => $s) {
    $seq = $idx + 1;
    $ref = sprintf('SVX-%s-%05d', $seqYear, $seq);
    $student = $students[$idx % count($students)];
    $studentId = (int)$student['id'];

    $createdAt = date('Y-m-d H:i:s', strtotime("-{$s['days_ago']} days -{$idx} hours"));
    $updatedAt = date('Y-m-d H:i:s', strtotime("-{$s['days_ago']} days +3 hours"));
    $resolvedAt = ($s['status'] === 'resolved' || $s['status'] === 'closed') ? date('Y-m-d H:i:s', strtotime("-1 days")) : null;
    $closedAt   = ($s['status'] === 'closed') ? date('Y-m-d H:i:s', strtotime("-1 days")) : null;
    $closedBy   = $s['closed_by'] ?? ($s['status'] === 'closed' ? 'student' : null);
    $holdUntil  = ($s['status'] === 'on_hold') ? date('Y-m-d', strtotime("+5 days")) : null;

    // Pick assigned staff
    $assignedStaffId = null;
    if (!empty($s['assign'])) {
        foreach ($staffUsers as $su) {
            if ((int)$su['department_id'] === (int)$s['dept']) {
                $assignedStaffId = (int)$su['id'];
                break;
            }
        }
    }

    $checkStmt = $pdo->prepare("SELECT id FROM complaints WHERE reference = ?");
    $checkStmt->execute([$ref]);
    if ($checkStmt->fetch()) {
        continue;
    }

    $ins = $pdo->prepare("
        INSERT INTO complaints (
            reference, student_id, title, description, category_id, location_id, department_id,
            assigned_to, visibility, priority, status, hold_until, created_at, updated_at, resolved_at, closed_at, closed_by
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");
    $ins->execute([
        $ref, $studentId, $s['title'], $s['desc'], $s['cat'], $s['loc'], $s['dept'],
        $assignedStaffId, $s['vis'], $s['pri'], $s['status'], $holdUntil,
        $createdAt, $updatedAt, $resolvedAt, $closedAt, $closedBy
    ]);
    $compId = (int)$pdo->lastInsertId();

    // 1. Audit Events
    add_event($compId, $studentId, 'created', null, 'submitted');
    if ($assignedStaffId) {
        add_event($compId, $adminUser['id'], 'assigned', 'Unassigned', 'Staff Member');
    }
    if (in_array($s['status'], ['in_progress', 'on_hold', 'resolved', 'closed', 'rejected', 'duplicate'], true)) {
        add_event($compId, $assignedStaffId ?: $adminUser['id'], 'status_change', 'assigned', $s['status'], $s['reason'] ?? null);
    }

    // 2. Add realistic comments
    if ($s['status'] !== 'submitted') {
        $pdo->prepare("INSERT INTO comments (complaint_id, user_id, body, is_internal, created_at) VALUES (?, ?, ?, 0, ?)")
            ->execute([$compId, $assignedStaffId ?: $adminUser['id'], "Staff team dispatched to inspect problem on campus.", $updatedAt]);
        $pdo->prepare("INSERT INTO comments (complaint_id, user_id, body, is_internal, created_at) VALUES (?, ?, ?, 1, ?)")
            ->execute([$compId, $assignedStaffId ?: $adminUser['id'], "Parts ordered from central warehouse catalog.", $updatedAt]);
    }

    // 3. Add Affected Count for Public Reports
    if ($s['vis'] === 'public') {
        $numAffected = rand(1, 6);
        for ($af = 0; $af < $numAffected; $af++) {
            $otherStd = $students[($idx + $af + 1) % count($students)];
            $pdo->prepare("INSERT IGNORE INTO affected (complaint_id, user_id, created_at) VALUES (?, ?, ?)")
                ->execute([$compId, $otherStd['id'], $updatedAt]);
        }
    }

    // 4. Attach realistic uploaded photos through upload pipeline for demo complaints
    $demoAssetsDir = SOLVEX_ROOT . '/storage/logs/demo_assets';
    $demoPhoto = match ($idx) {
        1, 10 => $demoAssetsDir . '/tap.jpg',
        11    => $demoAssetsDir . '/vending.jpg',
        12    => $demoAssetsDir . '/fan.jpg',
        default => null
    };

    if ($demoPhoto && file_exists($demoPhoto)) {
        // Run through normal pipeline process_image_upload
        $fakeUpload = [
            'name'     => basename($demoPhoto),
            'type'     => 'image/jpeg',
            'tmp_name' => $demoPhoto,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($demoPhoto)
        ];
        $upRes = process_image_upload($fakeUpload);
        if ($upRes['success']) {
            $pdo->prepare("INSERT INTO complaint_images (complaint_id, stored_name, mime, size, created_at) VALUES (?, ?, ?, ?, ?)")
                ->execute([$compId, $upRes['stored_name'], $upRes['mime'], $upRes['size'], $createdAt]);
        }
    }

    $createdCount++;
}

echo "Successfully seeded {$createdCount} realistic demo complaints across campus facilities!\n";
echo "Demo Accounts:\n";
echo "  Student: student@solvex.edu | Pass: Student@123\n";
echo "  Staff:   staff@solvex.edu   | Pass: Staff@123\n";
echo "  Admin:   admin@solvex.edu   | Pass: Admin@123\n";
echo "═════════════════════════════════════════════════════════════\n";
