<?php
/**
 * Solvex Report — Hostel Domain Migration & Seeder
 * 
 * Adapts categories and location hierarchy to Hostel structure:
 * 1. Six standard hostel categories
 * 2. Hostel -> Floor -> Room / Common Facilities
 * 3. Connects existing complaints safely
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

$pdo = db();
try {
    echo "1. Migrating Categories to 6 Hostel Categories...\n";

    // Deactivate all old categories first (non-destructive)
    $pdo->exec("UPDATE categories SET is_active = 0");

    // Standard 6 Hostel Categories
    $hostelCategories = [
        1 => ['name' => 'Plumbing and Water Supply',    'icon' => 'droplet',     'priority' => 'high',   'target_days' => 2],
        2 => ['name' => 'Electrical Problems',          'icon' => 'lightning',   'priority' => 'high',   'target_days' => 1],
        3 => ['name' => 'Cleaning and Sanitation',      'icon' => 'trash',       'priority' => 'medium', 'target_days' => 2],
        4 => ['name' => 'Room and Furniture Maintenance','icon' => 'house',      'priority' => 'medium', 'target_days' => 3],
        5 => ['name' => 'Wi-Fi and Internet',           'icon' => 'wifi',        'priority' => 'medium', 'target_days' => 1],
        6 => ['name' => 'Mess and Food Facilities',     'icon' => 'cup',         'priority' => 'high',   'target_days' => 1],
    ];

    $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
    $updateStmt = $pdo->prepare("
        UPDATE categories 
        SET name = :name, icon = :icon, default_priority = :priority, target_days = :target_days, is_active = 1
        WHERE id = :id
    ");
    $insertStmt = $pdo->prepare("
        INSERT INTO categories (`id`, `name`, `icon`, `department_id`, `default_priority`, `default_visibility`, `force_private`, `sensitive`, `target_days`, `location_required`, `is_active`)
        VALUES (:id, :name, :icon, 1, :priority, 'private', 1, 0, :target_days, 1, 1)
    ");

    foreach ($hostelCategories as $id => $data) {
        $checkStmt->execute([$id]);
        if ($checkStmt->fetch()) {
            $updateStmt->execute([
                ':name' => $data['name'],
                ':icon' => $data['icon'],
                ':priority' => $data['priority'],
                ':target_days' => $data['target_days'],
                ':id' => $id
            ]);
            echo "   Updated category #$id: {$data['name']}\n";
        } else {
            $insertStmt->execute([
                ':id' => $id,
                ':name' => $data['name'],
                ':icon' => $data['icon'],
                ':priority' => $data['priority'],
                ':target_days' => $data['target_days']
            ]);
            echo "   Inserted category #$id: {$data['name']}\n";
        }
    }

    // Remap any old active complaints belonging to categories > 6 to one of the 6 categories
    $pdo->exec("UPDATE complaints SET category_id = 4 WHERE category_id NOT IN (1,2,3,4,5,6)");

    echo "2. Migrating Location Tree to Hostel -> Floor -> Room...\n";
    // Check if Hostel root locations exist
    // Root level: Boys Hostel (Block A), Boys Hostel (Block B), Girls Hostel (Block A)
    // Child level: Ground Floor, 1st Floor, 2nd Floor, 3rd Floor, Common Areas
    // Room level: Rooms 101-110, 201-210, etc.

    // Let's create a clean, dedicated hostel location structure:
    $hostelTree = [
        'Boys Hostel - Block A' => [
            'Ground Floor' => ['Room 101', 'Room 102', 'Room 103', 'Room 104', 'Common Washroom GF'],
            'First Floor'  => ['Room 201', 'Room 202', 'Room 203', 'Room 204', 'Common Washroom 1F', 'Reading Room'],
            'Second Floor' => ['Room 301', 'Room 302', 'Room 303', 'Room 304', 'Common Washroom 2F'],
            'Common Facilities' => ['Dining Hall / Mess', 'RO Water Dispenser', 'Recreation Room', 'Main Corridor']
        ],
        'Boys Hostel - Block B' => [
            'Ground Floor' => ['Room B-101', 'Room B-102', 'Room B-103', 'Washroom GF'],
            'First Floor'  => ['Room B-201', 'Room B-202', 'Room B-203', 'Washroom 1F'],
            'Second Floor' => ['Room B-301', 'Room B-302', 'Room B-303', 'Washroom 2F'],
        ],
        'Girls Hostel' => [
            'Ground Floor' => ['Room G-101', 'Room G-102', 'Room G-103', 'Common Washroom GF', 'Dining Hall'],
            'First Floor'  => ['Room G-201', 'Room G-202', 'Room G-203', 'Common Washroom 1F', 'Study Room'],
            'Second Floor' => ['Room G-301', 'Room G-302', 'Room G-303', 'Common Washroom 2F']
        ]
    ];

    $findLoc = $pdo->prepare("SELECT id FROM locations WHERE name = ? AND (parent_id <=> ?)");
    $insertLoc = $pdo->prepare("INSERT INTO locations (parent_id, name, type) VALUES (?, ?, ?)");

    foreach ($hostelTree as $hostelName => $floors) {
        $findLoc->execute([$hostelName, null]);
        $hostelId = $findLoc->fetchColumn();
        if (!$hostelId) {
            $insertLoc->execute([null, $hostelName, 'hostel']);
            $hostelId = (int)$pdo->lastInsertId();
            echo "   Created Hostel: $hostelName (#$hostelId)\n";
        }

        foreach ($floors as $floorName => $rooms) {
            $findLoc->execute([$floorName, $hostelId]);
            $floorId = $findLoc->fetchColumn();
            if (!$floorId) {
                $insertLoc->execute([$hostelId, $floorName, 'floor']);
                $floorId = (int)$pdo->lastInsertId();
                echo "      Created Floor: $floorName (#$floorId)\n";
            }

            foreach ($rooms as $roomName) {
                $findLoc->execute([$roomName, $floorId]);
                $roomId = $findLoc->fetchColumn();
                if (!$roomId) {
                    $insertLoc->execute([$floorId, $roomName, 'room']);
                    $roomId = (int)$pdo->lastInsertId();
                }
            }
        }
    }

    echo "3. Updating User Schema for Hostel Residents...\n";
    // Check if hostel_id and room_id exist on users table
    $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('hostel_id', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN hostel_id INT UNSIGNED NULL AFTER department_id");
        echo "   Added users.hostel_id\n";
    }
    if (!in_array('room_no', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN room_no VARCHAR(50) NULL AFTER hostel_id");
        echo "   Added users.room_no\n";
    }

    // Assign demo student to Boys Hostel Block A, Room 204
    $pdo->exec("
        UPDATE users 
        SET hostel_id = (SELECT id FROM locations WHERE name = 'Boys Hostel - Block A' LIMIT 1),
            room_no = 'Room 204',
            hosteller = 1
        WHERE role = 'student' AND (hostel_id IS NULL OR room_no IS NULL)
    ");
    echo "   Updated demo students with default hostel and room.\n";

    echo "4. Streamlining Complaint Statuses...\n";
    // Map existing statuses to standard 5:
    // submitted -> pending
    // on_hold -> in_progress
    // reopened -> pending
    // duplicate / rejected -> closed
    // Note: in complaints table, status column is ENUM('submitted','assigned','in_progress','on_hold','resolved','closed','reopened','rejected','duplicate')
    // Let's modify the ENUM to include 'pending' or map 'submitted' <=> 'pending'
    // To ensure zero breakage of existing code while supporting the 5 statuses:
    // Let's alter ENUM to ('pending', 'submitted', 'assigned', 'in_progress', 'resolved', 'closed', 'reopened', 'rejected')
    $pdo->exec("ALTER TABLE complaints MODIFY COLUMN status ENUM('pending','submitted','assigned','in_progress','on_hold','resolved','closed','reopened','rejected','duplicate') NOT NULL DEFAULT 'pending'");
    $pdo->exec("UPDATE complaints SET status = 'pending' WHERE status = 'submitted'");
    echo "   Complaints status mapped: submitted -> pending.\n";

    echo "\nHostel Domain Migration Completed Successfully!\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}
