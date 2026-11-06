#!/usr/bin/env python3
"""
Solvex Report — Universal Cross-Platform Launcher & Environment Manager

Supports: Windows, macOS, Linux
Handles:
  1. Operating system & runtime detection (PHP 8.1+, MySQL/MariaDB)
  2. Automatic directory and local configuration provisioning
  3. Service discovery & automatic background MySQL daemon startup
  4. Database schema migration & realistic demo seed check
  5. Built-in web server launch and automatic browser opening
"""

import sys
import os
import subprocess
import socket
import time
import webbrowser
import platform

PROJECT_DIR = os.path.dirname(os.path.abspath(__file__))

# ANSI Colors for terminal output
CYAN = "\033[96m"
GREEN = "\033[92m"
YELLOW = "\033[93m"
RED = "\033[91m"
MAGENTA = "\033[95m"
BOLD = "\033[1m"
RESET = "\033[0m"

# Windows color support
if platform.system() == "Windows":
    try:
        import ctypes
        kernel32 = ctypes.windll.kernel32
        kernel32.SetConsoleMode(kernel32.GetStdHandle(-11), 7)
    except Exception:
        pass


def print_banner():
    print(f"{CYAN}{'='*72}{RESET}")
    print(f"{BOLD}{YELLOW}   SOLVEX REPORT — CAMPUS INCIDENT RESOLUTION PLATFORM{RESET}")
    print(f"{CYAN}   Automated Cross-Platform Self-Contained Launcher{RESET}")
    print(f"{CYAN}{'='*72}{RESET}\n")


def is_port_open(host, port, timeout=1.0):
    try:
        with socket.create_connection((host, port), timeout=timeout):
            return True
    except (socket.timeout, ConnectionRefusedError, OSError):
        return False


