# Solvex Report — Campus Incident Resolution System

Solvex Report is a modern, transparent campus problem reporting, department triage, and civic accountability system built for college and university hostel environments.

---

## 🌟 Key Features

- **3-Step Guided Intake:** Visual category tag cards, cascading location selectors, Geolocation API coordinate capture, and drag-and-drop photos.
- **Upload Pipeline Security:** Re-encodes uploaded images via GD to strip EXIF and GPS markers; prevents arbitrary file uploads via `finfo` magic byte validation.
- **Role-Based Lifecycle:**
  - **Students:** Report issues, follow live immutable audit timelines, view public feed anonymously, and verify resolution (Accept / Reopen).
  - **Staff:** Zendesk/Jira-style department queue, ticket workspace, assignment, internal notes (hidden from students), resolution notes, and SLA tracking.
  - **Administrators:** Stripe-style metric cards, Chart.js trend charts, user management, category/department master configuration, CSV student registry import, and report exports.
- **Accountability Leaderboard:** Public transparent ranking of longest unresolved campus issues with department reopen rates.
- **Zero Cron Dependency:** Built-in lazy maintenance engine throttled to 60-second atomic locks auto-closes expired tickets and flags overdue holds.

---

## 🚀 Quick Start Guide (Plug & Play Across Any Machine)

Solvex Report is fully self-contained and portable. When transferring this project to any new computer, you can run any of the single startup files:

### 1. Cross-Platform Python Launcher (Universal — Windows, macOS, Linux)
```bash
python run.py
```
*Automatically detects OS, locates PHP, starts MySQL if offline, verifies 6-point environment health, runs database migrations & seeds, and launches the application in your browser.*

### 2. Windows Double-Click or Batch
Double-click `run.bat` or run:
```cmd
run.bat
```
*Or via PowerShell:*
```powershell
.\run.ps1
```

### 3. Linux / macOS Terminal
```bash
chmod +x run.sh
./run.sh
```

### 4. Containerized 1-Command Setup (Docker)
If the target machine has Docker installed:
```bash
docker compose up -d
```
Access at: **[http://localhost:8000](http://localhost:8000)**

---

### Manual Setup & Commands (Optional)

#### 1. Requirements
- PHP 8.2+ with `pdo_mysql`, `gd`, `fileinfo`, `exif`, `mbstring`.
- MySQL / MariaDB (via XAMPP or native).

#### 2. Database Initialization
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS solvex_report;"
& "C:\xampp\mysql\bin\mysql.exe" -u root solvex_report -e "source database/schema.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root solvex_report -e "source database/seed.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root solvex_report -e "source database/migrate_phase2.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root solvex_report -e "source database/migrate_phase3.sql;"
```

#### 3. Generate Realistic Demo Data
Populate 45 realistic complaints and Indian student records across all campus facilities:
```powershell
& "C:\xampp\php\php.exe" database/demo_seed.php --reset
```

#### 4. Run Automated Test Suite
Verify all 31 end-to-end tests across phases 1, 2, 3, and security hardening:
```powershell
& "C:\xampp\php\php.exe" tests/run.php
```

#### 5. Start Development Server Manually
```powershell
& "C:\xampp\php\php.exe" -S 127.0.0.1:8000 router.php
```
Navigate to: **[http://localhost:8000](http://localhost:8000)**

---

## 🔑 Demo Credentials

| Role | Email | Password | Identifier | Access Dashboard |
|---|---|---|---|---|
| **Student** | `student@solvex.edu` | `Student@123` | `2024CS100` | `/student/dashboard.php` |
| **Staff** | `staff@solvex.edu` | `Staff@123` | `STF001` | `/staff/queue.php` |
| **Admin** | `admin@solvex.edu` | `Admin@123` | `ADM001` | `/admin/dashboard.php` |

---

## 📁 Project Structure

```
Solvex Report/
├── config.local.php            # Database & mail credentials (git-ignored)
├── database/
│   ├── schema.sql              # Master table schemas
│   ├── seed.sql                # Default departments & categories
│   ├── migrate_phase2.sql      # Phase 2 indexes
│   ├── migrate_phase3.sql      # Phase 3 settings & admin_log
│   └── demo_seed.php           # Realistic demo dataset generator (--reset)
├── docs/
│   ├── ARCHITECTURE.md         # System technical design & permissions
│   ├── DEPLOYMENT.md           # Deployment steps for XAMPP and hosting
│   ├── DEMO_SCRIPT.md          # 6-minute live demonstration narrative
│   ├── SECURITY_AUDIT.md       # Complete vulnerability & hardening checklist
│   ├── TEST_PHASE2.md          # Phase 2 test evidence
│   ├── TEST_PHASE3.md          # Phase 3 test evidence
│   └── USER_GUIDE.md           # Step-by-step role documentation
├── includes/
│   ├── auth.php                # Authentication & role checks
│   ├── csrf.php                # Synchronizer CSRF tokens
│   ├── db.php                  # PDO database singleton
│   ├── events.php              # Status transition engine & notifications
│   ├── header.php / footer.php # Responsive layout shell
│   ├── maintenance.php         # Lazy cron-free maintenance engine
│   ├── permissions.php         # Anonymity & visibility authorization
│   └── upload.php              # GD image sanitization & EXIF stripping
├── public/
│   ├── admin/                  # Overview, complaints, users, master, reports, accountability
│   ├── staff/                  # Queue & single complaint workspace
│   ├── student/                # Dashboard, 3-step report, my complaints, profile
│   ├── api/                    # JSON endpoints (locations, similar, affected, comments)
│   ├── assets/                 # CSS design system, JS, and WebP vector artwork
│   └── errors/                 # Hardened 403, 404, 500 error templates
├── storage/                    # Sanitized uploads & error logs (secured outside web root)
└── tests/
    ├── phase2_tests.php        # Phase 2 suite
    ├── phase3_tests.php        # Phase 3 suite
    └── run.php                 # Master CLI test runner (30/30 tests pass)
```
