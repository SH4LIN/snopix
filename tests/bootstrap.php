<?php
/**
 * PHPUnit bootstrap for Pixel Scout tests.
 *
 * @package Pixel_Scout
 */

// Load WordPress test suite.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_tests_dir ) {
	$wp_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test suite not found at $wp_tests_dir\n";
	exit( 1 );
}

require_once $wp_tests_dir . '/includes/functions.php';

// Bootstrap the plugin.
function _manually_load_plugin() {
	require_once dirname( dirname( __FILE__ ) ) . '/pixel-scout.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start the test suite.
require_once $wp_tests_dir . '/includes/bootstrap.php';

