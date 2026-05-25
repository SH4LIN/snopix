<?php
/**
 * PHPUnit bootstrap for Snopix tests.
 *
 * @package Snopix
 */

// Load PHPUnit polyfills before WordPress test suite to provide removed PHPUnit classes.
require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// Point to wp-tests-config.php in plugin root.
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', dirname( __DIR__ ) . '/wp-tests-config.php' );
}

// Use vendor wp-phpunit package (PHPUnit 11 compatible) unless overridden.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test suite not found at $wp_tests_dir\n";
	exit( 1 );
}

require_once $wp_tests_dir . '/includes/functions.php';

/**
 * Bootstrap the plugin under the WP test harness.
 *
 * Hooked onto `muplugins_loaded` so the plugin loads after WordPress core
 * but before any test fixtures are created.
 *
 * @return void
 */
function _manually_load_plugin() {
	require_once dirname( dirname( __FILE__ ) ) . '/snopix.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start the test suite.
require_once $wp_tests_dir . '/includes/bootstrap.php';
