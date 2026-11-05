-- Phase 2 list/query indexes (safe to re-run: duplicate-key errors are ignored by the installer).
ALTER TABLE `complaints`
    ADD INDEX `idx_visibility_status_created` (`visibility`, `status`, `created_at`),
    ADD INDEX `idx_category_location_created` (`category_id`, `location_id`, `created_at`),
    ADD INDEX `idx_student_created` (`student_id`, `created_at`);

ALTER TABLE `comments`
    ADD INDEX `idx_complaint_internal` (`complaint_id`, `is_internal`);
