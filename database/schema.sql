-- ============================================================
-- Solvex Report — Database Schema
-- MySQL 8+ / MariaDB 10.6+
-- Charset: utf8mb4 | Engine: InnoDB
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Departments ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `departments` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(100)    NOT NULL,
    `head_user_id` INT UNSIGNED    NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Users ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `register_no`       VARCHAR(30)     NULL DEFAULT NULL,
    `staff_code`        VARCHAR(30)     NULL DEFAULT NULL,
    `full_name`         VARCHAR(150)    NOT NULL,
    `email`             VARCHAR(255)    NOT NULL,
    `password_hash`     VARCHAR(255)    NOT NULL,
    `role`              ENUM('student','staff','admin') NOT NULL DEFAULT 'student',
    `department_id`     INT UNSIGNED    NULL DEFAULT NULL,
    `status`            ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
    `email_verified_at` DATETIME        NULL DEFAULT NULL,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_register_no` (`register_no`),
    UNIQUE KEY `uk_staff_code` (`staff_code`),
    UNIQUE KEY `uk_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FK for department head (circular reference, add after users exist)
ALTER TABLE `departments`
    ADD CONSTRAINT `fk_departments_head` FOREIGN KEY (`head_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ── 3. Student Master ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `student_master` (
    `register_no`  VARCHAR(30)     NOT NULL,
    `full_name`    VARCHAR(150)    NOT NULL,
    `department`   VARCHAR(100)    NOT NULL,
    `year`         TINYINT UNSIGNED NOT NULL,
    `hosteller`    TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (`register_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Categories ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
    `id`                 INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`               VARCHAR(100)    NOT NULL,
    `icon`               VARCHAR(50)     NOT NULL DEFAULT 'bi-tag',
    `department_id`      INT UNSIGNED    NOT NULL,
    `default_priority`   ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
    `default_visibility` ENUM('public','private') NOT NULL DEFAULT 'public',
    `force_private`      TINYINT(1)      NOT NULL DEFAULT 0,
    `sensitive`          TINYINT(1)      NOT NULL DEFAULT 0,
    `target_days`        SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    `location_required`  TINYINT(1)      NOT NULL DEFAULT 1,
    `is_active`          TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_categories_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Locations (hierarchical) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `locations` (
    `id`        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `parent_id` INT UNSIGNED    NULL DEFAULT NULL,
    `name`      VARCHAR(150)    NOT NULL,
    `type`      VARCHAR(50)     NOT NULL DEFAULT 'area',
    PRIMARY KEY (`id`),
    INDEX `idx_parent` (`parent_id`),
    CONSTRAINT `fk_locations_parent` FOREIGN KEY (`parent_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. Complaints ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `complaints` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `reference`       VARCHAR(20)     NOT NULL,
    `student_id`      INT UNSIGNED    NOT NULL,
    `title`           VARCHAR(200)    NOT NULL,
    `description`     TEXT            NOT NULL,
    `category_id`     INT UNSIGNED    NOT NULL,
    `location_id`     INT UNSIGNED    NULL DEFAULT NULL,
    `department_id`   INT UNSIGNED    NOT NULL,
    `assigned_to`     INT UNSIGNED    NULL DEFAULT NULL,
    `lat`             DECIMAL(10,7)   NULL DEFAULT NULL,
    `lng`             DECIMAL(10,7)   NULL DEFAULT NULL,
    `gps_accuracy_m`  DECIMAL(8,2)    NULL DEFAULT NULL,
    `visibility`      ENUM('public','private') NOT NULL DEFAULT 'public',
    `priority`        ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
    `status`          ENUM('submitted','assigned','in_progress','on_hold','resolved','closed','reopened','rejected','duplicate') NOT NULL DEFAULT 'submitted',
    `duplicate_of_id` INT UNSIGNED    NULL DEFAULT NULL,
    `hold_until`      DATE            NULL DEFAULT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `resolved_at`     DATETIME        NULL DEFAULT NULL,
    `closed_at`       DATETIME        NULL DEFAULT NULL,
    `closed_by`       ENUM('student','auto','admin') NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_reference` (`reference`),
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_department` (`department_id`),
    INDEX `idx_student` (`student_id`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_visibility_status_created` (`visibility`, `status`, `created_at`),
    INDEX `idx_category_location_created` (`category_id`, `location_id`, `created_at`),
    INDEX `idx_student_created` (`student_id`, `created_at`),
    CONSTRAINT `fk_complaints_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_complaints_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_complaints_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_complaints_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_complaints_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_complaints_duplicate` FOREIGN KEY (`duplicate_of_id`) REFERENCES `complaints` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. Complaint Images ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `complaint_images` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `complaint_id` INT UNSIGNED    NOT NULL,
    `stored_name`  VARCHAR(255)    NOT NULL,
    `mime`         VARCHAR(50)     NOT NULL,
    `size`         INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_complaint` (`complaint_id`),
    CONSTRAINT `fk_images_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 8. Complaint Events (append-only audit log) ─────────────
CREATE TABLE IF NOT EXISTS `complaint_events` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `complaint_id` INT UNSIGNED    NOT NULL,
    `actor_id`     INT UNSIGNED    NULL DEFAULT NULL,
    `type`         VARCHAR(50)     NOT NULL,
    `old_value`    TEXT            NULL DEFAULT NULL,
    `new_value`    TEXT            NULL DEFAULT NULL,
    `reason`       TEXT            NULL DEFAULT NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_complaint` (`complaint_id`),
    INDEX `idx_created` (`created_at`),
    CONSTRAINT `fk_events_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_events_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 9. Comments ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `comments` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `complaint_id` INT UNSIGNED    NOT NULL,
    `user_id`      INT UNSIGNED    NOT NULL,
    `body`         TEXT            NOT NULL,
    `is_internal`  TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_complaint` (`complaint_id`),
    INDEX `idx_complaint_internal` (`complaint_id`, `is_internal`),
    CONSTRAINT `fk_comments_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 10. Affected (me-too) ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `affected` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `complaint_id` INT UNSIGNED    NOT NULL,
    `user_id`      INT UNSIGNED    NOT NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_complaint_user` (`complaint_id`, `user_id`),
    CONSTRAINT `fk_affected_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_affected_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 11. Notifications ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED    NOT NULL,
    `complaint_id` INT UNSIGNED    NULL DEFAULT NULL,
    `message`      VARCHAR(500)    NOT NULL,
    `is_read`      TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_read` (`user_id`, `is_read`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_notifications_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 12. Auth Tokens (verify/reset) ──────────────────────────
CREATE TABLE IF NOT EXISTS `auth_tokens` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    NOT NULL,
    `type`       ENUM('verify','reset') NOT NULL,
    `token_hash` VARCHAR(64)     NOT NULL,
    `expires_at` DATETIME        NOT NULL,
    `used_at`    DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_token_hash` (`token_hash`),
    INDEX `idx_user_type` (`user_id`, `type`),
    CONSTRAINT `fk_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 13. Login Attempts ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `identifier`   VARCHAR(255)    NOT NULL,
    `ip`           VARCHAR(45)     NOT NULL,
    `attempted_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `success`      TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `idx_identifier_time` (`identifier`, `attempted_at`),
    INDEX `idx_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
