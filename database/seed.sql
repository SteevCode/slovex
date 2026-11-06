-- ============================================================
-- Solvex Report — Seed Data
-- Run after schema.sql
-- ============================================================

SET NAMES utf8mb4;

-- ── Departments ─────────────────────────────────────────────
INSERT IGNORE INTO `departments` (`id`, `name`) VALUES
    (1, 'Maintenance'),
    (2, 'Electrical'),
    (3, 'IT'),
    (4, 'Mess Administration'),
    (5, 'Hostel Warden'),
    (6, 'Security');

-- ── Categories (10 from Architecture doc) ───────────────────
INSERT IGNORE INTO `categories` (`id`, `name`, `icon`, `department_id`, `default_priority`, `default_visibility`, `force_private`, `sensitive`, `target_days`, `location_required`, `is_active`) VALUES
    (1,  'Plumbing / Water',    'bi-droplet',       1, 'medium',   'public',  0, 0, 3, 1, 1),
    (2,  'Furniture / Civil',   'bi-house-door',     1, 'medium',   'public',  0, 0, 5, 1, 1),
    (3,  'Electrical Issue',    'bi-lightning',      2, 'high',     'public',  0, 0, 2, 1, 1),
    (4,  'Internet / Wi-Fi',    'bi-wifi',           3, 'high',     'public',  0, 0, 1, 1, 1),
    (5,  'Computer / Printer',  'bi-pc-display',     3, 'medium',   'public',  0, 0, 3, 1, 1),
    (6,  'Food Quality',        'bi-cup-straw',      4, 'medium',   'public',  0, 0, 1, 0, 1),
    (7,  'Mess Hygiene',        'bi-shield-exclamation', 4, 'high', 'public',  0, 0, 2, 0, 1),
    (8,  'Hostel Issue',        'bi-building',       5, 'medium',   'public',  0, 0, 3, 1, 1),
    (9,  'Lost & Found',        'bi-search',         6, 'low',      'private', 1, 0, 7, 0, 1),
    (10, 'Safety / Harassment', 'bi-shield-lock',    6, 'critical', 'private', 1, 1, 1, 0, 1);

-- ── Locations (hierarchical tree) ───────────────────────────
-- Level 0: Campus
INSERT IGNORE INTO `locations` (`id`, `parent_id`, `name`, `type`) VALUES (1, NULL, 'Main Campus', 'campus');

-- Level 1: Zones
INSERT IGNORE INTO `locations` (`id`, `parent_id`, `name`, `type`) VALUES
    (2,  1, 'Academic Block',  'building'),
    (3,  1, 'Library',         'building'),
    (4,  1, 'Canteen',         'building'),
    (5,  1, 'Boys Hostel',     'hostel'),
    (6,  1, 'Girls Hostel',    'hostel'),
    (7,  1, 'Playground',      'area');

-- Academic Block floors
INSERT IGNORE INTO `locations` (`id`, `parent_id`, `name`, `type`) VALUES
    (8,  2, 'Floor 1', 'floor'),
    (9,  2, 'Floor 2', 'floor'),
    (10, 2, 'Floor 3', 'floor');

-- Academic Block rooms/labs
INSERT IGNORE INTO `locations` (`id`, `parent_id`, `name`, `type`) VALUES
    (11, 8,  'Room 101',     'room'),
    (12, 8,  'Room 102',     'room'),
    (13, 8,  'Computer Lab', 'lab'),
    (14, 9,  'Room 201',     'room'),
    (15, 9,  'Room 202',     'room'),
    (16, 9,  'Physics Lab',  'lab'),
    (17, 10, 'Room 301',     'room'),
    (18, 10, 'Room 302',     'room'),
    (19, 10, 'Chemistry Lab','lab');

-- Boys Hostel floors & areas
INSERT IGNORE INTO `locations` (`id`, `parent_id`, `name`, `type`) VALUES
    (20, 5, 'Floor 1', 'floor'),
    (21, 5, 'Floor 2', 'floor'),
    (22, 5, 'Floor 3', 'floor');

