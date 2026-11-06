# Solvex Report — PowerShell Server Runner
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

Write-Host "═══════════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "         SOLVEX REPORT — CAMPUS INFRASTRUCTURE PLATFORM               " -ForegroundColor Yellow -NoNewline
Write-Host " (v1.0)" -ForegroundColor Green
Write-Host "═══════════════════════════════════════════════════════════════════════" -ForegroundColor Cyan

# 1. Detect PHP binary
$phpBin = "php"
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    if (Test-Path "C:\xampp\php\php.exe") {
        $phpBin = "C:\xampp\php\php.exe"
    } elseif (Test-Path "D:\xampp\php\php.exe") {
        $phpBin = "D:\xampp\php\php.exe"
    } else {
        Write-Host "❌ PHP executable not found in PATH or standard XAMPP paths." -ForegroundColor Red
        Write-Host "   Please ensure PHP 8.2+ or XAMPP is installed." -ForegroundColor Yellow
        exit 1
    }
}
Write-Host "⚙️  PHP Binary: $phpBin" -ForegroundColor Gray

# 2. Check Database & Storage Environment
& $phpBin "scripts\portable_setup.php"
if ($LASTEXITCODE -eq 2) {
    Write-Host "⚠️  MySQL is offline. Starting XAMPP MySQL daemon..." -ForegroundColor Yellow
    if (Test-Path "C:\xampp\mysql_start.bat") {
        Start-Process "C:\xampp\mysql_start.bat" -WindowStyle Minimized
        Start-Sleep -Seconds 3
        & $phpBin "scripts\portable_setup.php"
        if ($LASTEXITCODE -ne 0) {
            Write-Host "❌ Failed to reach MySQL. Please start MySQL from XAMPP Control Panel." -ForegroundColor Red
            exit 1
        }
    } else {
        Write-Host "❌ MySQL daemon is not running. Please start MySQL and run again." -ForegroundColor Red
        exit 1
    }
}

$port = 8000
$serverUrl = "http://localhost:$port"

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════════════════" -ForegroundColor DarkGray
Write-Host " 🎓 DEMO LOGIN CREDENTIALS:" -ForegroundColor Cyan
Write-Host "   • Student: " -NoNewline -ForegroundColor White
Write-Host "student@solvex.edu" -NoNewline -ForegroundColor Yellow
Write-Host "  | Password: " -NoNewline -ForegroundColor White
Write-Host "Student@123" -ForegroundColor Green
Write-Host "   • Staff:   " -NoNewline -ForegroundColor White
Write-Host "staff@solvex.edu" -NoNewline -ForegroundColor Yellow
Write-Host "    | Password: " -NoNewline -ForegroundColor White
Write-Host "Staff@123" -ForegroundColor Green
Write-Host "   • Admin:   " -NoNewline -ForegroundColor White
Write-Host "admin@solvex.edu" -NoNewline -ForegroundColor Yellow
Write-Host "    | Password: " -NoNewline -ForegroundColor White
Write-Host "Admin@123" -ForegroundColor Green
Write-Host "───────────────────────────────────────────────────────────────────────" -ForegroundColor DarkGray
Write-Host " 🌐 SERVER URL: " -NoNewline -ForegroundColor White
Write-Host "$serverUrl" -ForegroundColor Cyan
Write-Host " 🛑 Press " -NoNewline -ForegroundColor White
Write-Host "Ctrl + C" -NoNewline -ForegroundColor Red
Write-Host " in this terminal to shut down the server." -ForegroundColor White
Write-Host "═══════════════════════════════════════════════════════════════════════" -ForegroundColor DarkGray
Write-Host ""

# 3. Open Browser
Start-Process $serverUrl

# 4. Start Server
& $phpBin -S "127.0.0.1:$port" router.php

