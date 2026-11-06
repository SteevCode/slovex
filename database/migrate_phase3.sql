-- ============================================================
-- Solvex Report — Phase 3 Database Migration
-- Run after schema.sql + seed.sql + migrate_phase2.sql
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Settings table (key-value store for lazy rules throttle) ──
ALTER TABLE `complaints` 
    MODIFY COLUMN `status` ENUM('submitted','assigned','in_progress','on_hold','resolved','closed','reopened','rejected','duplicate') NOT NULL DEFAULT 'submitted';

CREATE TABLE IF NOT EXISTS `settings` (
    `key`        VARCHAR(100)    NOT NULL,
    `value`      TEXT            NULL,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the maintenance last-run key
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('maintenance_last_run', '2000-01-01 00:00:00');

-- ── 2. Admin Audit Log ───────────────────────────────────────────
-- Records all admin actions on users and master data
CREATE TABLE IF NOT EXISTS `admin_log` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `actor_id`   INT UNSIGNED    NULL DEFAULT NULL,
    `action`     VARCHAR(100)    NOT NULL,
    `target`     VARCHAR(255)    NOT NULL,
    `detail`     TEXT            NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_actor` (`actor_id`),
    INDEX `idx_created` (`created_at`),
    CONSTRAINT `fk_adminlog_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Notification flag: unassigned-over-24h already notified ──
-- We use a complaint_events row (type='notified_unassigned') to track this
-- so no extra column is needed. The maintenance function checks for its existence.

-- ── 4. Additional indexes for Phase 3 queries ───────────────────
-- Staff queue: filter by assigned_to + status
ALTER TABLE `complaints`
    ADD INDEX IF NOT EXISTS `idx_assigned_status` (`assigned_to`, `status`),
    ADD INDEX IF NOT EXISTS `idx_dept_status_priority` (`department_id`, `status`, `priority`),
    ADD INDEX IF NOT EXISTS `idx_hold_until` (`hold_until`),
    ADD INDEX IF NOT EXISTS `idx_resolved_at` (`resolved_at`);

-- Admin log
ALTER TABLE `admin_log`
    ADD INDEX IF NOT EXISTS `idx_action` (`action`);

-- ── 5. Force-change-password column for new staff accounts ──────
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email_verified_at`,
    ADD COLUMN IF NOT EXISTS `department`           VARCHAR(100) NULL DEFAULT NULL AFTER `must_change_password`,
    ADD COLUMN IF NOT EXISTS `year`                 TINYINT UNSIGNED NULL DEFAULT NULL AFTER `department`,
    ADD COLUMN IF NOT EXISTS `hosteller`            TINYINT(1) NOT NULL DEFAULT 0 AFTER `year`;

