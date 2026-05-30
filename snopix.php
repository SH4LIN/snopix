<?php
/**
 * Plugin Name: Snopix
 * Plugin URI: https://github.com/SH4LIN/snopix
 * Description: Image similarity search for WordPress media library.
 * Version: 0.1.2
 * Author: SH4LIN
 * Text Domain: snopix
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPLv2 or later
 *
 * @package Snopix
 */

use Snopix\Infrastructure\Autoloader;
use Snopix\Infrastructure\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SNOPIX_VERSION' ) ) {
	define( 'SNOPIX_VERSION', '0.1.2' );
}

if ( ! defined( 'SNOPIX_DB_VERSION' ) ) {
	define( 'SNOPIX_DB_VERSION', '0.1.1' );
}

if ( ! defined( 'SNOPIX_FILE' ) ) {
	define( 'SNOPIX_FILE', __FILE__ );
}

if ( ! defined( 'SNOPIX_PLUGIN_DIR' ) ) {
	define( 'SNOPIX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'SNOPIX_PLUGIN_URL' ) ) {
	define( 'SNOPIX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'SNOPIX_OPTION_DB_VERSION' ) ) {
	define( 'SNOPIX_OPTION_DB_VERSION', 'snopix_db_version' );
}

if ( ! defined( 'SNOPIX_TABLE' ) ) {
	global $wpdb;
	define( 'SNOPIX_TABLE', $wpdb->prefix . 'snopix_index' );
}

require_once SNOPIX_PLUGIN_DIR . 'includes/infrastructure/class-autoloader.php';
Autoloader::init( SNOPIX_PLUGIN_DIR . 'includes' );

require_once SNOPIX_PLUGIN_DIR . 'includes/infrastructure/functions.php';

register_activation_hook( __FILE__, array( 'Snopix\Infrastructure\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Snopix\Infrastructure\Plugin', 'deactivate' ) );

Plugin::instance()->register();
