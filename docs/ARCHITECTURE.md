# Solvex Report — Technical Architecture Documentation

Solvex Report is an institutional campus problem reporting, department triage, and civic accountability platform built for colleges and university hostel environments. It follows a clean native PHP 8.2+ architecture with strict multi-role permission boundaries, atomic state transitions, and zero background daemon dependencies.

---

## 1. System Overview & Architecture Philosophy

```
+─────────────────────────────────────────────────────────────────────────+
|                           CLIENT BROWSERS                               |
|        (Students, Department Staff, Institutional Administrators)       |
+─────────────────────────────────────────────────────────────────────────+
                                    │
                                    │ HTTPS (GET / POST / AJAX)
                                    ▼
+─────────────────────────────────────────────────────────────────────────+
|                      WEB SERVER & ROUTER (public/)                      |
|  - Security Headers (nosniff, DENY, strict-origin, Permissions-Policy)  |
|  - Request Normalization & Trusted Proxy Filtering (get_ip)             |
|  - Static Asset Delivery (CSS, WebP images, WOFF2 fonts)                |
+─────────────────────────────────────────────────────────────────────────+
                                    │
                  ┌─────────────────┴─────────────────┐
                  ▼                                   ▼
+───────────────────────────────────+ +───────────────────────────────────+
|       SESSION & AUTH LAYER        | |         PERMISSION LAYER          |
|  - init_session() with HttpOnly   | |  - can_view_complaint()           |
|  - CSRF Token Validation          | |  - can_see_reporter() (PII Guard) |
|  - Rate Limiting (Login/Intake)   | |  - require_role('student'|'staff')|
+───────────────────────────────────+ +───────────────────────────────────+
                  │                                   │
                  └─────────────────┬─────────────────┘
                                    ▼
+─────────────────────────────────────────────────────────────────────────+
|                         APPLICATION CORE ENGINE                         |
|  - Intake Engine: Validation, similarity search, upload sanitization    |
|  - Event Engine: change_status(), optimistic locking, notifications     |
|  - Lazy Maintenance: 60s atomic locks, auto-closure of resolved tickets |
|  - UI Engine: ui.php, icon map, status tags, days_open calculation      |
+─────────────────────────────────────────────────────────────────────────+
                                    │
                                    │ Native PDO (EMULATE_PREPARES=false)
                                    ▼
+─────────────────────────────────────────────────────────────────────────+
|                       DATABASE LAYER (MariaDB / MySQL)                  |
|  - solvex_report (utf8mb4_unicode_ci, InnoDB)                           |
|  - Append-Only Audit Ledgers (complaint_events, admin_log)              |
+─────────────────────────────────────────────────────────────────────────+
```

### Core Design Principles
1. **Zero Framework Bloat:** Native PHP 8.2+ using vanilla PDO. Runs on standard XAMPP, shared cPanel hosting, or Docker containers without Node.js build pipelines or Composer dependencies.
2. **Document Root Isolation:** The web server document root points to `public/`. Application logic (`includes/`), database credentials (`config.local.php`), and uploaded binaries (`storage/`) reside strictly outside public HTTP reach.
3. **Information Hiding Over Error Exposure:** Access violations on private or sensitive tickets return HTTP **404 Not Found** instead of 403 Forbidden to prevent malicious enumeration of ticket IDs.
4. **Append-Only Auditability:** Status changes, staff reassignments, and administrative mutations are immutably recorded in `complaint_events` and `admin_log`. Zero `UPDATE` or `DELETE` endpoints exist for audit tables.

---

## 2. Directory Layout & Layer Responsibilities

| Path | Access | Responsibility |
|---|---|---|
| `public/` | Public Web Root | Entry points (`index.php`, `feed.php`, `login.php`), student endpoints (`student/`), staff queue (`staff/`), admin console (`admin/`), JSON endpoints (`api/`), and static assets (`assets/`). |
| `public/image.php` | Authenticated Proxy | Streams uploaded photos only after running `can_view_complaint()`. Directly accessing the storage folder via HTTP is denied. |
| `includes/` | Private System | Core infrastructure: `db.php` (PDO singleton), `auth.php` (sessions & passwords), `csrf.php` (CSRF tokens), `events.php` (state transitions), `permissions.php` (RBAC), `upload.php` (GD sanitization), `maintenance.php` (lazy engine), and `ui.php` (components). |
| `storage/uploads/` | Protected Storage | Re-encoded JPG images saved under randomized hexadecimal hashes. Denied script execution via `.htaccess`. |
| `storage/logs/` | Protected Logs | Application error logs and diagnostic scratch directories. |
| `database/` | Private Schemas | `schema.sql`, default seeds (`seed.sql`), migrations (`migrate_phase2.sql`, `migrate_phase3.sql`), and realistic demo seeder (`demo_seed.php`). |
| `tests/` | Private Harness | Automated end-to-end verification suites (`run.php`, `phase2_tests.php`, `phase3_tests.php`, `remediation_tests.php`). |

