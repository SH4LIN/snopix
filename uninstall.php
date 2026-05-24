<?php
/**
 * Uninstall handler for Pixel Scout.
 *
 * Fired by WordPress when the plugin is deleted from the Plugins screen.
 * Delegates to Plugin::uninstall() which drops the index table, options,
 * and transients.
 *
 * @package Pixel_Scout
 */

use PixelScout\Infrastructure\Autoloader;
use PixelScout\Infrastructure\Plugin;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'PIXEL_SCOUT_OPTION_DB_VERSION' ) ) {
	define( 'PIXEL_SCOUT_OPTION_DB_VERSION', 'ps_db_version' );
}

require_once __DIR__ . '/includes/infrastructure/class-autoloader.php';
Autoloader::init( __DIR__ . '/includes' );

Plugin::uninstall();
