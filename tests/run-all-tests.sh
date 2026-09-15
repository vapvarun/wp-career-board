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

# Clean up even when a suite fails - otherwise a red run leaves seed data
# behind and the next run starts dirty.
trap 'echo ""; echo "=== Cleaning up test data ==="; wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/cleanup-seed-data.php' EXIT

echo "=== Seeding test data ==="
wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/seed-data.php
echo ""

echo "=== REST Exposure Contract ==="
wp eval-file wp-content/plugins/wp-career-board/tests/audit/rest-exposure.php
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

echo "=== Settings Accessor Tests ==="
wp eval-file wp-content/plugins/wp-career-board/tests/test-settings-accessor.php
echo ""

echo "=== Pages Resolver Tests ==="
wp eval-file wp-content/plugins/wp-career-board/tests/test-pages-resolver.php
echo ""

echo "=== App Auth Tests ==="
wp eval-file wp-content/plugins/wp-career-board/tests/test-app-auth.php
echo ""

echo "=== Applications Role Split Tests ==="
wp eval-file wp-content/plugins/wp-career-board/tests/test-applications-role-split.php
echo ""

echo "=== Cron Event Tests ==="
wp eval-file wp-content/plugins/wp-career-board/tests/test-cron-events.php
echo ""

echo "=== Scale Harness Tests ==="
wp eval-file wp-content/plugins/wp-career-board/tests/test-scale-harness.php
echo ""

echo "=== ALL SUITES COMPLETE ==="
