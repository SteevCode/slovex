# Solvex Report — Hostel Complaint and Maintenance Management System

An accessible, reliable, and high-contrast **Hostel Complaint and Maintenance Management System** built with native **PHP 8.2+**, **MySQL / MariaDB**, **Bootstrap 5**, and vanilla **JavaScript**. Designed for college and university hostel administration as an MCA micro project.

---

## 🏢 Project Overview

Solvex Report streamlines the maintenance and repair operations of campus hostels. It replaces chaotic WhatsApp groups and paper logbooks with a transparent, role-based workflow:

```
[Student Reports Hostel Problem]
            │
            ▼
[Warden Reviews & Assigns Maintenance Staff]
            │
            ▼
[Maintenance Staff Updates Progress & Resolves]
            │
            ▼
[Student Verifies Fix & Confirms Closure]
     (or reopens if problem persists)
```

### Key Principles
- **No External Framework Bloat:** Pure native PHP 8+ and PDO with zero runtime composer dependencies.
- **Strict Privacy & Isolation:** Students only view their private complaints; staff view assigned complaints; warden oversees all.
- **High-Contrast Design System:** WCAG AA compliant light and dark modes with clear typography and zero distracting slogans.
- **Immutable Audit Trail:** Append-only event ledger tracking every status transition and remark.

---

## 👥 Three User Roles

### 1. Student
- Register with university register number and log in.
- Set and update hostel building and room number in profile.
- Submit hostel maintenance requests across the 6 standard categories.
- Track real-time repair status, staff remarks, and timeline.
- Verify resolution: **"Confirm Fix"** to close, or **"Still Broken"** to reopen with details.
- Manage profile and change password securely.

### 2. Warden (Administrator)
- Full overview dashboard with open, pending, in-progress, overdue, and resolved metrics.
- Triage intake: filter complaints by category, hostel building, priority, and date.
- Assign complaints to responsible maintenance staff technicians.
- Adjust priorities (`low`, `medium`, `high`, `critical`).
- Manage student accounts and maintenance staff logins (enable/disable without deleting history).
- Manage hostel blocks, floors, rooms, and complaint categories with SLA targets.
- Generate and download filtered complaint reports.

### 3. Maintenance Staff
- Log in to dedicated assigned work queue (`/staff/queue.php`).
- View room location, student contact info, and attached photos.
- Update ticket status: `in_progress` &rarr; `resolved`.
- Enter work completion remarks and part replacement details.
- View personal history of completed maintenance tasks.

---

## 🔧 The 6 Standard Hostel Categories

| ID | Category Name | Default SLA Target | Department |
|---|---|---|---|
| **1** | **Plumbing and Water Supply** | 2 Days | Maintenance & Civil |
| **2** | **Electrical Problems** | 1 Day | Electrical Works |
| **3** | **Cleaning and Sanitation** | 1 Day | Maintenance & Civil |
| **4** | **Room and Furniture Maintenance** | 3 Days | Maintenance & Civil |
| **5** | **Wi-Fi and Internet** | 2 Days | IT & Network Services |
| **6** | **Mess and Food Facilities** | 1 Day | Mess Administration |

---

## 🔑 Demo Credentials

| Role | Email | Password | Identifier | Default Dashboard |
|---|---|---|---|---|
| **Student** | `student@solvex.edu` | `Student@123` | `2024CS100` | `/student/dashboard.php` |
| **Maintenance Staff** | `staff@solvex.edu` | `Staff@123` | `STF001` | `/staff/queue.php` |
| **Hostel Warden (Admin)** | `admin@solvex.edu` | `Admin@123` | `ADM001` | `/admin/dashboard.php` |

---

## 🚀 Quick Start (Running Locally with XAMPP)

### Prerequisites
- **XAMPP** (Apache, MySQL / MariaDB, PHP 8.2+)
- Web browser (Chrome, Firefox, Edge)

### Option A: Direct Master Run Script
In PowerShell from the project root:
```powershell
.\run.ps1
```
*Or double-click `run.bat` on Windows.*