---

## 3. Database Schema Architecture

```
                  ┌───────────────────┐
                  │   departments     │
                  └─────────┬─────────┘
                            │ 1:N
                  ┌─────────▼─────────┐
                  │    categories     │
                  └─────────┬─────────┘
                            │ 1:N
 ┌──────────────┐ 1:N     ┌─▼─────────────────┐     1:N ┌───────────────────┐
 │    users     ├─────────►    complaints     ◄─────────┤     locations     │
 └──────┬───────┘         └─┬──────┬────────┬─┘         └───────────────────┘
        │                   │      │        │
    1:N │               1:N │  1:N │    1:N │
        ▼                   │      │        ▼
 ┌──────────────┐           │      │   ┌───────────────────┐
 │auth_tokens / │           │      │   │ complaint_photos  │
 │notifications │           │      │   └───────────────────┘
 └──────────────┘           │      ▼
                            │  ┌───────────────────┐
                            │  │ complaint_comments│
                            │  └───────────────────┘
                            ▼
                       ┌───────────────────┐
                       │ complaint_events  │
                       └───────────────────┘
```

### Table Definitions & Key Attributes

1. **`users`:** Stores authenticated entities (`student`, `staff`, `admin`). Includes `identifier` (e.g. `2024CS100`, `STF001`), `password_hash`, `department_id` (for staff), and account status (`active`, `pending`, `disabled`).
2. **`student_master`:** Pre-authorized institutional register with `register_no`, `full_name`, `email`, and `department_id`. Registration verifies against this table to prevent unauthorized signups.
3. **`complaints`:** Primary incident record.
   - Identifier: Unique reference string in format `SVX-YYYY-NNNNN` (e.g. `SVX-2026-00042`).
   - Fields: `title`, `description`, `category_id`, `department_id`, `location_id`, `specific_location`, `status`, `priority`, `visibility` (`public` or `private`), `sensitive` (boolean), `lat`/`lng` (GPS coordinates), `created_at`, `resolved_at`, `closed_at`, `closed_by` (`student`, `staff`, `admin`, `auto`).
4. **`complaint_events`:** Append-only ledger recording every transition:
   - `complaint_id`, `user_id` (actor), `action` (e.g. `created`, `assigned`, `status_changed`, `reassigned`, `marked_duplicate`), `old_status`, `new_status`, `notes`, `created_at`.
5. **`complaint_comments`:** Discussion thread entries. Flags `is_internal = 1` for staff-only notes.
6. **`complaint_photos`:** Metadata pointing to re-encoded files stored in `storage/uploads/`.
7. **`complaint_affected`:** Tracks student toggles for *"I am also affected"*. Composite primary key `(complaint_id, user_id)` guarantees idempotency.
8. **`admin_log`:** Append-only audit record tracking administrative modifications to users, categories, departments, and system settings.

---

## 4. Complaint Lifecycle State Machine

The incident lifecycle follows a deterministic transition matrix implemented in `includes/events.php`:

```
                       ┌───────────────┐
                       │   submitted   │
                       └───────┬───────┘
                               │
               ┌───────────────┼───────────────┐
               ▼               ▼               ▼
        ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
        │  assigned   │ │  rejected   │ │  duplicate  │
        └──────┬──────┘ └─────────────┘ └─────────────┘
               │
               ▼
        ┌─────────────┐
   ┌───►│ in_progress │◄──┐
   │    └──────┬──────┘   │
   │           │          │
   │           ▼          │
┌──┴────────┐ ┌──────────┴──┐
│  on_hold  │ │   resolved   │
└───────────┘ └──────┬──────┘
                     │
         ┌───────────┴───────────┐
         ▼                       ▼
  ┌─────────────┐         ┌─────────────┐
  │   closed    │         │  reopened   │
  └─────────────┘         └─────────────┘
```

### Transition Authority Matrix

