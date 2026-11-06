# Solvex Report — Technical Architecture Documentation

Solvex Report is a **Hostel Complaint and Maintenance Management System** built with native **PHP 8.2+**, **MySQL / MariaDB**, **Bootstrap 5**, and vanilla **JavaScript**. Designed for university hostels and college accommodation maintenance as an MCA micro project.

---

## 1. System Architecture Overview

```
+─────────────────────────────────────────────────────────────────────────+
|                           CLIENT BROWSERS                               |
|            (Students, Hostel Warden, Maintenance Technicians)           |
+─────────────────────────────────────────────────────────────────────────+
                                    │
                                    │ HTTPS (GET / POST)
                                    ▼
+─────────────────────────────────────────────────────────────────────────+
|                      WEB SERVER & ROUTER (public/)                      |
|  - Security Headers (nosniff, DENY, strict-origin, SameSite=Strict)     |
|  - Request Normalization & Input Sanitization (get_ip, e())             |
|  - Static Asset Delivery (CSS, WebP images, WOFF2 fonts)                |
+─────────────────────────────────────────────────────────────────────────+
                                    │
                  ┌─────────────────┴─────────────────┐
                  ▼                                   ▼
+───────────────────────────────────+ +───────────────────────────────────+
|       SESSION & AUTH LAYER        | |         PERMISSION LAYER          |
|  - init_session() with HttpOnly   | |  - can_view_complaint()           |
|  - CSRF Token Validation          | |  - require_role('student'|'staff' |
|  - Daily Rate Limiting (5/day)    | |    |'admin')                      |
+───────────────────────────────────+ +───────────────────────────────────+
                  │                                   │
                  └─────────────────┬─────────────────┘
                                    ▼
+─────────────────────────────────────────────────────────────────────────+
|                         APPLICATION CORE ENGINE                         |
|  - Hostel Intake Engine: 1-page form, category & room validation        |
|  - Event & State Engine: change_status(), optimistic locking, notices   |
|  - Media Pipeline: GD re-encoding, EXIF/GPS stripping, max 5MB         |
|  - UI Helpers: ui.php, status tags, days_open calculation, dark theme   |
+─────────────────────────────────────────────────────────────────────────+
                                    │
                                    │ Native PDO (EMULATE_PREPARES=false)
                                    ▼
+─────────────────────────────────────────────────────────────────────────+
|                       DATABASE LAYER (MariaDB / MySQL)                  |
|  - solvex_report (utf8mb4_unicode_ci, InnoDB)                           |
|  - Core entities: users, complaints, complaint_events, locations        |
+─────────────────────────────────────────────────────────────────────────+
```

### Core Architectural Principles
1. **Zero Runtime Framework Dependencies:** Pure native PHP 8.2+ and PDO without heavy external vendor libraries. Runs out of the box on standard XAMPP or LAMP setups.
2. **Document Root Isolation:** Web server document root points to `public/`. Application logic (`includes/`), database credentials (`config.local.php`), and uploaded files (`storage/`) reside outside public HTTP access.
3. **Information Hiding Over Error Exposure:** Access violations on private complaints return HTTP **404 Not Found** rather than 403 Forbidden to prevent enumeration of ticket references.
4. **Append-Only Auditability:** All status updates, reassignments, and remarks are appended to `complaint_events`. Historical event rows are never updated or deleted.

---

## 2. Database Schema Design

The hostel management system revolves around 8 core tables:

