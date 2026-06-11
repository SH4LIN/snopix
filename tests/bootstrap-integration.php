<?php
/**
 * Bootstrap for integration tests - boots the full WordPress test suite and
 * loads the plugin so tests run against real WP, the database, and the plugin's
 * cron/REST/hook surface.
 *
 * Resolution order for the WP test scaffold:
 *   1. WP_TESTS_DIR env (wp-env's tests-cli sets this to /wordpress-phpunit),
 *   2. the vendored wp-phpunit copy as a fallback for host/CI runs.
 *
 * @package Snopix
 */

require_once __DIR__ . '/fixtures/extract-images.php';

$snopix_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $snopix_tests_dir || ! file_exists( $snopix_tests_dir . '/includes/functions.php' ) ) {
	$snopix_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! file_exists( $snopix_tests_dir . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		"Could not locate the WordPress test suite. Set WP_TESTS_DIR or run inside wp-env.\n"
	);
	exit( 1 );
}

require_once $snopix_tests_dir . '/includes/functions.php';

/**
 * Load the plugin into the test WordPress instance once mu-plugins are loaded.
 */
tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/snopix.php';
	}
);

require $snopix_tests_dir . '/includes/bootstrap.php';

// The plugin's custom table is created with dbDelta (DDL), which implicitly
// commits and so cannot live inside WP_UnitTestCase's per-test transaction.
// Install it once here; per-test row writes still roll back normally.
( new \Snopix\Repository\Schema() )->install();

require_once __DIR__ . '/integration/class-integration-testcase.php';
