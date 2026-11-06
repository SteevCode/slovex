# Solvex Report — Deployment Guide

## 1. Local Deployment (XAMPP on Windows)

### Prerequisites
- XAMPP with PHP 8.2+ and MySQL/MariaDB.
- Required PHP Extensions: `pdo_mysql`, `gd`, `fileinfo`, `exif`, `mbstring`, `session`.

### ⚡ Fastest Way: One-Click Runner
Simply double-click [`run.bat`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/run.bat) or run in PowerShell:
```powershell
.\run.ps1
```
This automated runner validates PHP, starts MySQL if offline, creates missing directories, seeds realistic demo data if needed, launches your browser, and serves the application on `http://localhost:8000`.

---

### Setup Steps (Manual)
1. **Clone / Place Project:**
   Place the project folder at:
   `C:\xampp\htdocs\solvex` (or any custom folder if using virtual hosts or built-in server).
2. **Start MySQL:**
   Open XAMPP Control Panel and start MySQL (or run `C:\xampp\mysql_start.bat`).
3. **Database Setup:**
   Run MySQL CLI or phpMyAdmin to import schemas:
   ```powershell
   & "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS solvex_report CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   & "C:\xampp\mysql\bin\mysql.exe" -u root solvex_report -e "source database/schema.sql;"
   & "C:\xampp\mysql\bin\mysql.exe" -u root solvex_report -e "source database/seed.sql;"
   & "C:\xampp\mysql\bin\mysql.exe" -u root solvex_report -e "source database/migrate_phase2.sql;"
   & "C:\xampp\mysql\bin\mysql.exe" -u root solvex_report -e "source database/migrate_phase3.sql;"
   ```
4. **Configuration:**
   Copy `config.local.example.php` to `config.local.php`:
   - Set `DB_NAME` to `solvex_report`
   - Set `DB_USER` to `root`
   - Set `DB_PASS` to `""`
5. **Generate Realistic Seed Data:**
   ```powershell
   & "C:\xampp\php\php.exe" database/demo_seed.php --reset
   ```
6. **Run Test Harness:**
   ```powershell
   & "C:\xampp\php\php.exe" tests/run.php
   ```
7. **Launch Web Server:**
   ```powershell
   & "C:\xampp\php\php.exe" -S 127.0.0.1:8000 -t public
   ```
   Access at: `http://127.0.0.1:8000`

---

## 2. Production Deployment (Free / Shared / VPS Hosting)

1. **Document Root:**
   Point Apache/Nginx Web Root directly to the `public/` directory. The `storage/` and `includes/` folders must remain above document root.
2. **HTTPS Requirement:**
   Enable SSL (Let's Encrypt). HTTPS is required for the browser Geolocation API and secure cookies.
3. **Set `APP_ENV = 'production'`** in `config.local.php` to disable display of PHP errors.
4. **Mail Delivery:**
   Set `MAIL_MODE = 'smtp'` and provide your Gmail App Password in `config.local.php`.

---

## 3. Demo Credentials

| Role | Email | Password | Staff / Reg Code |
|---|---|---|---|
| **Student** | `student@solvex.edu` | `Student@123` | `2024CS100` |
| **Staff (Maintenance)** | `staff@solvex.edu` | `Staff@123` | `STF001` |
| **Super Admin** | `admin@solvex.edu` | `Admin@123` | `ADM001` |