def find_php_binary():
    """Finds a viable PHP executable across common OS and XAMPP paths."""
    # 1. System PATH
    candidates = ["php"]
    
    # 2. Windows XAMPP and common paths
    if platform.system() == "Windows":
        candidates.extend([
            r"C:\xampp\php\php.exe",
            r"D:\xampp\php\php.exe",
            r"E:\xampp\php\php.exe",
            os.path.expanduser(r"~\xampp\php\php.exe"),
            os.path.join(PROJECT_DIR, ".php_portable", "php.exe"),
        ])
    elif platform.system() == "Darwin": # macOS
        candidates.extend([
            "/opt/homebrew/bin/php",
            "/usr/local/bin/php",
            "/Applications/XAMPP/xamppfiles/bin/php",
        ])
    else: # Linux
        candidates.extend([
            "/usr/bin/php",
            "/usr/local/bin/php",
            "/opt/lampp/bin/php",
        ])

    for cand in candidates:
        try:
            res = subprocess.run([cand, "-v"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            if res.returncode == 0:
                first_line = res.stdout.splitlines()[0] if res.stdout else ""
                return cand, first_line
        except (FileNotFoundError, PermissionError):
            continue

    return None, None


def ensure_mysql_running():
    """Checks if MySQL/MariaDB port 3306 is open; attempts launch if Windows XAMPP exists."""
    if is_port_open("127.0.0.1", 3306):
        return True

    print(f"{YELLOW}⚠️  MySQL is not listening on 127.0.0.1:3306. Attempting to start...{RESET}")

    if platform.system() == "Windows":
        # Look for XAMPP mysql_start.bat
        xampp_starts = [
            r"C:\xampp\mysql_start.bat",
            r"D:\xampp\mysql_start.bat",
            r"C:\xampp\mysql\bin\mysqld.exe",
        ]
        for start_cmd in xampp_starts:
            if os.path.exists(start_cmd):
                print(f"[*] Starting MySQL via {start_cmd}...")
                subprocess.Popen(
                    start_cmd,
                    shell=True,
                    stdout=subprocess.DEVNULL,
                    stderr=subprocess.DEVNULL,
                    creationflags=getattr(subprocess, "CREATE_NEW_PROCESS_GROUP", 0)
                )
                # Wait up to 6 seconds for port to open
                for _ in range(12):
                    time.sleep(0.5)
                    if is_port_open("127.0.0.1", 3306):
                        print(f"{GREEN}✅ MySQL daemon started successfully.{RESET}")
                        return True

    elif platform.system() == "Linux":
        # Attempt systemctl or service
        try:
            subprocess.run(["sudo", "systemctl", "start", "mariadb"], check=False)
            time.sleep(2)
            if is_port_open("127.0.0.1", 3306):
                return True
        except Exception:
            pass

    return False


def main():
    print_banner()

    # Step 1: Detect Operating System
    os_name = platform.system()
    print(f"[*] Detected Operating System: {BOLD}{os_name} ({platform.machine()}){RESET}")

    # Step 2: Locate PHP
    php_bin, php_ver = find_php_binary()
    if not php_bin:
        print(f"\n{RED}❌ PHP 8.1+ was not found on your system.{RESET}")
        print("   To run Solvex Report, please install PHP or XAMPP:")
        if os_name == "Windows":
            print("   👉 Install XAMPP: https://www.apachefriends.org/download.html")
            print("   👉 Or portable PHP: https://windows.php.net/download/")
        elif os_name == "Darwin":
            print("   👉 Install via Homebrew: brew install php")
        else:
            print("   👉 Install via APT: sudo apt-get update && sudo apt-get install php php-mysql php-gd php-mbstring")
        sys.exit(1)

    print(f"[*] PHP Binary Found: {GREEN}{php_bin}{RESET}")
    print(f"    Version: {php_ver}")

    # Step 3: MySQL Service Check
    mysql_ok = ensure_mysql_running()
    if not mysql_ok:
        print(f"\n{RED}❌ Could not connect to MySQL / MariaDB on 127.0.0.1:3306.{RESET}")
        print("   Please start your MySQL server (via XAMPP Control Panel, Docker, or native service) and re-run.")
        sys.exit(1)

    # Step 4: Run Health Check & Setup Script
    setup_script = os.path.join(PROJECT_DIR, "scripts", "portable_setup.php")
    res = subprocess.run([php_bin, setup_script], cwd=PROJECT_DIR)
    if res.returncode != 0:
        print(f"\n{RED}❌ Environment validation failed with exit code {res.returncode}.{RESET}")
        sys.exit(res.returncode)

    # Step 5: Start PHP Server
    port = 8000
    server_url = f"http://localhost:{port}"

    print(f"\n{CYAN}{'='*72}{RESET}")
    print(f"{BOLD}{GREEN} 🚀 LAUNCHING SOLVEX REPORT SERVER{RESET}")
    print(f"{CYAN}{'='*72}{RESET}")
    print(f" 🌐 Access URL:       {BOLD}{CYAN}{server_url}{RESET}")
    print(f"\n 🎓 {BOLD}DEMO ACCOUNTS:{RESET}")
    print(f"    • Student:        {YELLOW}student@solvex.edu{RESET}  | Password: {GREEN}Student@123{RESET}")
    print(f"    • Staff:          {YELLOW}staff@solvex.edu{RESET}    | Password: {GREEN}Staff@123{RESET}")
    print(f"    • Administrator:  {YELLOW}admin@solvex.edu{RESET}    | Password: {GREEN}Admin@123{RESET}")
    print(f"\n 🛑 Press {RED}{BOLD}Ctrl + C{RESET} in this terminal to shut down the server.")
    print(f"{CYAN}{'='*72}{RESET}\n")

    # Automatically open default browser after a brief delay
    def open_browser():
        time.sleep(1.0)
        try:
            webbrowser.open(server_url)
        except Exception:
            pass

    import threading
    threading.Thread(target=open_browser, daemon=True).start()

    # Launch PHP built-in server with development router
    router_file = os.path.join(PROJECT_DIR, "router.php")
    server_cmd = [php_bin, "-S", f"127.0.0.1:{port}", router_file]

    try:
        subprocess.run(server_cmd, cwd=PROJECT_DIR)
    except KeyboardInterrupt:
        print(f"\n\n{YELLOW}[*] Shutting down Solvex Report server. Goodbye!{RESET}")
        sys.exit(0)


if __name__ == "__main__":
    main()