| From Status | Permitted Target Statuses | Authorized Roles | Mandatory Requirements |
|---|---|---|---|
| `submitted` | `assigned`, `in_progress`, `rejected`, `duplicate` | Staff (Dept), Admin | `rejected` and `duplicate` require reason notes. |
| `assigned` | `in_progress`, `on_hold`, `rejected`, `duplicate` | Assigned Staff, Admin | `on_hold` requires future hold date and reason. |
| `in_progress` | `on_hold`, `resolved`, `rejected`, `duplicate` | Assigned Staff, Admin | `resolved` requires resolution summary notes. |
| `on_hold` | `in_progress`, `resolved` | Assigned Staff, Admin | - |
| `resolved` | `closed` | Student (Owner), Admin, Auto-Close | Student clicks "Accept Resolution" (`closed_by = 'student'`). |
| `resolved` | `reopened` | Student (Owner), Admin | Student clicks "Still Broken" with mandatory explanation note. |
| `reopened` | `assigned`, `in_progress` | Staff (Dept), Admin | - |
| `closed` | `reopened` | Admin Only | Administrative justification required. |

### Optimistic Concurrency Control
To prevent triage collisions when multiple staff triage the same queue item simultaneously:
```sql
UPDATE complaints 
SET status = :new_status, updated_at = NOW() 
WHERE id = :id AND status = :expected_old_status;
```
If two staff submit transitions concurrently, exactly one succeeds (`rowCount = 1`). The second receives `rowCount = 0` and is safely notified with an advice message to refresh their view.

---

## 5. Security & Permission Architecture

### 1. Role-Based Access Control (RBAC)
- **`student`:** Allowed access to `/student/*`, `/feed.php`, `/complaint.php`, `/accountability.php`. Restricted from accessing any `/staff/*` or `/admin/*` routes.
- **`staff`:** Restricted to tickets matching their assigned `department_id`. Cannot access administrative controls under `/admin/*`.
- **`admin`:** Global read and write access across all departments, user administration, CSV imports, and audit ledgers.

### 2. Visibility & Anonymity Pipeline
Visibility is governed by `can_view_complaint()` and `sanitize_complaint_for_view()` in `includes/permissions.php`:

| Incident Characteristic | Student (Owner) | Student (Peer) | Department Staff | System Admin |
|---|---|---|---|---|
| **Public Complaint** | Full Details | Sanitized (`Anonymous student`, GPS suppressed) | Full Details | Full Details |
| **Private Complaint** | Full Details | **404 Not Found** | Full Details | Full Details |
| **Sensitive Complaint** (`sensitive = 1`) | Full Details | **404 Not Found** | **404 Not Found** (Staff Denied) | Full Details |
| **Internal Notes** (`is_internal = 1`) | **Hidden** | **Hidden** | Visible | Visible |

### 3. Media Upload Hardening
1. **Magic Byte Verification:** Inspects binary headers via `finfo_file(FILEINFO_MIME_TYPE)`. Rejects disguised executables (e.g. PHP scripts renamed to `.jpg`).
2. **GD Re-Encoding:** Every valid image is completely decoded and re-rendered via `imagecreatefromjpeg()` / `imagepng()` / `imagewebp()` and output as a sanitized JPEG at 85% quality.
3. **Metadata Stripping:** Re-encoding permanently removes all EXIF, GPS coordinates, and camera serial metadata.
4. **Isolated Storage:** Uploaded files are assigned random hexadecimal names and saved in `storage/uploads/` where execution is disabled via Apache configuration.

---

## 6. Lazy Maintenance Engine

Traditional systems rely on system crons (e.g. `crontab -e`) which fail on shared PHP hosting. Solvex Report utilizes a built-in lazy maintenance trigger:

1. **Invocation:** Called at the start of authenticated staff and admin page loads (`run_maintenance()`).
2. **Atomic Lock:** Uses an atomic database setting `maintenance_last_run`. If the timestamp is less than 60 seconds old, execution terminates immediately.
3. **Auto-Closure Routine:** Queries all complaints where `status = 'resolved'` and `resolved_at <= NOW() - INTERVAL 5 DAY`. Transitions matched records to `closed` with `closed_by = 'auto'` and logs an event.
4. **Hold Expiry Alerts:** Evaluates on-hold tickets where `hold_until <= NOW()`, dispatching notifications to assigned staff to resume work.

---

## 7. JSON API Endpoints

All AJAX endpoints enforce `csrf_verify_ajax()` without rotating session tokens, maintaining multi-tab stability:

- **`GET /api/locations.php?parent_id=X`:** Returns cascading sub-locations (Campus &rarr; Building &rarr; Floor &rarr; Room).
- **`GET /api/similar.php?category_id=X&location_id=Y`:** Identifies open complaints filed in the same location within the past 14 days to prevent duplicate tickets.
- **`POST /api/affected.php`:** Idempotently toggles student affected state (*"I am also affected"*).
- **`GET /api/comments.php?complaint_id=X`:** Fetches discussion thread. Suppresses `is_internal = 1` comments when called by students.
- **`POST /api/comments.php`:** Submits discussion notes with role-based internal flag validation.
- **`GET /api/notifications.php`:** Fetches unread notifications for the active user badge.
