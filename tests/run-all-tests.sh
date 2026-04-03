#!/bin/bash
#
# WP Career Board — run all test suites.
#
# Usage (from the WP root):
#   bash wp-content/plugins/wp-career-board/tests/run-all-tests.sh
#
# Or make executable:
#   chmod +x wp-content/plugins/wp-career-board/tests/run-all-tests.sh
#   ./wp-content/plugins/wp-career-board/tests/run-all-tests.sh

set -e

echo "=== Seeding test data ==="
wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/seed-data.php
echo ""

echo "=== WP-CLI Command Tests ==="
wp eval-file wp-content/plugins/wp-career-board/tests/test-cli-commands.php
echo ""

echo "=== REST API Tests (Free) ==="
wp eval-file wp-content/plugins/wp-career-board/tests/test-rest-api-free.php
echo ""

echo "=== REST API Tests (Pro) ==="
wp eval-file wp-content/plugins/wp-career-board-pro/tests/test-rest-api-pro.php
echo ""

echo "=== Cleaning up test data ==="
wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/cleanup-seed-data.php
echo ""

echo "=== ALL SUITES COMPLETE ==="
