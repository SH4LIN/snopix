<?php
/**
 * Tests for Pixel_Scout_Plugin bootstrap and lifecycle.
 *
 * @package Pixel_Scout
 */

require_once __DIR__ . '/../class-testcase.php';

/**
 * Test Plugin bootstrap.
 */
class Pixel_Scout_Plugin_Test extends Pixel_Scout_TestCase {
	/**
	 * Test singleton instance.
	 */
	public function test_instance_is_singleton(): void {
		$instance1 = Pixel_Scout_Plugin::instance();
		$instance2 = Pixel_Scout_Plugin::instance();

		$this->assertSame( $instance1, $instance2 );
		$this->assertInstanceOf( 'Pixel_Scout_Plugin', $instance1 );
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
		Pixel_Scout_Plugin::activate();

		// Table should exist.
		$this->assertTableExists( $table );

		// DB version should be set.
		$version = get_option( PIXEL_SCOUT_OPTION_DB_VERSION );
		$this->assertEquals( PIXEL_SCOUT_DB_VERSION, $version );
	}

	/**
	 * Test deactivation clears cron.
	 */
	public function test_deactivation_hook(): void {
		// Schedule a test event.
		wp_schedule_single_event( time() + 3600, 'ps_bulk_index_batch' );

		// Verify it's scheduled.
		$timestamp = wp_next_scheduled( 'ps_bulk_index_batch' );
		$this->assertNotFalse( $timestamp );

		// Call deactivation.
		Pixel_Scout_Plugin::deactivate();

		// Event should be cleared.
		$timestamp = wp_next_scheduled( 'ps_bulk_index_batch' );
		$this->assertFalse( $timestamp );
	}

	/**
	 * Test uninstall removes table and options.
	 */
	public function test_uninstall_hook(): void {
		global $wpdb;
		$table = $this->get_ps_table();

		// Ensure table and options exist.
		Pixel_Scout_Plugin::activate();
		update_option( 'ps_settings', [ 'search_visibility' => 'anyone' ] );
		set_transient( 'ps_bulk_progress', 50 );
		set_transient( 'ps_bulk_total', 100 );

		// Call uninstall.
		Pixel_Scout_Plugin::uninstall();

		// Table should be removed.
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertNull( $result );

		// Options should be removed.
		$settings = get_option( 'ps_settings' );
		$this->assertEmpty( $settings );

		$db_version = get_option( PIXEL_SCOUT_OPTION_DB_VERSION );
		$this->assertEmpty( $db_version );

		// Transients should be removed.
		$progress = get_transient( 'ps_bulk_progress' );
		$this->assertFalse( $progress );

		$total = get_transient( 'ps_bulk_total' );
		$this->assertFalse( $total );
	}

	/**
	 * Test register method doesn't error.
	 */
	public function test_register_method(): void {
		$plugin = Pixel_Scout_Plugin::instance();
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
		$plugin = Pixel_Scout_Plugin::instance();
		$plugin->load_textdomain();
		$this->assertTrue( true );
	}

	/**
	 * Test activation is idempotent.
	 */
	public function test_activation_is_idempotent(): void {
		$table = $this->get_ps_table();

		// First activation.
		Pixel_Scout_Plugin::activate();
		$this->assertTableExists( $table );

		// Second activation should not error.
		Pixel_Scout_Plugin::activate();
		$this->assertTableExists( $table );
	}
}

