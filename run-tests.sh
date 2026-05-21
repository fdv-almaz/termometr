#!/bin/bash

# Termometr Test Runner
# Usage: ./run-tests.sh [all|api|database|integration]

set -e

TESTS_DIR="tests"
VENDOR_BIN="vendor/bin/phpunit"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if phpunit is installed
if [ ! -f "$VENDOR_BIN" ]; then
    echo -e "${YELLOW}PHPUnit not found. Installing dependencies...${NC}"
    composer install
fi

# Default to all tests
TEST_SUITE="${1:-all}"

case $TEST_SUITE in
    all)
        echo -e "${GREEN}Running all tests...${NC}"
        $VENDOR_BIN
        ;;
    api)
        echo -e "${GREEN}Running API tests...${NC}"
        $VENDOR_BIN tests/unit/Api/
        ;;
    database)
        echo -e "${GREEN}Running database tests...${NC}"
        $VENDOR_BIN tests/unit/Database/
        ;;
    integration)
        echo -e "${GREEN}Running integration tests...${NC}"
        $VENDOR_BIN tests/unit/Integration/
        ;;
    coverage)
        echo -e "${GREEN}Generating coverage report...${NC}"
        $VENDOR_BIN --coverage-html coverage/
        echo -e "${GREEN}Coverage report generated in coverage/index.html${NC}"
        ;;
    watch)
        echo -e "${GREEN}Watching for changes...${NC}"
        if command -v phpunit-watcher &> /dev/null; then
            phpunit-watcher watch
        else
            echo -e "${YELLOW}phpunit-watcher not installed. Run: composer require --dev spatie/phpunit-watcher${NC}"
        fi
        ;;
    *)
        echo -e "${RED}Unknown test suite: $TEST_SUITE${NC}"
        echo "Usage: ./run-tests.sh [all|api|database|integration|coverage|watch]"
        exit 1
        ;;
esac
