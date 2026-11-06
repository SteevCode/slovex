@echo off
setlocal EnableDelayedExpansion
title Solvex Report - Campus Incident Resolution Platform

echo =======================================================================
echo          SOLVEX REPORT - CAMPUS INFRASTRUCTURE PLATFORM
echo =======================================================================
echo.

:: 1. Locate PHP Binary
set "PHP_BIN="
where php >nul 2>nul
if %ERRORLEVEL% equ 0 (
    set "PHP_BIN=php"
    goto :find_mysql
)

if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
    goto :find_mysql
)
if exist "D:\xampp\php\php.exe" (
    set "PHP_BIN=D:\xampp\php\php.exe"
    goto :find_mysql
)
if exist "E:\xampp\php\php.exe" (
    set "PHP_BIN=E:\xampp\php\php.exe"
    goto :find_mysql
)

echo [ERROR] PHP was not found in PATH or standard XAMPP paths (C:\xampp\php\php.exe).
echo Please install PHP 8.2+ or start XAMPP.
pause
exit /b 1

:find_mysql
echo [*] Using PHP: %PHP_BIN%

:: 2. Check Database & Auto-start MySQL if needed
"%PHP_BIN%" scripts\portable_setup.php
if %ERRORLEVEL% equ 2 (
    echo [*] MySQL is offline. Attempting to start XAMPP MySQL daemon...
    if exist "C:\xampp\mysql_start.bat" (
        start "" /b "C:\xampp\mysql_start.bat"
        timeout /t 3 /nobreak >nul
        "%PHP_BIN%" scripts\portable_setup.php
        if !ERRORLEVEL! neq 0 (
            echo [WARNING] Could not connect to MySQL. Please start MySQL from the XAMPP Control Panel.
            pause
            exit /b 1
        )
    ) else (
        echo [ERROR] MySQL is not running. Please start MySQL and run again.
        pause
        exit /b 1
    )
)

echo.
echo =======================================================================
echo   DEMO LOGIN ACCOUNTS:
echo     Student:        student@solvex.edu   (Password: Student@123)
echo     Staff:          staff@solvex.edu     (Password: Staff@123)
echo     Administrator:  admin@solvex.edu     (Password: Admin@123)
echo.
echo   SERVER URL: http://localhost:8000
echo   Press Ctrl + C in this window to stop the server anytime.
echo =======================================================================
echo.

:: 3. Launch Default Browser
start http://localhost:8000

:: 4. Start PHP Built-in Server with development router
"%PHP_BIN%" -S 127.0.0.1:8000 router.php

pause
