# Solvex Report — Deployment & Operations Guide

## 1. Quick-Start Local Launchers (Universal Portability)

Solvex Report includes self-contained launchers that detect your operating system, verify required PHP extensions, connect to MySQL, execute pending schema migrations, and boot the web application.

### Windows (Batch or PowerShell)
Double-click `run.bat` or run:
```cmd
run.bat
```
*Or via PowerShell:*
```powershell
.\run.ps1
```

### Universal Python Launcher (Windows, macOS, Linux)
```bash
python run.py
```

### Linux / macOS Shell Script
```bash
chmod +x run.sh
./run.sh
```

### Containerized 1-Command Deployment (Docker)
```bash
docker compose up -d
```
Access the application at: **[http://localhost:8000](http://localhost:8000)**

---

## 2. Manual Local Deployment (XAMPP / Native PHP)

### Prerequisites
- **PHP 8.2+** with extensions: `pdo_mysql`, `gd`, `fileinfo`, `exif`, `mbstring`, `session`.
- **MySQL / MariaDB 10.4+** running on `localhost:3306`.

### Step 1: Database Initialization
Open your terminal and run:
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS solvex_report CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& "C:\xampp\mysql\bin\mysql.exe" -u root solvex_report -e "source database/schema.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root solvex_report -e "source database/seed.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root solvex_report -e "source database/migrate_phase2.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root solvex_report -e "source database/migrate_phase3.sql;"
```

### Step 2: Environment Configuration
Copy the configuration template:
```powershell
Copy-Item config.local.example.php config.local.php
```
Ensure `config.local.php` matches your local database settings:
```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'solvex_report');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_ENV', 'local');
define('ENABLE_DEMO_TOOLS', true);
```

### Step 3: Populate Realistic Demo Records
Populate 45 campus complaints, students, and facility locations:
```powershell
& "C:\xampp\php\php.exe" database/demo_seed.php --reset
```

### Step 4: Run Automated Verification Tests
```powershell
& "C:\xampp\php\php.exe" tests/run.php
```
Ensure all 38 test suites pass with zero failures.

### Step 5: Start PHP Built-in Server
```powershell
& "C:\xampp\php\php.exe" -S 127.0.0.1:8000 router.php
```
Navigate to: **[http://localhost:8000](http://localhost:8000)**

---

## 3. Production Deployment (Apache / Nginx / Shared Hosting)

### 1. Document Root Configuration
**CRITICAL:** The web server document root must point exclusively to the `public/` directory. The `includes/`, `storage/`, `database/`, and `config.local.php` files must remain strictly above the web root.

#### Apache VirtualHost Example (`/etc/apache2/sites-available/solvex.conf`):
```apache
<VirtualHost *:80>
    ServerName solvex.campus.edu
    DocumentRoot /var/www/solvex/public

    <Directory /var/www/solvex/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/solvex_error.log
    CustomLog ${APACHE_LOG_DIR}/solvex_access.log combined
</VirtualHost>
```

### 2. HTTPS & TLS Requirement
HTTPS is required in production:
- Enables browser Geolocation API (`navigator.geolocation`) for accurate campus coordinates.
- Enforces `HttpOnly` and `Secure` cookie attributes for session protection.
- Automatically enforced by `Strict-Transport-Security` header in `includes/config.php`.

### 3. Production Configuration Settings (`config.local.php`)
```php
define('APP_ENV', 'production');
define('ENABLE_DEMO_TOOLS', false); // Disables debug links and demo shortcuts
define('BASE_URL', 'https://solvex.campus.edu');
define('MAIL_MODE', 'smtp');
define('SMTP_HOST', 'smtp.sendgrid.net');
define('SMTP_PORT', 587);
define('SMTP_USER', 'apikey');
define('SMTP_PASS', 'YOUR_SMTP_API_KEY');
```

### 4. File System Permissions
```bash
chown -R www-data:www-data /var/www/solvex
chmod -R 755 /var/www/solvex
chmod -R 775 /var/www/solvex/storage
chmod 600 /var/www/solvex/config.local.php
```

---

## 4. Operational Checklist & Health Audits

| Verification Area | Command / Check | Expected Result |
|---|---|---|
| **Automated Test Harness** | `php tests/run.php` | 38 PASSED / 38 TOTAL (0 failures) |
| **Static Security Linter** | `php scripts/lint_security.php` | 46 files audited, 0 violations |
| **Direct Execution in Uploads** | `GET /storage/uploads/test.php` | HTTP 403 Forbidden |
| **Demo Tools Gating** | Access `?demo_as=student` in production | Ignored; redirects to login |
| **Lazy Maintenance** | Load `/staff/queue.php` | Expired tickets auto-closed after 5 days |
