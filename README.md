# Solvex Report — Campus Incident Resolution System

Solvex Report is an accessible, civic campus problem reporting, department triage, and accountability system engineered for colleges and university hostel environments. Built on native PHP 8.2+ and MariaDB/MySQL, it operates with zero runtime framework dependencies, zero background daemon/cron requirements, and strict multi-role permission boundaries.

---

## 🌟 Key Capabilities

### 1. 3-Step Guided Student Intake
- **Visual Category Cards:** Clear SLA targets, public/private sensitivity badges, and emergency safety banners.
- **Cascading Location Selectors:** Drill down from Campus/Hostel &rarr; Building &rarr; Floor &rarr; Specific Room, with optional Geolocation API GPS capture.
- **Automated Duplicate Detection:** Proactively surfaces similar open complaints from the preceding 14 days and allows students to click *"I am also affected"* instead of filing redundant tickets.
- **Sanitized Media Uploads:** Strips GPS and EXIF metadata through GD re-encoding and validates magic bytes to prevent arbitrary code execution.
- **Resolution Verification:** Students personally confirm resolution (*"Accept Resolution"* to close, or *"Still Broken"* to reopen with mandatory justification).

### 2. Zendesk/Jira-Style Staff Resolution Queue
- **Department Queue Triage:** Filter by status, priority, category, date, unassigned, and overdue SLA violations.
- **Confidential Internal Notes:** Post staff-only internal discussions completely hidden from students and public JSON feeds.
- **SLA Violation Tracking:** Visual ageing counters and days-open indicators calculate active response times with automatic freeze on resolved records.
- **Strict Transition Engine:** Optimistic concurrency locks (`UPDATE ... WHERE id=? AND status=?`) prevent race conditions during triage.

### 3. Administrator Control Center
- **Stripe-Style Metric Cards & Analytics:** Live intake vs. resolution trends and category distributions.
- **Master Data Configuration:** Full CRUD for campus categories, SLA target days, departments, and location trees with non-destructive soft-disables.
- **Student Master Registry CSV Import:** Validates registry rows before upserting into the student directory.
- **Accountability Leaderboard:** Public transparent ranking of longest unresolved campus issues with department reopen rates.
- **Audit Logging:** Append-only ledger recording every user and master data mutation.

### 4. Zero-Cron Lazy Maintenance Engine
- Background maintenance automatically runs during staff and admin page loads, protected by 60-second atomic locks.
- Automatically closes resolved tickets after 5 days with `closed_by = 'auto'`.
- Flags overdue on-hold tickets without requiring system cron access or shell daemon processes.

---

## 🚀 Quick Start (Plug & Play Universal Launchers)

Solvex Report is completely self-contained and portable across Windows, macOS, and Linux. Choose your preferred launcher:

### 1. Cross-Platform Python Launcher (Universal)
```bash
python run.py
```
*Detects operating system, locates PHP binary, boots MySQL if offline, verifies 6-point environment health, runs database migrations and seeds, and opens the application in your default browser.*

### 2. Windows Batch or PowerShell
Double-click `run.bat` or run in terminal:
```cmd
run.bat
```
*Or via PowerShell:*
```powershell
.\run.ps1
```

### 3. Linux / macOS Shell Script
```bash
chmod +x run.sh
./run.sh
```