### Option B: PHP Built-in Server
1. Start MySQL from XAMPP Control Panel.
2. Ensure database `solvex_report` is imported (`database/migrate_hostel.sql`).
3. Run PHP development server:
```powershell
& "C:\xampp\php\php.exe" -S 127.0.0.1:8000 router.php
```
4. Open your browser and navigate to: **[http://127.0.0.1:8000](http://127.0.0.1:8000)**

---

## 🧪 Automated Testing & Verification

Run the master test runner to verify 100% test coverage across all hostel workflows:

```powershell
& "C:\xampp\php\php.exe" tests/run.php
```

### Dedicated Hostel Lifecycle Test
```powershell
& "C:\xampp\php\php.exe" tests/hostel_lifecycle_test.php
```
*Verifies role isolation, 5-stage status transition matrix, student confirmation, reopen workflow, and append-only audit trail.*

### Static Security Linter
```powershell
& "C:\xampp\php\php.exe" scripts/lint_security.php
```
*Verifies zero XSS, CSRF, or SQL injection vectors across all PHP files.*

---

## 📂 Project Directory Structure

```
Solvex Report/
├── config.local.php             # Database credentials & local environment settings
├── router.php                   # Built-in server router with MIME streaming
├── run.bat / run.ps1            # 1-click startup scripts
│
├── public/                      # Web root
│   ├── index.php                # High-contrast landing page
│   ├── login.php                # Authentication entry
│   ├── register.php             # Student self-registration
│   ├── complaint.php            # Single complaint timeline & student confirmation
│   ├── assets/
│   │   ├── css/solvex.css       # High-contrast design system (light/dark)
│   │   └── js/solvex.js         # Theme toggle & UI helpers
│   ├── student/                 # Student Portal
│   │   ├── dashboard.php        # Metric cards & recent complaints
│   │   ├── report.php           # 1-page hostel maintenance intake form
│   │   ├── my_complaints.php    # Student's private complaint list
│   │   └── profile.php          # Hostel/room settings & password change
│   ├── staff/                   # Maintenance Staff Portal
│   │   ├── queue.php            # Assigned ticket queue
│   │   └── complaint.php        # Work execution, remarks & resolution
│   └── admin/                   # Hostel Warden Portal
│       ├── dashboard.php        # Executive overview & overdue alerts
│       ├── complaints.php       # All complaints triage & staff assignment
│       ├── users.php            # Student & staff account management
│       ├── master.php           # Hostels, floors, rooms & categories CRUD
│       └── reports.php          # CSV data export
│
├── includes/                    # Backend core logic
│   ├── config.php               # System constants
│   ├── db.php                   # PDO database singleton
│   ├── auth.php                 # Session & role validation
│   ├── permissions.php          # Multi-role access control rules
│   ├── events.php               # State transition matrix & append-only events
│   ├── helpers.php              # Sanitization & escaping (e())
│   ├── csrf.php                 # Token generation and validation
│   └── upload.php               # Photo validation & EXIF metadata stripping
│
├── database/                    # SQL schema & migrations
│   ├── schema.sql               # Base database schema
│   ├── seed.sql                 # Initial seed records
│   ├── migrate_hostel.sql       # Hostel domain migration
│   └── backup_pre_hostel.sql    # Full verified database backup
│
└── tests/                       # Automated test harness
    ├── run.php                  # Master test runner (all suites)
    ├── hostel_lifecycle_test.php# Hostel 5-stage lifecycle tests
    ├── phase2_tests.php         # Student flow & permissions
    └── phase3_tests.php         # Staff & admin concurrency tests
```

---

## 🛡️ Security Architecture

1. **Prepared Statements Everywhere:** 100% of SQL queries use PDO parameter binding with zero string concatenation.
2. **Context-Aware Escaping:** Every dynamic output rendered through `e()` defusing XSS attacks.
3. **Session Hardening:** `Strict` SameSite cookie flags, `HttpOnly`, and cryptographically secure CSRF tokens on all POST requests.
4. **Media Sanitization:** Images re-encoded via GD to remove EXIF GPS data and neutralize embedded script payloads.
5. **Rate Limiting:** Maximum 5 complaint submissions per student per day to prevent spam.
6. **Optimistic Concurrency:** State transitions use `UPDATE ... WHERE id = ? AND status = ?` to prevent race conditions.

---

## 📄 Academic Project Details
- **Project Type:** MCA Micro Project
- **Application:** Hostel Complaint and Maintenance Management System
- **Technologies:** PHP 8.2+, MySQL / MariaDB, Bootstrap 5, Vanilla JavaScript, CSS3
