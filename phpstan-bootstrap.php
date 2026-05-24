<?php
/**
 * PHPStan bootstrap — defines constants required for static analysis.
 *
 * @package Pixel_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

define( 'PIXEL_SCOUT_VERSION', '0.1.0' );
define( 'PIXEL_SCOUT_DB_VERSION', '0.1.0' );
define( 'PIXEL_SCOUT_FILE', __DIR__ . '/pixel-scout.php' );
define( 'PIXEL_SCOUT_PLUGIN_DIR', __DIR__ . '/' );
define( 'PIXEL_SCOUT_PLUGIN_URL', 'http://localhost/' );
define( 'PIXEL_SCOUT_OPTION_DB_VERSION', 'ps_db_version' );
define( 'PIXEL_SCOUT_TABLE', 'wp_ps_index' );
