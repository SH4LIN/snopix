<?php
/**
 * Uninstall handler for Snopix.
 *
 * Fired by WordPress when the plugin is deleted from the Plugins screen.
 * Delegates to Plugin::uninstall() which drops the index table, options,
 * and transients.
 *
 * @package Snopix
 */

use Snopix\Infrastructure\Autoloader;
use Snopix\Infrastructure\Plugin;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'SNOPIX_OPTION_DB_VERSION' ) ) {
	define( 'SNOPIX_OPTION_DB_VERSION', 'snopix_db_version' );
}

require_once __DIR__ . '/includes/infrastructure/class-autoloader.php';
Autoloader::init( __DIR__ . '/includes' );

Plugin::uninstall();
