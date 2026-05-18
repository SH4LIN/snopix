#!/usr/bin/env bash
# Pixel Scout test runner commands — copy/paste as needed.

CONTAINER=$(docker ps --format "{{.Names}}" | grep "tests-cli" | head -1)
PLUGIN_PATH="/var/www/html/wp-content/plugins/pixel-scout"
PHPUNIT="WP_TESTS_DIR=/wordpress-phpunit php $PLUGIN_PATH/vendor/bin/phpunit --configuration $PLUGIN_PATH/phpunit.xml.dist"

# ---------------------------------------------------------------------------
# UNIT TESTS (requires: npx wp-env start)
# ---------------------------------------------------------------------------

# Run all unit tests
docker exec $CONTAINER bash -c "$PHPUNIT"

# Run a specific test class (replace filter value as needed)
docker exec $CONTAINER bash -c "$PHPUNIT --filter Pixel_Scout_Repository"
docker exec $CONTAINER bash -c "$PHPUNIT --filter Pixel_Scout_Schema"
docker exec $CONTAINER bash -c "$PHPUNIT --filter Pixel_Scout_Plugin"
docker exec $CONTAINER bash -c "$PHPUNIT --filter Pixel_Scout_Query"

# Run a specific test method
docker exec $CONTAINER bash -c "$PHPUNIT --filter test_upsert_inserts_new_row"

# Confirm container name (run this if the above fails)
docker ps --format "{{.Names}}" | grep tests-cli

# ---------------------------------------------------------------------------
# E2E TESTS (requires: npx wp-env start, npm install)
# ---------------------------------------------------------------------------

# First-time setup — install Playwright browsers (run once)
npx playwright install chromium

# Download fixture images for indexing test (run once, skips already-downloaded)
php tests/bin/download-fixtures.php

# Run all e2e tests (headless, Chromium only — fastest)
npx playwright test --project=chromium

# Run all e2e tests across all browsers
npm run test:e2e

# Run a specific spec file
npx playwright test tests/e2e/admin.spec.ts --project=chromium
npx playwright test tests/e2e/indexing.spec.ts --project=chromium
npx playwright test tests/e2e/shortcode.spec.ts --project=chromium

# Run with browser visible
npm run test:e2e:headed

# Run with interactive Playwright UI
npm run test:e2e:ui

# Debug mode (step through)
npm run test:e2e:debug

# View HTML report from last run
npm run test:e2e:report

# Override base URL (default: http://localhost:8000)
# WORDPRESS_URL=http://localhost:8888 npx playwright test --project=chromium

# Override WP admin credentials (default: admin / password)
# WP_ADMIN_USER=admin WP_ADMIN_PASSWORD=password npx playwright test --project=chromium
