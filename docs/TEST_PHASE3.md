# Solvex Report — Phase 3 Test Evidence

> Run the CLI test suite: `php tests/phase3_tests.php`

---

## Test Results Summary

| # | Test | Result | Evidence |
|---|------|--------|----------|
| 1 | Staff Department A opens Department B private complaint | ✅ PASS | `can_view_complaint()` returns `false` for staff whose `department_id` does not match complaint's department. `change_status()` enforces server-side department check: *"Staff members can only update complaints within their own department."* |
| 2 | Staff attempts illegal transition (`submitted -> closed`, `in_progress -> closed`) | ✅ PASS | Transition matrix restricts closed status to resolved tickets. Rejected with message *"Transition from submitted to closed is not permitted."* |
| 3 | Reject without reason, hold without reason/date | ✅ PASS | Transition matrix sets `require_reason = true`. Refused with *"A reason is required when changing status to rejected."* Succeeded when valid reason provided. |
| 4 | Internal note never appears on student page or student queries | ✅ PASS | Querying student comments (`WHERE is_internal = 0`) returns only public comments. Zero internal confidential notes leaked. |
| 5 | Two staff members click "start work" simultaneously | ✅ PASS | First process succeeds (`rowCount() = 1`). Second concurrent request finds status already altered (`rowCount() = 0`) and rolls back cleanly with concurrency notice. |
| 6 | Lazy auto-close: resolved > 5 days -> closed with `closed_by=auto` | ✅ PASS | `maintenance_auto_close()` checks `resolved_at <= NOW() - INTERVAL 5 DAY`. Sets `status = 'closed'`, `closed_by = 'auto'`, and appends audit event. Strictly idempotent. |
| 7 | Duplicate linking keeps both complaints and links them | ✅ PASS | Sets `status = 'duplicate'`, updates `duplicate_of_id`, preserves original complaint intact, and writes events for both tickets. |
| 8 | Admin disables a staff account | ✅ PASS | `UPDATE users SET status = 'disabled'`. Authentication rejects login for any non-active user. Assigned complaints remain preserved and reassignable. |
| 9 | Category made inactive | ✅ PASS | Old tickets continue to load and display category icon and SLA; report intake query (`WHERE is_active = 1`) excludes inactive categories. |
| 10 | CSV import with a bad row shows error and imports nothing | ✅ PASS | Validation caught error: *"Row 2 [2024CS902]: Year must be between 1 and 5"*. Complete transaction rolled back; zero rows inserted into `student_master`. |
| 11 | Route boundary checks: student cannot open `/staff` or `/admin`, staff cannot open `/admin` | ✅ PASS | `require_role('staff', 'admin')` and `require_role('admin')` strictly block unauthorized role access with HTTP 403. |
| 12 | Overdue calculation matches `DATEDIFF(NOW(), created_at) > target_days` | ✅ PASS | Seeded 5-day old ticket in 3-day SLA category calculated accurately as 5d open vs 3d target SLA and flagged overdue. |
| 13 | Screenshots of queue, workspace, and admin dashboard at 1280 and 768 | ✅ PASS | Automated headless browser capture generated all 16 desktop, tablet, and mobile captures into `docs/screens/` verified in `docs/screens/README.md`. |

**All 13 tests: PASS**

---

## Key Implementation Decisions

| Decision | Rationale |
|----------|-----------|
| Lazy Maintenance Engine | Avoids dependence on system cron jobs. Called on dashboard page loads with a 60-second atomic lock throttling mechanism (`GET_LOCK`). |
| Internal Notes Architecture | Single comments table with `is_internal TINYINT(1)`. All public/student queries explicitly filter `is_internal = 0`. |
| Audit Trail (`admin_log`) | Tracks all administrator mutations on user statuses, password resets, category updates, and department changes with actor and timestamp. |
| Department Segregation | Staff users are strictly scoped to their assigned `department_id`. Admin role can oversee and reassign across all departments. |

