-- ============================================================
-- Solvex Report — Hostel Refactoring Migration
-- Adapts schema to Hostel Complaint & Maintenance System
-- ============================================================

-- 1. Ensure user has hostel_id and room_no
ALTER TABLE `users` 
    ADD COLUMN IF NOT EXISTS `hostel_id` INT UNSIGNED NULL AFTER `department_id`,
    ADD COLUMN IF NOT EXISTS `room_no` VARCHAR(50) NULL AFTER `hostel_id`;

-- 2. Expand status ENUM to support 'pending' as default intake status
ALTER TABLE `complaints` 
    MODIFY COLUMN `status` ENUM('pending','submitted','assigned','in_progress','on_hold','resolved','closed','reopened','rejected','duplicate') NOT NULL DEFAULT 'pending';

-- 3. Map any existing submitted complaints to pending
UPDATE `complaints` SET `status` = 'pending' WHERE `status` = 'submitted';

-- 4. Set the 6 standard Hostel Categories
UPDATE `categories` SET `is_active` = 0;

INSERT INTO `categories` (`id`, `name`, `icon`, `department_id`, `default_priority`, `default_visibility`, `force_private`, `sensitive`, `target_days`, `location_required`, `is_active`)
VALUES
    (1, 'Plumbing and Water Supply', 'droplet', 1, 'high', 'private', 1, 0, 2, 1, 1),
    (2, 'Electrical Problems', 'lightning', 2, 'high', 'private', 1, 0, 1, 1, 1),
    (3, 'Cleaning and Sanitation', 'trash', 1, 'medium', 'private', 1, 0, 2, 1, 1),
    (4, 'Room and Furniture Maintenance', 'house', 1, 'medium', 'private', 1, 0, 3, 1, 1),
    (5, 'Wi-Fi and Internet', 'wifi', 3, 'medium', 'private', 1, 0, 1, 1, 1),
    (6, 'Mess and Food Facilities', 'cup', 4, 'high', 'private', 1, 0, 1, 1, 1)
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `icon` = VALUES(`icon`),
    `default_priority` = VALUES(`default_priority`),
    `target_days` = VALUES(`target_days`),
    `is_active` = 1;

