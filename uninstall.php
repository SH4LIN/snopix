<?php
/**
 * Uninstall routine for Pixel Scout.
 *
 * @package Pixel_Scout
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'PIXEL_SCOUT_DB_VERSION' ) ) {
	define( 'PIXEL_SCOUT_DB_VERSION', '1.0.0' );
}

if ( ! defined( 'PIXEL_SCOUT_OPTION_DB_VERSION' ) ) {
	define( 'PIXEL_SCOUT_OPTION_DB_VERSION', 'ps_db_version' );
}

require_once __DIR__ . '/includes/repository/class-schema.php';

$schema = new Pixel_Scout_Schema();
$schema->uninstall();

delete_option( 'ps_settings' );
delete_option( PIXEL_SCOUT_OPTION_DB_VERSION );
delete_transient( 'ps_bulk_progress' );
delete_transient( 'ps_bulk_total' );
