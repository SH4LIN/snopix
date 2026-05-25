<?php
/**
 * Tests for Snopix_Schema database management.
 *
 * @package Snopix
 */

require_once __DIR__ . '/../class-testcase.php';

use Snopix\Repository\Schema;

/**
 * Test Schema manager.
 */
class Snopix_Schema_Test extends Snopix_TestCase {
	/**
	 * Table name for tests.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->table = $this->get_ps_table();
	}

	/**
	 * Test table is created on install.
	 */
	public function test_install_creates_table(): void {
		// Drop table if it exists from previous test.
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$this->table}" );

		$schema = new Schema();
		$schema->install();

		$this->assertTableExists( $this->table );
	}

	/**
	 * Test table has correct columns.
	 */
	public function test_table_has_correct_columns(): void {
		$schema = new Schema();
		$schema->install();

		global $wpdb;
		$columns = $wpdb->get_results( "DESCRIBE {$this->table}" );

		$column_names = wp_list_pluck( $columns, 'Field' );
		$expected = [
			'id',
			'attachment_id',
			'phash',
			'color_vector',
			'edge_vector',
			'width',
			'height',
			'mime_type',
			'file_size',
			'indexed_at',
		];

		foreach ( $expected as $col ) {
			$this->assertContains( $col, $column_names, "Column $col not found" );
		}
	}

	/**
	 * Test table has primary key.
	 */
	public function test_table_has_primary_key(): void {
		$schema = new Schema();
		$schema->install();

		global $wpdb;
		$keys = $wpdb->get_results( "SHOW KEYS FROM {$this->table} WHERE Key_name = 'PRIMARY'" );

		$this->assertNotEmpty( $keys, 'PRIMARY key not found' );
	}

	/**
	 * Test table has unique constraint on attachment_id.
	 */
	public function test_table_has_unique_attachment_id(): void {
		$schema = new Schema();
		$schema->install();

		global $wpdb;
		$keys = $wpdb->get_results( "SHOW KEYS FROM {$this->table} WHERE Key_name = 'attachment_id'" );

		$this->assertNotEmpty( $keys, 'UNIQUE key on attachment_id not found' );
	}

	/**
	 * Test table has index on phash.
	 */
	public function test_table_has_phash_index(): void {
		$schema = new Schema();
		$schema->install();

		global $wpdb;
		$keys = $wpdb->get_results( "SHOW KEYS FROM {$this->table} WHERE Key_name = 'idx_phash'" );

		$this->assertNotEmpty( $keys, 'Index idx_phash not found' );
	}

	/**
	 * Test uninstall removes table.
	 */
	public function test_uninstall_drops_table(): void {
		// WP test suite redirects CREATE/DROP TABLE to TEMPORARY TABLE to protect the DB.
		// Bypass to test real DDL.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$this->table}" );

		$schema = new Schema();
		$schema->install();
		$this->assertTableExists( $this->table );

		$schema->uninstall();

		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table ) );
		$this->assertNull( $result, "Table $this->table still exists after uninstall" );

		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
	}

	/**
	 * Test db version option is set during install.
	 */
	public function test_install_sets_db_version(): void {
		delete_option( SNOPIX_OPTION_DB_VERSION );

		$schema = new Schema();
		$schema->install();

		$version = get_option( SNOPIX_OPTION_DB_VERSION );
		$this->assertEquals( SNOPIX_DB_VERSION, $version );
	}

	/**
	 * Test maybe_upgrade skips if version matches.
	 */
	public function test_maybe_upgrade_skips_if_current(): void {
		$schema = new Schema();
		$schema->install();

		// Update to current version.
		update_option( SNOPIX_OPTION_DB_VERSION, SNOPIX_DB_VERSION );

		// Call maybe_upgrade - should not re-create table.
		$schema->maybe_upgrade();

		$this->assertTableExists( $this->table );
	}

	/**
	 * Test maybe_upgrade triggers if version mismatch.
	 */
	public function test_maybe_upgrade_triggers_if_stale(): void {
		$schema = new Schema();
		$schema->install();

		// Force old version.
		update_option( SNOPIX_OPTION_DB_VERSION, '0.0.1' );

		// Call maybe_upgrade - should update.
		$schema->maybe_upgrade();

		$version = get_option( SNOPIX_OPTION_DB_VERSION );
		$this->assertEquals( SNOPIX_DB_VERSION, $version );
	}

	/**
	 * Test install is idempotent.
	 */
	public function test_install_is_idempotent(): void {
		$schema = new Schema();

		$schema->install();
		$rows_after_first = $this->get_row_count();

		// Should not error on second install.
		$schema->install();
		$rows_after_second = $this->get_row_count();

		$this->assertEquals( $rows_after_first, $rows_after_second );
	}

	/**
	 * Get row count in test table.
	 *
	 * @return int
	 */
	private function get_row_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}
}

