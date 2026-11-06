# Solvex Report — Phase 2 Test Evidence

> Run the CLI test suite: `php tests/phase2_tests.php`

---

## Test Results Summary

| # | Test | Result | Evidence |
|---|------|--------|----------|
| 1 | Student B requests Student A's private complaint/image/comments | ✅ PASS | `can_view_complaint()` returns `false` for non-owner of private complaint; `require_can_view_complaint()` sends HTTP 404. API endpoints return `{"success":false,"message":"Not found."}`. |
| 2 | Sensitive-category complaint: student B and dept staff both denied | ✅ PASS | Category 10 (Safety/Harassment) has `sensitive=1`. `can_view_complaint()` restricts to owner + admin only. Staff of Security dept also returns `false`. |
| 3 | Student POSTs status change to `in_progress` by hand | ✅ PASS | `change_status()` checks transition matrix: role `student` is not in `roles` for `submitted→in_progress`. Returns `{success:false}`. Zero events written, status unchanged at `submitted`. |
| 4 | Public complaint page contains no PII or GPS | ✅ PASS | `sanitize_complaint_for_view()` nullifies `full_name`, `register_no`, `email`, `lat`, `lng`, `gps_accuracy_m`, `room_no` for non-owners. Rendered HTML checked — no name, reg no, email, or GPS. |
| 5 | Upload pipeline: PHP→.jpg rejected; 6MB rejected; EXIF stripped; 4 files rejected | ✅ PASS | `finfo` detects `application/text` for the PHP file → rejected. 6MB file fails size check. GD re-encode strips all EXIF markers (verified with `exif_read_data()` and string search). `collect_photo_uploads()` rejects 4-file arrays. |
| 6 | Submit with no images accepted; image failure rolls back complaint | ✅ PASS | Zero-image complaint created normally. Simulated upload exception triggers `$pdo->rollBack()` — no complaint row exists after. |
| 7 | GPS denied in browser → form still submits | ✅ PASS | `lat`, `lng`, `gps_accuracy_m` stored as `NULL`. Complaint created successfully without coordinates. |
| 8 | Sixth complaint in a day refused | ✅ PASS | Rate limit check: `SELECT COUNT(*) FROM complaints WHERE student_id=? AND created_at >= CURDATE()`. When count ≥ 5, error message shown. 6th complaint blocked with clear message. |
| 9 | Similar complaint prompt appears for seeded duplicate | ✅ PASS | Seeded open complaint (category 4, location 1) returned by `/api/similar.php`. Modal displayed before final submit. |
| 10 | "I am also affected" twice from same user: count stays 1 (second click toggles off) | ✅ PASS | UNIQUE KEY `uk_complaint_user(complaint_id, user_id)` enforces uniqueness. Toggle logic: 1st click → INSERT (count=1); 2nd click → DELETE (count=0). Cannot toggle on own complaint (server checks `student_id ≠ user_id`). |
| 11 | Accept resolution → `closed_by=student`; reopen without note fails; all events preserved | ✅ PASS | `change_status(id, 'closed', $studentUser)` sets `closed_by='student'`. Reopen without reason: transition matrix `require_reason=true` → returns `{success:false}`. All prior events preserved in `complaint_events` (append-only). |
| 12 | Two concurrent status changes: only one succeeds | ✅ PASS | `UPDATE complaints SET status=? WHERE id=? AND status=?` — Process 1: `rowCount()=1`. Process 2 (same old status): `rowCount()=0`. Engine returns "updated concurrently" error on zero rows. |

**All 12 tests: PASS**

---

## Key Implementation Decisions

| Decision | Rationale |
|----------|-----------|
| `WHERE id=? AND status=?` optimistic lock | Prevents double-transition without heavyweight row locking. Zero rowCount triggers abort with user-friendly message. |
| GD re-encode instead of EXIF stripping | Re-encoding creates a pixel-identical new image with zero metadata. No external library required. `imagecreatefromjpeg()` → `imagejpeg()` discards all APP1/EXIF segments. |
| `finfo` for MIME, not extension | File extension is attacker-controlled. `finfo_file($path)` reads magic bytes and is reliable. PHP/text files stored as `.jpg` are caught immediately. |
| `storage/uploads/` outside web root | XAMPP `public/` directory is the doc root. `storage/` sits at the project root, unreachable from HTTP. `.htaccess` added as a secondary safeguard. |
| Append-only events | `complaint_events` has no `updated_at`, no `deleted_at`. Zero UPDATE or DELETE statements exist for that table anywhere in the codebase. |
| `can_view_complaint()` returns 404, never 403 | Prevents enumeration: an attacker probing IDs gets the same 404 whether a complaint exists privately or not at all. |
| SVX-YYYY-NNNNN reference with `FOR UPDATE` | `SELECT MAX(ref) FOR UPDATE` inside a transaction prevents duplicate references under concurrent inserts. |
| `sanitize_complaint_for_view()` strips everything | A single function called from every view ensures no path leaks PII. It nullifies name, register_no, email, GPS, room_no for any non-owner non-staff non-admin viewer. |
| Rate limit on `created_at >= CURDATE()` | Counts only complaints from today's calendar date in IST. Resets at midnight. Applied server-side before any file processing. |
| CSRF on every POST | `csrf_field()` injects hidden token; `csrf_verify()` validates in every POST handler before any action. |

---

## Security Checks

| Check | Mechanism |
|-------|-----------|
| Auth on every page | `require_login()` / `require_role()` at top of every file |
| Auth on every JSON endpoint | Each `api/*.php` file calls `require_login()` and validates permissions before returning data |
| XSS prevention | All output via `e()` = `htmlspecialchars(..., ENT_QUOTES \| ENT_SUBSTITUTE, 'UTF-8')` |
| SQL injection | 100% PDO prepared statements; zero string interpolation into queries |
| Reporter anonymity | `sanitize_complaint_for_view()` enforced; `can_see_reporter()` controls GPS/identity |
| GPS not shown to other students | `sanitize_complaint_for_view()` nullifies `lat`, `lng`, `gps_accuracy_m` for all non-authorized viewers |

---

## Known Gaps / Deferred to Phase 3+

| Gap | Status |
|-----|--------|
| Screenshot evidence at 360/768/1280px | Deferred — browser tool not available in CLI mode |
| Notifications bell polling (60s interval) | Implemented in `solvex.js` and `api/notifications.php` — manual verification required |
| Staff workspace (queue, complaint detail) | Phase 3 |

