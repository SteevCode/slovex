@echo off
setlocal enabledelayedexpansion
title Solvex Report — Campus Issue Resolution Platform

echo =======================================================================
echo          SOLVEX REPORT — CAMPUS INFRASTRUCTURE PLATFORM
echo =======================================================================
echo.

:: 1. Locate PHP Executable
set "PHP_CMD=php"
where php >nul 2>nul
if %ERRORLEVEL% equ 0 goto php_found

if exist "C:\xampp\php\php.exe" (
    set "PHP_CMD=C:\xampp\php\php.exe"
    goto php_found
)
if exist "D:\xampp\php\php.exe" (
    set "PHP_CMD=D:\xampp\php\php.exe"
    goto php_found
)

echo [ERROR] PHP executable was not found in PATH or standard XAMPP paths.
echo Please install PHP 8.2+ or start XAMPP.
pause
exit /b 1

:php_found
echo [*] Using PHP: %PHP_CMD%

:: 2. Check Database & Start MySQL if needed
"%PHP_CMD%" scripts\portable_setup.php
if %ERRORLEVEL% equ 2 (
    echo [*] MySQL is not running. Attempting to start XAMPP MySQL daemon...
    if exist "C:\xampp\mysql_start.bat" (
        start /b "" "C:\xampp\mysql_start.bat"
        timeout /t 3 /nobreak >nul
        "%PHP_CMD%" scripts\portable_setup.php
        if !ERRORLEVEL! neq 0 (
            echo [WARNING] Could not connect to MySQL. Please start MySQL from the XAMPP Control Panel.
            pause
            exit /b 1
        )
    ) else (
        echo [ERROR] MySQL is not running. Please start MySQL and run this script again.
        pause
        exit /b 1
    )
)

echo.
echo =======================================================================
echo   DEMO CREDENTIALS:
echo   - Student: student@solvex.edu   | Password: Student@123
echo   - Staff:   staff@solvex.edu     | Password: Staff@123
echo   - Admin:   admin@solvex.edu     | Password: Admin@123
echo.
echo   SERVER URL: http://localhost:8000
echo   Press Ctrl + C in this window to stop the server anytime.
echo =======================================================================
echo.

:: 3. Launch Default Browser
timeout /t 1 /nobreak >nul
start http://localhost:8000

:: 4. Start PHP Built-in Server
"%PHP_CMD%" -S 127.0.0.1:8000 router.php
pause