INSERT IGNORE INTO `locations` (`id`, `parent_id`, `name`, `type`) VALUES
    (23, 20, 'Common Washroom', 'washroom'),
    (24, 20, 'Corridor',        'corridor'),
    (25, 20, 'Room Area',       'room_area'),
    (26, 21, 'Common Washroom', 'washroom'),
    (27, 21, 'Corridor',        'corridor'),
    (28, 21, 'Room Area',       'room_area'),
    (29, 22, 'Common Washroom', 'washroom'),
    (30, 22, 'Corridor',        'corridor'),
    (31, 22, 'Room Area',       'room_area');

-- Girls Hostel floors & areas
INSERT IGNORE INTO `locations` (`id`, `parent_id`, `name`, `type`) VALUES
    (32, 6, 'Floor 1', 'floor'),
    (33, 6, 'Floor 2', 'floor'),
    (34, 6, 'Floor 3', 'floor');

INSERT IGNORE INTO `locations` (`id`, `parent_id`, `name`, `type`) VALUES
    (35, 32, 'Common Washroom', 'washroom'),
    (36, 32, 'Corridor',        'corridor'),
    (37, 32, 'Room Area',       'room_area'),
    (38, 33, 'Common Washroom', 'washroom'),
    (39, 33, 'Corridor',        'corridor'),
    (40, 33, 'Room Area',       'room_area'),
    (41, 34, 'Common Washroom', 'washroom'),
    (42, 34, 'Corridor',        'corridor'),
    (43, 34, 'Room Area',       'room_area');

-- ── Student Master (30 sample students) ─────────────────────
INSERT IGNORE INTO `student_master` (`register_no`, `full_name`, `department`, `year`, `hosteller`) VALUES
    ('2024CS001', 'Aarav Patel',        'Computer Science',    2, 1),
    ('2024CS002', 'Diya Nair',          'Computer Science',    2, 1),
    ('2024CS003', 'Vihaan Reddy',       'Computer Science',    2, 0),
    ('2024CS004', 'Ananya Gupta',       'Computer Science',    2, 1),
    ('2024CS005', 'Arjun Mehta',        'Computer Science',    2, 0),
    ('2024EC001', 'Saanvi Krishnan',    'Electronics',         2, 1),
    ('2024EC002', 'Aditya Joshi',       'Electronics',         2, 0),
    ('2024EC003', 'Isha Verma',         'Electronics',         2, 1),
    ('2024EC004', 'Reyansh Chauhan',    'Electronics',         2, 0),
    ('2024EC005', 'Kavya Pillai',       'Electronics',         2, 1),
    ('2024ME001', 'Rohan Singh',        'Mechanical',          3, 1),
    ('2024ME002', 'Meera Iyer',         'Mechanical',          3, 0),
    ('2024ME003', 'Kabir Sharma',       'Mechanical',          3, 1),
    ('2024ME004', 'Tanvi Kulkarni',     'Mechanical',          3, 1),
    ('2024ME005', 'Dhruv Agarwal',      'Mechanical',          3, 0),
    ('2023CS001', 'Aarna Das',          'Computer Science',    3, 1),
    ('2023CS002', 'Vivaan Saxena',      'Computer Science',    3, 0),
    ('2023CS003', 'Riya Banerjee',      'Computer Science',    3, 1),
    ('2023EC001', 'Sai Prasad',         'Electronics',         3, 1),
    ('2023EC002', 'Nisha Tiwari',       'Electronics',         3, 0),
    ('2023ME001', 'Karthik Menon',      'Mechanical',          4, 1),
    ('2023ME002', 'Pooja Shetty',       'Mechanical',          4, 0),
    ('2024CV001', 'Harsh Pandey',       'Civil',               2, 1),
    ('2024CV002', 'Sneha Rajan',        'Civil',               2, 1),
    ('2024CV003', 'Aakash Mishra',      'Civil',               2, 0),
    ('2024EE001', 'Lakshmi Narayan',    'Electrical',          2, 1),
    ('2024EE002', 'Pranav Deshmukh',    'Electrical',          2, 0),
    ('2024EE003', 'Shruti Bhatt',       'Electrical',          2, 1),
    ('2024IT001', 'Nikhil Mathur',      'Information Tech',    2, 1),
    ('2024IT002', 'Divya Ranganathan',  'Information Tech',    2, 0);
