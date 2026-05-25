<?php
/**
 * Database schema management.
 *
 * @package Snopix
 */

namespace Snopix\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Manages database table schema for the plugin.
 */
class Schema {
	/**
	 * Create or update plugin tables.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'snopix_index';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			attachment_id BIGINT(20) UNSIGNED NOT NULL,
			phash CHAR(16) NOT NULL DEFAULT '',
			color_vector JSON DEFAULT NULL,
			edge_vector JSON DEFAULT NULL,
			width SMALLINT UNSIGNED DEFAULT 0,
			height SMALLINT UNSIGNED DEFAULT 0,
			mime_type VARCHAR(50) DEFAULT '',
			file_size BIGINT UNSIGNED DEFAULT 0,
			file_hash CHAR(32) NOT NULL DEFAULT '',
			error_code VARCHAR(64) NOT NULL DEFAULT '',
			indexed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY attachment_id (attachment_id),
			KEY idx_error_code (error_code),
			KEY idx_file_hash (file_hash)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( SNOPIX_OPTION_DB_VERSION, SNOPIX_DB_VERSION );
	}

	/**
	 * Drop plugin table.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		global $wpdb;

		// Table identifier is built from $wpdb->prefix and a literal — no user input is interpolated.
		$table_name = esc_sql( $wpdb->prefix . 'snopix_index' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}

	/**
	 * Run migrations if db version changed.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$installed_version = get_option( SNOPIX_OPTION_DB_VERSION, '' );

		if ( SNOPIX_DB_VERSION === $installed_version ) {
			return;
		}

		$this->install();
	}
}
