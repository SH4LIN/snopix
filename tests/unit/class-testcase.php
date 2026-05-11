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
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		// Ensure plugin is loaded.
		$this->assertTrue( class_exists( 'Pixel_Scout_Plugin' ), 'Plugin class not loaded' );
	}

	/**
	 * Tear down after each test.
	 */
	public function tearDown(): void {
		parent::tearDown();
		wp_cache_flush();
	}

	/**
	 * Helper to clear all Pixel Scout transients.
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
	 * Assert table exists.
	 *
	 * @param string $table Table name.
	 */
	protected function assertTableExists( string $table ): void {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertEquals( $table, $result, "Table $table does not exist" );
	}

	/**
	 * Assert row count matches.
	 *
	 * @param int    $expected Expected count.
	 * @param string $table Table name.
	 */
	protected function assertRowCount( int $expected, string $table ): void {
		global $wpdb;
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$this->assertEquals( $expected, $count, "Row count mismatch in $table" );
	}
}

