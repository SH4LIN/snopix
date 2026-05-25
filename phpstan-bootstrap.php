<?php
/**
 * PHPStan bootstrap — defines constants required for static analysis.
 *
 * @package Snopix
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

define( 'SNOPIX_VERSION', '0.1.0' );
define( 'SNOPIX_DB_VERSION', '0.1.0' );
define( 'SNOPIX_FILE', __DIR__ . '/snopix.php' );
define( 'SNOPIX_PLUGIN_DIR', __DIR__ . '/' );
define( 'SNOPIX_PLUGIN_URL', 'http://localhost/' );
define( 'SNOPIX_OPTION_DB_VERSION', 'snopix_db_version' );
define( 'SNOPIX_TABLE', 'wp_snopix_index' );
