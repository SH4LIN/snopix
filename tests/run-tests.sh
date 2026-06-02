#!/usr/bin/env bash
#
# Snopix test runner.
#
#   ./tests/run-tests.sh unit          pure unit suite on the host (no WP/DB)
#   ./tests/run-tests.sh integration   integration suite inside wp-env tests-cli
#   ./tests/run-tests.sh e2e           Playwright chromium against the dev site
#   ./tests/run-tests.sh all           all three in order
#
# Integration/e2e require `npx wp-env start` to have been run first.

set -euo pipefail

cd "$(dirname "$0")/.."

run_unit() {
	vendor/bin/phpunit -c phpunit-unit.xml.dist
}

run_integration() {
	npx wp-env run tests-cli \
		--env-cwd=wp-content/plugins/snopix \
		vendor/bin/phpunit -c phpunit-integration.xml.dist
}

run_e2e() {
	npx playwright test --project=chromium
}

case "${1:-all}" in
	unit) run_unit ;;
	integration) run_integration ;;
	e2e) run_e2e ;;
	all)
		run_unit
		run_integration
		run_e2e
		;;
	*)
		echo "usage: $0 {unit|integration|e2e|all}" >&2
		exit 1
		;;
esac
