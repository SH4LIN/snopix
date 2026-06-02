<?php
/**
 * Integration tests for Snopix\Repository\Schema.
 *
 * These tests mutate the shared plugin table (drop/re-install). The table is
 * always restored in tear_down() so later tests still find it in place.
 *
 * @package Snopix
 */

use Snopix\Repository\Schema;

/**
 * @covers \Snopix\Repository\Schema
 */
final class Schema_Test extends Snopix_Integration_TestCase {

	private Schema $schema;

	public function set_up(): void {
		parent::set_up();
		$this->schema = new Schema();

		// WP_UnitTestCase rewrites CREATE/DROP TABLE into TEMPORARY ops per
		// test, which would operate on a separate namespace from the real
		// bootstrap-created table. Remove those filters so this class's DDL
		// (install/uninstall) acts on the actual plugin table; tear_down()
		// always re-installs it for later tests.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
	}

	/**
	 * Restore the shared table for any later test, regardless of what this test
	 * did to it.
	 */
	public function tear_down(): void {
		( new Schema() )->install();
		parent::tear_down();
	}

	/**
	 * Resolve the fully-prefixed plugin table name.
	 */
	private function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'snopix_index';
	}

	/**
	 * Whether the plugin table currently exists.
	 */
	private function table_exists(): bool {
		global $wpdb;
		$table = $this->table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Column names present on the plugin table.
	 *
	 * @return array<int, string>
	 */
	private function column_names(): array {
		global $wpdb;
		$table = $this->table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	}

	/**
	 * Index rows from SHOW INDEX, keyed and grouped by key name.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function indexes_by_name(): array {
		global $wpdb;
		$table = $this->table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SHOW INDEX FROM {$table}", ARRAY_A );

		$by_name = array();
		foreach ( $rows as $row ) {
			$by_name[ $row['Key_name'] ][] = $row;
		}
		return $by_name;
	}

	public function test_install_creates_table_with_expected_columns(): void {
		$this->assertTrue( $this->table_exists() );

		$columns = $this->column_names();
		foreach (
			array(
				'attachment_id',
				'phash',
				'color_vector',
				'edge_vector',
				'file_hash',
				'error_code',
				'indexed_at',
			) as $column
		) {
			$this->assertContains( $column, $columns, "Missing column: {$column}" );
		}
	}

	public function test_install_creates_unique_and_secondary_keys(): void {
		$indexes = $this->indexes_by_name();

		$this->assertArrayHasKey( 'attachment_id', $indexes );
		// Non_unique 0 means a UNIQUE key.
		$this->assertSame( '0', (string) $indexes['attachment_id'][0]['Non_unique'] );

		$this->assertArrayHasKey( 'idx_file_hash', $indexes );
		$this->assertArrayHasKey( 'idx_error_code', $indexes );
	}

	public function test_install_sets_db_version_option(): void {
		// Force a different stored value to prove install() rewrites it.
		update_option( SNOPIX_OPTION_DB_VERSION, 'stale' );

		$this->schema->install();

		$this->assertSame( SNOPIX_DB_VERSION, get_option( SNOPIX_OPTION_DB_VERSION ) );
	}

	public function test_maybe_upgrade_is_noop_when_versions_match(): void {
		update_option( SNOPIX_OPTION_DB_VERSION, SNOPIX_DB_VERSION );

		$this->schema->maybe_upgrade();

		$this->assertSame( SNOPIX_DB_VERSION, get_option( SNOPIX_OPTION_DB_VERSION ) );
		$this->assertTrue( $this->table_exists() );
	}

	public function test_maybe_upgrade_reinstalls_when_versions_differ(): void {
		update_option( SNOPIX_OPTION_DB_VERSION, '0.0.0' );

		$this->schema->maybe_upgrade();

		// install() ran, bumping the option back to the current version.
		$this->assertSame( SNOPIX_DB_VERSION, get_option( SNOPIX_OPTION_DB_VERSION ) );
		$this->assertTrue( $this->table_exists() );
	}

	public function test_uninstall_drops_the_table(): void {
		$this->assertTrue( $this->table_exists() );

		$this->schema->uninstall();

		$this->assertFalse( $this->table_exists() );
	}
}
