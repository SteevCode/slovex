#!/usr/bin/env python3
"""
Solvex Report — Automated High-Resolution Screen Capture
Captures desktop (1280), tablet (768), and mobile (360) screens into docs/screens/
using headless Chrome/Edge.
"""

import os
import sys
import subprocess
import time
import socket

PROJECT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SCREENS_DIR = os.path.join(PROJECT_DIR, "docs", "screens")
os.makedirs(SCREENS_DIR, exist_ok=True)

# 1. Locate Chrome or Edge
CHROME_CANDIDATES = [
    r"C:\Program Files\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
    "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
    "/usr/bin/google-chrome",
    "/usr/bin/chromium-browser",
]

chrome_bin = None
for c in CHROME_CANDIDATES:
    if os.path.exists(c):
        chrome_bin = c
        break

if not chrome_bin:
    print("❌ No Chrome/Edge binary found for automated screenshots.")
    sys.exit(1)

print(f"[*] Using Browser Binary: {chrome_bin}")

# 2. Check if local web server is running on port 8000
def is_server_running():
    try:
        with socket.create_connection(("127.0.0.1", 8000), timeout=1.0):
            return True
    except Exception:
        return False

server_process = None
if not is_server_running():
    print("[*] Starting local PHP server on port 8000 for capture...")
    php_candidates = [
        r"C:\xampp\php\php.exe",
        "php"
    ]
    php_bin = "php"
    for p in php_candidates:
        if os.path.exists(p):
            php_bin = p
            break

    router_file = os.path.join(PROJECT_DIR, "router.php")
    server_process = subprocess.Popen(
        [php_bin, "-S", "127.0.0.1:8000", router_file],
        cwd=PROJECT_DIR,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL
    )
    time.sleep(2)

if not is_server_running():
    print("❌ Server failed to respond on 127.0.0.1:8000.")
    sys.exit(1)

# 3. Screens to capture: (filename, path_query, width, height)
SCREENS = [
    ("landing-1280.png", "/", 1280, 800),
    ("landing-360.png", "/", 360, 780),
    ("login-1280.png", "/login.php", 1280, 800),
    ("register-1280.png", "/register.php", 1280, 800),
    ("feed-1280.png", "/feed.php", 1280, 800),
    ("feed-360.png", "/feed.php", 360, 780),
    ("report-step1-1280.png", "/student/report.php?demo_as=student", 1280, 800),
    ("report-step1-360.png", "/student/report.php?demo_as=student", 360, 780),
    ("student-dashboard-1280.png", "/student/dashboard.php?demo_as=student", 1280, 800),
    ("complaint-detail-1280.png", "/complaint.php?ref=SVX-2026-00001&demo_as=student", 1280, 800),
    ("staff-queue-1280.png", "/staff/queue.php?demo_as=staff", 1280, 800),
    ("staff-queue-768.png", "/staff/queue.php?demo_as=staff", 768, 1024),
    ("staff-workspace-1280.png", "/staff/complaint.php?id=1&demo_as=staff", 1280, 800),
    ("admin-dashboard-1280.png", "/admin/dashboard.php?demo_as=admin", 1280, 800),
    ("admin-dashboard-768.png", "/admin/dashboard.php?demo_as=admin", 768, 1024),
    ("admin-leaderboard-1280.png", "/admin/accountability.php?demo_as=admin", 1280, 800),
]

print(f"[*] Capturing {len(SCREENS)} high-resolution screenshots into docs/screens/...")

for filename, path, width, height in SCREENS:
    out_file = os.path.join(SCREENS_DIR, filename)
    url = f"http://127.0.0.1:8000{path}"
    cmd = [
        chrome_bin,
        "--headless",
        "--disable-gpu",
        "--hide-scrollbars",
        f"--window-size={width},{height}",
        f"--screenshot={out_file}",
        url
    ]
    try:
        subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=15)
        if os.path.exists(out_file) and os.path.getsize(out_file) > 1000:
            print(f"  ✅ {filename} ({width}x{height}) captured [{os.path.getsize(out_file)//1024} KB]")
        else:
            print(f"  ⚠️ {filename} created but empty or missing")
    except Exception as e:
        print(f"  ❌ Failed {filename}: {e}")

# 4. Cleanup background server if started by us
if server_process:
    server_process.terminate()

print("🎉 Automated screenshot capture complete.")

