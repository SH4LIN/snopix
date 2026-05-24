<?php
/**
 * Plugin Name: Pixel Scout
 * Description: Image similarity search for WordPress media library.
 * Version: 0.1.0
 * Author: Pixel Scout
 * Text Domain: pixel-scout
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPLv2 or later
 *
 * @package Pixel_Scout
 */

use PixelScout\Infrastructure\Autoloader;
use PixelScout\Infrastructure\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PIXEL_SCOUT_VERSION' ) ) {
	define( 'PIXEL_SCOUT_VERSION', '0.1.0' );
}

if ( ! defined( 'PIXEL_SCOUT_DB_VERSION' ) ) {
	define( 'PIXEL_SCOUT_DB_VERSION', '0.1.0' );
}

if ( ! defined( 'PIXEL_SCOUT_FILE' ) ) {
	define( 'PIXEL_SCOUT_FILE', __FILE__ );
}

if ( ! defined( 'PIXEL_SCOUT_PLUGIN_DIR' ) ) {
	define( 'PIXEL_SCOUT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'PIXEL_SCOUT_PLUGIN_URL' ) ) {
	define( 'PIXEL_SCOUT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'PIXEL_SCOUT_OPTION_DB_VERSION' ) ) {
	define( 'PIXEL_SCOUT_OPTION_DB_VERSION', 'ps_db_version' );
}

if ( ! defined( 'PIXEL_SCOUT_TABLE' ) ) {
	global $wpdb;
	define( 'PIXEL_SCOUT_TABLE', $wpdb->prefix . 'ps_index' );
}

require_once PIXEL_SCOUT_PLUGIN_DIR . 'includes/infrastructure/class-autoloader.php';
Autoloader::init( PIXEL_SCOUT_PLUGIN_DIR . 'includes' );

require_once PIXEL_SCOUT_PLUGIN_DIR . 'includes/infrastructure/functions.php';

register_activation_hook( __FILE__, array( 'PixelScout\Infrastructure\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PixelScout\Infrastructure\Plugin', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'PixelScout\Infrastructure\Plugin', 'uninstall' ) );

Plugin::instance()->register();
