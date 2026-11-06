#!/usr/bin/env bash
# ==============================================================================
# Solvex Report — Linux & macOS Startup Launcher
# ==============================================================================

set -e

# Terminal colors
CYAN='\033[0;36m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo -e "${CYAN}=======================================================================${NC}"
echo -e "${YELLOW}   SOLVEX REPORT — CAMPUS INCIDENT RESOLUTION PLATFORM (v1.0)${NC}"
echo -e "${CYAN}   Linux / macOS Self-Contained Launcher${NC}"
echo -e "${CYAN}=======================================================================${NC}"

# 1. Locate PHP executable
PHP_BIN=""
if command -v php >/dev/null 2>&1; then
    PHP_BIN="$(command -v php)"
elif [ -x "/opt/homebrew/bin/php" ]; then
    PHP_BIN="/opt/homebrew/bin/php"
elif [ -x "/usr/local/bin/php" ]; then
    PHP_BIN="/usr/local/bin/php"
elif [ -x "/usr/bin/php" ]; then
    PHP_BIN="/usr/bin/php"
elif [ -x "/opt/lampp/bin/php" ]; then
    PHP_BIN="/opt/lampp/bin/php"
fi

if [ -z "$PHP_BIN" ]; then
    echo -e "${RED}❌ PHP 8.1+ not found on your system.${NC}"
    if [[ "$OSTYPE" == "darwin"* ]]; then
        echo -e "   Please install PHP via Homebrew: ${YELLOW}brew install php${NC}"
    else
        echo -e "   Please install PHP: ${YELLOW}sudo apt-get install php php-mysql php-gd php-mbstring${NC}"
    fi
    exit 1
fi

echo -e "⚙️  PHP Executable: ${GREEN}$PHP_BIN${NC}"

# 2. Run Portable Setup & Health Check
"$PHP_BIN" scripts/portable_setup.php

PORT=8000
SERVER_URL="http://localhost:$PORT"

echo ""
echo -e "${CYAN}=======================================================================${NC}"
echo -e "${GREEN} 🚀 LAUNCHING SOLVEX REPORT SERVER${NC}"
echo -e "${CYAN}=======================================================================${NC}"
echo -e " 🌐 Access URL:       ${CYAN}${SERVER_URL}${NC}"
echo -e ""
echo -e " 🎓 DEMO ACCOUNTS:"
echo -e "    • Student:        ${YELLOW}student@solvex.edu${NC}  | Password: ${GREEN}Student@123${NC}"
echo -e "    • Staff:          ${YELLOW}staff@solvex.edu${NC}    | Password: ${GREEN}Staff@123${NC}"
echo -e "    • Administrator:  ${YELLOW}admin@solvex.edu${NC}    | Password: ${GREEN}Admin@123${NC}"
echo -e ""
echo -e " 🛑 Press ${RED}Ctrl + C${NC} to shut down the server."
echo -e "${CYAN}=======================================================================${NC}"
echo ""

# 3. Open Browser
if command -v xdg-open >/dev/null 2>&1; then
    (sleep 1 && xdg-open "$SERVER_URL") >/dev/null 2>&1 &
elif command -v open >/dev/null 2>&1; then
    (sleep 1 && open "$SERVER_URL") >/dev/null 2>&1 &
fi

# 4. Start Server
exec "$PHP_BIN" -S "127.0.0.1:$PORT" router.php