### 1. `users`
Stores student accounts, maintenance staff technicians, and the hostel warden (administrator).
- `id`: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `email`: VARCHAR(191) UNIQUE
- `password_hash`: VARCHAR(255)
- `full_name`: VARCHAR(100)
- `role`: ENUM('student', 'staff', 'admin')
- `department_id`: INT UNSIGNED NULL (for staff department routing)
- `hostel_id`: INT UNSIGNED NULL (student's assigned hostel building)
- `room_no`: VARCHAR(50) NULL (student's assigned room number)
- `register_no`: VARCHAR(50) NULL (student ID / roll number)
- `status`: ENUM('active', 'disabled')

### 2. `complaints`
Central ledger of all maintenance complaints submitted by students.
- `id`: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `reference`: VARCHAR(32) UNIQUE (format: `SVX-YYYY-NNNNN`)
- `student_id`: INT UNSIGNED (FK &rarr; `users.id`)
- `category_id`: INT UNSIGNED (FK &rarr; `categories.id`)
- `location_id`: INT UNSIGNED NULL (FK &rarr; `locations.id`)
- `department_id`: INT UNSIGNED (FK &rarr; `departments.id`)
- `assigned_to`: INT UNSIGNED NULL (FK &rarr; `users.id`, assigned maintenance staff)
- `title`: VARCHAR(100)
- `description`: TEXT (up to 1500 chars)
- `visibility`: ENUM('public', 'private') (Hostel complaints default to 'private')
- `priority`: ENUM('low', 'medium', 'high', 'critical')
- `status`: ENUM('pending', 'submitted', 'assigned', 'in_progress', 'on_hold', 'resolved', 'closed', 'reopened', 'rejected', 'duplicate')
- `closed_by`: ENUM('student', 'admin', 'auto') NULL
- `created_at`, `updated_at`, `resolved_at`, `closed_at`: DATETIME

### 3. `complaint_events`
Immutable, append-only audit trail of every status transition and remark.
- `id`: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `complaint_id`: INT UNSIGNED (FK &rarr; `complaints.id`)
- `actor_id`: INT UNSIGNED NULL (FK &rarr; `users.id`)
- `type`: ENUM('created', 'status_change', 'assigned', 'priority_change', 'department_change', 'reopened', 'closed')
- `old_value`: VARCHAR(100) NULL
- `new_value`: VARCHAR(100) NULL
- `reason`: TEXT NULL (mandatory for rejection, hold, resolution, and reopening)
- `created_at`: DATETIME

### 4. `complaint_images`
Sanitized photo attachments (max 2 per complaint).
- `id`: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `complaint_id`: INT UNSIGNED (FK &rarr; `complaints.id`)
- `stored_name`: VARCHAR(64) UNIQUE (cryptographically random hex filename)
- `mime`: VARCHAR(50) ('image/jpeg', 'image/png', 'image/webp')
- `size`: INT UNSIGNED (max 5 MB)
- `created_at`: DATETIME

### 5. `categories`
The 6 standard hostel maintenance categories with SLA targets.
- `id`: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `name`: VARCHAR(100) (e.g. "Plumbing and Water Supply")
- `department_id`: INT UNSIGNED (FK &rarr; `departments.id`)
- `default_priority`: ENUM('low', 'medium', 'high', 'critical')
- `target_days`: INT UNSIGNED (SLA working days: 1 to 3)
- `is_active`: TINYINT(1) DEFAULT 1

### 6. `locations`
Hierarchical tree for hostel buildings, floors, rooms, and common amenities.
- `id`: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `parent_id`: INT UNSIGNED NULL (self-referencing FK for tree hierarchy)
- `name`: VARCHAR(100) (e.g. "Boys Hostel Block A", "Ground Floor", "Dining Hall / Mess")
- `type`: ENUM('campus', 'hostel', 'floor', 'room', 'washroom', 'corridor', 'area')

### 7. `comments`
Public student/staff communication thread and confidential internal notes.
- `id`: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `complaint_id`: INT UNSIGNED (FK &rarr; `complaints.id`)
- `user_id`: INT UNSIGNED (FK &rarr; `users.id`)
- `body`: TEXT
- `is_internal`: TINYINT(1) DEFAULT 0 (if 1, visible strictly to staff and warden)
- `created_at`: DATETIME

### 8. `notifications`
In-app notification messages delivered to students, staff, and warden.
- `id`: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `user_id`: INT UNSIGNED (FK &rarr; `users.id`)
- `complaint_id`: INT UNSIGNED NULL
- `message`: VARCHAR(255)
- `is_read`: TINYINT(1) DEFAULT 0
- `created_at`: DATETIME

---

## 3. The 5-Stage Complaint Lifecycle State Machine

```
┌──────────────┐
│   PENDING    │  Student submits maintenance issue
└──────┬───────┘
       │ [Warden Assigns Staff]
       ▼
┌──────────────┐
│   ASSIGNED   │  Assigned to technician in responsible department
└──────┬───────┘
       │ [Staff Starts Work]
       ▼
┌──────────────┐
│ IN_PROGRESS  │  Technician physically inspecting/repairing
└──────┬───────┘
       │ [Staff Marks Completed with Repair Note]
       ▼
┌──────────────┐
│   RESOLVED   │  Repair done, awaiting student verification
└──────┬───────┘
       ├─────────────────────────────────┐
       │ [Student Confirms Fix]          │ [Student: Issue Persists]
       ▼                                 ▼
┌──────────────┐                  ┌──────────────┐
│    CLOSED    │                  │   REOPENED   │
│(closed_by=   │                  └──────┬───────┘
│  'student')  │                         │ [Warden/Staff Resumes]
└──────────────┘                         └───────────&rarr; to IN_PROGRESS
```

### Transition Concurrency Lock
To avoid double-submit race conditions when staff or students click simultaneously:
```sql
UPDATE complaints 
SET status = :new_status, updated_at = NOW() 
WHERE id = :id AND status = :expected_old_status
```
If two users perform an action simultaneously, exactly one row updates (`rowCount() === 1`), and the other cleanly rolls back with a concurrency conflict notice.

---

## 4. Security & Privacy Defenses

1. **Strict Input Sanitization:** All forms pass CSRF validation (`csrf_verify()`) and rate limiting (max 5 submissions per student per day).
2. **Context-Aware Escaping:** `e()` defuses HTML special characters (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
3. **EXIF & GPS Stripping:** Uploaded photos are decoded and re-encoded using PHP GD before storage, discarding embedded GPS tags, device identifiers, and metadata.
4. **Restricted File Serving:** Photos are stored in `/storage/uploads/` outside the web document root and served via authentication-gated PHP streaming.
5. **No Dangerous Cron / Daemon Dependencies:** Zero background processes or daemon dependencies; all actions are real-time, event-driven, and transaction-safe.
