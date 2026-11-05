# Solvex Report — Campus Issue Resolution & Civic Tracking

> **Phase 1: Foundation Release**  
> Skeleton, Design System, Database Architecture, Authentication, and Public Landing Page.

Solvex Report is a civic problem reporting and transparent tracking web application designed for college campuses and hostels. Students report problems (electrical, civil, IT, mess, safety), staff members resolve them, and an administrator oversees everything. Every complaint has a public ageing record and an immutable audit timeline.

---

## Technical Stack

* **Language:** PHP 8.0+ (Native PDO, Sessions, No frameworks)
* **Frontend:** HTML5, CSS3 (Bootstrap 5.3 + bespoke `solvex.css`), Vanilla JavaScript (`solvex.js`)
* **Database:** MySQL 8.0+ / MariaDB 10.6+ (InnoDB, `utf8mb4`, strict foreign keys)
* **Timezone:** Asia/Kolkata (`+05:30` set on every connection)
* **Security:** Bcrypt password hashing, SHA-256 token hashing, strict CSRF tokens, IP/identifier throttling, XSS output escaping.

---

## Directory Structure

```text
Solvex Report/
├── config.local.example.php   # Template for local configuration
├── config.local.php           # Local environment credentials (git-ignored)
├── database/
│   ├── schema.sql             # 13 tables with constraints & indexes
│   ├── seed.sql               # Departments, categories, locations, sample students
│   └── student_master_sample.csv # 30 sample student enrolment records
├── docs/
│   ├── ARCHITECTURE.md        # System architecture specification
│   ├── DECISIONS.md           # Architecture and technical decisions
│   ├── DESIGN.md              # Design system & component tokens
│   └── TEST_PHASE1.md         # Phase 1 test suite results & evidence
├── includes/
│   ├── auth.php               # Authentication, throttling, token validation
│   ├── config.php             # Core constants, paths, error logging
│   ├── csrf.php               # CSRF token generation and validation
│   ├── db.php                 # Singleton PDO connection wrapper
│   ├── footer.php             # Shared layout footer & scripts
│   ├── header.php             # Shared layout header & navigation
│   ├── helpers.php            # Escaping e(), URLs, formatting helpers
│   ├── mailer.php             # Mail dispatcher (SMTP & local log mode)
│   └── permissions.php        # Role authorization stubs
├── public/                    # Web root
│   ├── index.php              # Public landing page with live counters
│   ├── login.php              # Multi-role authentication endpoint
│   ├── register.php           # 3-step student registration wizard
│   ├── verify.php             # Email verification & activation
│   ├── forgot.php             # Password recovery request
│   ├── reset.php              # New password setup
│   ├── logout.php             # Session destruction & sign out
│   ├── admin/
│   │   └── dashboard.php      # Admin dashboard placeholder
│   ├── errors/
│   │   ├── 403.php            # Forbidden error page
│   │   └── 404.php            # Not found error page
│   ├── staff/
│   │   └── queue.php          # Staff resolution queue placeholder
│   ├── student/
│   │   └── dashboard.php      # Student dashboard placeholder
│   └── assets/
│       ├── css/
│       │   └── solvex.css     # Bespoke design system
│       ├── img/
│       │   ├── logo-full.svg  # Full brand logo
│       │   ├── logo-light.svg # Dark-mode / footer logo
│       │   ├── logo-mark.svg  # Brand pin icon
│       │   ├── favicon.svg    # Vector favicon
│       │   ├── favicon-32.png # 32x32 raster favicon
│       │   ├── apple-touch-icon.png # 180x180 mobile touch icon
│       │   └── og-image.png   # 1200x630 OpenGraph social share card
│       └── js/
│           └── solvex.js      # Form validation, toasts, counters, strength meter
├── scripts/
│   └── create_admin.php       # CLI script to provision the super admin
├── storage/
│   ├── logs/                  # Application and mail dispatch logs
│   └── uploads/               # Ticket attachments (Phase 2)
└── tests/
    └── test_phase1.php        # Automated test verification suite
```

---

## Exact XAMPP Setup Steps

### Step 1: Clone or Copy Project
Place the project in your preferred directory (or in `C:\xampp\htdocs\solvex`).

### Step 2: Start Apache and MySQL
Open the **XAMPP Control Panel** and click **Start** for both **Apache** and **MySQL**.

### Step 3: Configure `config.local.php`
Copy `config.local.example.php` to `config.local.php`:
```bash
cp config.local.example.php config.local.php
```
Open `config.local.php` and verify the database credentials:
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'solvex_report');
define('DB_USER', 'root');
define('DB_PASS', '');

// Set to 'log' for local development (emails write to storage/logs/mail.log)
define('MAIL_MODE', 'log');
```

### Step 4: Create Database and Import Schema & Seed
Open your terminal (or phpMyAdmin at `http://localhost/phpmyadmin`):
```bash
# Using MySQL CLI:
mysql -u root -e "CREATE DATABASE IF NOT EXISTS solvex_report CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root solvex_report < database/schema.sql
mysql -u root solvex_report < database/seed.sql
```

### Step 5: Create Super Administrator Account
Run the CLI provisioning script:
```bash
php scripts/create_admin.php
```
You will be prompted to enter:
* **Full Name:** e.g., `System Administrator`
* **Email:** e.g., `admin@solvexreport.edu`
* **Staff Code:** e.g., `ADM001`
* **Password:** e.g., `AdminSecure2024!` (min 8 chars)

*(You can also run non-interactively via flags: `php scripts/create_admin.php --name="Admin" --email="admin@solvex.edu" --code="ADM001" --password="Password123"`) *

### Step 6: Run the Application
You can run Solvex Report in two ways:

* **Option A: Using PHP Built-in Server (Recommended for instant testing):**
  ```bash
  php -S 127.0.0.1:8000 -t public
  ```
  Open your browser at: **`http://127.0.0.1:8000/`**

* **Option B: Using XAMPP Apache:**
  Access: **`http://localhost/solvex/public/`**

---

## Running the Automated Test Suite

To verify all security, database integrity, registration, throttling, and CSRF rules:
```bash
php tests/test_phase1.php
```
All 8 automated tests should report `[PASS]` with full output evidence.

---

## Seeded Test Accounts

| Role | Identifier | Default Password | Notes |
|------|-----------|------------------|-------|
| **Admin** | `admin@solvexreport.edu` or `ADM001` | *Set during Step 5* | Full administrative access |
| **Staff (Maintenance)** | `STF-MNT-01` or `rajesh.maintenance@solvexreport.edu` | `StaffPass2024!` | Maintenance Department Staff |
| **Staff (Electrical)** | `STF-ELE-01` or `suresh.electrical@solvexreport.edu` | `StaffPass2024!` | Electrical Works Staff |
| **Staff (IT)** | `STF-IT-01` or `priya.it@solvexreport.edu` | `StaffPass2024!` | IT & Network Services Staff |
| **Sample Students** | `2024CS001` through `2024CS030` | *Register via /register.php* | Listed in `student_master` |
