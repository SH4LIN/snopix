<?php
/**
 * Base test case for all Pixel Scout tests.
 *
 * @package Pixel_Scout
 */

/**
 * Base test case class.
 */
class Pixel_Scout_TestCase extends WP_UnitTestCase {
	/**
	 * Per-test setup. Verifies the plugin class is autoloaded before running.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		// Ensure plugin is loaded.
		$this->assertTrue( class_exists( 'PixelScout\Infrastructure\Plugin' ), 'Plugin class not loaded' );
	}

	/**
	 * Per-test teardown. Flushes the object cache to keep tests isolated.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
		wp_cache_flush();
	}

	/**
	 * Clear every transient written by Pixel Scout's bulk indexer.
	 *
	 * @return void
	 */
	protected function clear_ps_transients(): void {
		delete_transient( 'ps_bulk_progress' );
		delete_transient( 'ps_bulk_total' );
	}

	/**
	 * Get the ps_index table name.
	 *
	 * @return string
	 */
	protected function get_ps_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ps_index';
	}

	/**
	 * Assert that a database table is present in the current MySQL schema.
	 *
	 * @param string $table Fully-qualified table name (including $wpdb prefix).
	 *
	 * @return void
	 */
	protected function assertTableExists( string $table ): void {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertEquals( $table, $result, "Table $table does not exist" );
	}

	/**
	 * Assert that a table contains the expected number of rows.
	 *
	 * @param int    $expected Expected row count.
	 * @param string $table    Fully-qualified table name (including $wpdb prefix).
	 *
	 * @return void
	 */
	protected function assertRowCount( int $expected, string $table ): void {
		global $wpdb;
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$this->assertEquals( $expected, $count, "Row count mismatch in $table" );
	}
}

