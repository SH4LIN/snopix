<?php
/**
 * Tests for Snopix_Plugin bootstrap and lifecycle.
 *
 * @package Snopix
 */

require_once __DIR__ . '/../class-testcase.php';

use Snopix\Infrastructure\Plugin;

/**
 * Test Plugin bootstrap.
 */
class Snopix_Plugin_Test extends Snopix_TestCase {
	/**
	 * Test singleton instance.
	 */
	public function test_instance_is_singleton(): void {
		$instance1 = Plugin::instance();
		$instance2 = Plugin::instance();

		$this->assertSame( $instance1, $instance2 );
		$this->assertInstanceOf( Plugin::class, $instance1 );
	}

	/**
	 * Test activation creates table.
	 */
	public function test_activation_hook(): void {
		global $wpdb;
		$table = $this->get_ps_table();

		// Drop table to ensure clean state.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

		// Call activation.
		Plugin::activate();

		// Table should exist.
		$this->assertTableExists( $table );

		// DB version should be set.
		$version = get_option( SNOPIX_OPTION_DB_VERSION );
		$this->assertEquals( SNOPIX_DB_VERSION, $version );
	}

	/**
	 * Test deactivation clears cron.
	 */
	public function test_deactivation_hook(): void {
		// Schedule a test event.
		wp_schedule_single_event( time() + 3600, 'snopix_bulk_index_batch' );

		// Verify it's scheduled.
		$timestamp = wp_next_scheduled( 'snopix_bulk_index_batch' );
		$this->assertNotFalse( $timestamp );

		// Call deactivation.
		Plugin::deactivate();

		// Event should be cleared.
		$timestamp = wp_next_scheduled( 'snopix_bulk_index_batch' );
		$this->assertFalse( $timestamp );
	}

	/**
	 * Test uninstall removes table and options.
	 */
	public function test_uninstall_hook(): void {
		global $wpdb;
		$table = $this->get_ps_table();

		// Bypass WP test suite DDL filters so DROP TABLE runs on permanent table.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

		// Ensure table and options exist.
		Plugin::activate();
		update_option( 'snopix_settings', [ 'search_visibility' => 'anyone' ] );
		set_transient( 'snopix_bulk_progress', 50 );
		set_transient( 'snopix_bulk_total', 100 );

		// Call uninstall.
		Plugin::uninstall();

		// Table should be removed.
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertNull( $result );

		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		// Options should be removed.
		$settings = get_option( 'snopix_settings' );
		$this->assertEmpty( $settings );

		$db_version = get_option( SNOPIX_OPTION_DB_VERSION );
		$this->assertEmpty( $db_version );

		// Transients should be removed.
		$progress = get_transient( 'snopix_bulk_progress' );
		$this->assertFalse( $progress );

		$total = get_transient( 'snopix_bulk_total' );
		$this->assertFalse( $total );
	}

	/**
	 * Test register method doesn't error.
	 */
	public function test_register_method(): void {
		$plugin = Plugin::instance();
		$plugin->register();
		// Just verify no errors are thrown.
		$this->assertTrue( true );
	}

	/**
	 * Test text domain loading.
	 */
	public function test_textdomain_loads(): void {
		// This is hard to test without full WordPress environment,
		// but we can at least verify the function exists and doesn't error.
		$plugin = Plugin::instance();
		$plugin->load_textdomain();
		$this->assertTrue( true );
	}

	/**
	 * Test activation is idempotent.
	 */
	public function test_activation_is_idempotent(): void {
		$table = $this->get_ps_table();

		// First activation.
		Plugin::activate();
		$this->assertTableExists( $table );

		// Second activation should not error.
		Plugin::activate();
		$this->assertTableExists( $table );
	}
}