### 4. Containerized 1-Command Setup (Docker)
```bash
docker compose up -d
```
Access at: **[http://localhost:8000](http://localhost:8000)**

---

## 🔑 Demo Credentials

| Role | Email | Password | Identifier | Default Dashboard |
|---|---|---|---|---|
| **Student** | `student@solvex.edu` | `Student@123` | `2024CS100` | `/student/dashboard.php` |
| **Staff (Maintenance)** | `staff@solvex.edu` | `Staff@123` | `STF001` | `/staff/queue.php` |
| **Super Administrator** | `admin@solvex.edu` | `Admin@123` | `ADM001` | `/admin/dashboard.php` |

---

## 🎨 Design System: "Quiet Public-Records Office"

Solvex Report employs an editorial, information-first design system inspired by civic ledgers and modern issue trackers:
- **Surface Hierarchy:** Flat surfaces separated by crisp 1px hairlines (`--clr-border: #E2DFD6`), replacing heavy shadows and decorative gradients.
- **Color as Information:** Neutral warm paper base (`#F7F6F2`), civic teal accent (`#0E9F8E`), and semantic colors reserved strictly for functional ticket statuses.
- **Tabular Figures:** Numbers, counts, and reference identifiers (`SVX-YYYY-NNNNN`) formatted with tabular spacing and monospace fonts for effortless scanning.
- **Dark Mode Support:** Native light and dark themes with persistent `localStorage` preference and zero-flicker pre-render initialization.
- **WCAG 2.2 AA Compliance:** High-contrast color ratios, visible focus outlines, and full support for `prefers-reduced-motion` and `forced-colors`.
- **Living Styleguide:** Gated catalog available at `/styleguide.php` in local development mode.

---

## 🧪 Automated Test Suite & Security Verification

All application features and security controls are validated by an automated test harness:

```powershell
& "C:\xampp\php\php.exe" tests/run.php
```

```
═══════════════════════════════════════════════════════════════════════
   TEST HARNESS RESULTS: 38 PASSED / 38 TOTAL (0 FAILURES)
═══════════════════════════════════════════════════════════════════════
```

### Static Security Linter
Verify zero XSS escaping gaps, direct SQL concatenations, or CSRF bypasses across all 46 codebase endpoints:
```powershell
& "C:\xampp\php\php.exe" scripts/lint_security.php
```

---

## 📁 Project Directory Structure

```
Solvex Report/
├── config.local.example.php    # Configuration template
├── config.local.php            # Active database & credentials (git-ignored)
├── database/
│   ├── schema.sql              # Core database schema (InnoDB, utf8mb4)
│   ├── seed.sql                # Default departments, categories & locations
│   ├── migrate_phase2.sql      # Performance indexes & complaint extensions
│   ├── migrate_phase3.sql      # Admin logs & settings table
│   └── demo_seed.php           # Realistic demo dataset generator (--reset)
├── docs/
│   ├── ARCHITECTURE.md         # System technical design & permissions model
│   ├── DESIGN.md               # Design tokens, typography & component library
│   ├── DEPLOYMENT.md           # Production & XAMPP deployment guidelines
│   ├── USER_GUIDE.md           # Role-based walkthroughs (Student, Staff, Admin)
│   ├── SECURITY_AUDIT.md       # Complete vulnerability & hardening checklist
│   ├── DECISIONS.md            # Architectural decisions log
│   ├── FUTURE_WORK.md          # Feature roadmap & planned enhancements
│   └── screens/                # Screenshots at 1280px, 768px, and 360px
├── includes/
│   ├── auth.php                # Authentication, sessions, & role guards
│   ├── config.php              # Global configuration & security headers
│   ├── csrf.php                # Synchronizer CSRF tokens (session-persistent)
│   ├── db.php                  # PDO database singleton (non-emulated prepares)
│   ├── events.php              # Complaint lifecycle engine & audit trail
│   ├── helpers.php             # XSS escaping, safe input & date calculations
│   ├── ui.php                  # Shared UI components (icon, tags, stat blocks)
│   ├── header.php / footer.php # Responsive application layout shells
│   ├── maintenance.php         # Lazy cron-free maintenance engine
│   ├── permissions.php         # Anonymity & visibility authorization checks
│   └── upload.php              # Magic byte validation & GD EXIF stripper
├── public/
│   ├── admin/                  # Overview, complaints, users, master, import, reports
│   ├── staff/                  # Queue triage & single incident workspace
│   ├── student/                # Dashboard, 3-step report wizard, my complaints, profile
│   ├── api/                    # JSON endpoints (locations, similar, affected, comments)
│   ├── assets/                 # CSS design system (solvex.css), JS, and WebP icons
│   ├── errors/                 # Hardened 403, 404, 500 error templates
│   ├── styleguide.php          # Living design system catalog (local dev only)
│   └── index.php               # Civic landing page with live incident ledger
├── storage/                    # Sanitized uploads & error logs (secured outside web root)
└── tests/
    ├── phase2_tests.php        # Student flow & intake test suite
    ├── phase3_tests.php        # Staff queue & admin operations suite
    ├── remediation_tests.php   # Security audit regression checks
    └── run.php                 # Master CLI test runner (38/38 tests pass)
```

---

## 🔒 Security Architecture Highlights

- **SQL Injection Prevention:** 100% prepared statements with parameterized binds; zero string concatenation. Non-emulated PDO prepares enforce DBMS-level parsing.
- **Cross-Site Scripting (XSS):** Universal `e()` helper wrapping `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` for dynamic output.
- **Cross-Site Request Forgery (CSRF):** Synchronizer tokens validated via `hash_equals()`; persistent session tokens prevent multi-tab invalidation.
- **Role-Based Route Guards:** Server-side enforcement (`require_role('student')`, `require_role('staff')`, `require_role('admin')`).
- **Department Boundary Isolation:** Staff can only view and update tickets belonging to their assigned department. Non-authorized access returns HTTP 404 to prevent resource enumeration.
- **Reporter Anonymity & GPS Protection:** Public views sanitize reporter identity to `"Anonymous student"` and suppress GPS and room details for peers.
- **Append-Only Audit Trail:** `complaint_events` and `admin_log` records cannot be updated or deleted by any application role.

---

## 📄 License & Institutional Use

Solvex Report is built for campus infrastructure accountability and student welfare. Designed for easy deployment across college servers, shared hosting, and self-hosted institutional environments.
