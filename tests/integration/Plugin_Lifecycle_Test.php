<?php
/**
 * Integration tests for plugin lifecycle: activate, deactivate, uninstall.
 *
 * @package Snopix
 */

use Snopix\Infrastructure\Plugin;
use Snopix\Repository\Schema;
use Snopix\Hooks\Settings;
use Snopix\Duplicates\Duplicate_Scanner;
use Snopix\Indexing\Bulk_Indexer;

/**
 * @covers \Snopix\Infrastructure\Plugin
 */
final class Plugin_Lifecycle_Test extends Snopix_Integration_TestCase {

	public function set_up(): void {
		parent::set_up();

		// WP_UnitTestCase rewrites CREATE/DROP TABLE into TEMPORARY ops per
		// test, which would operate on a separate namespace from the real
		// bootstrap-created table. Remove those filters so DDL in activate()
		// and uninstall() acts on the actual plugin table; tear_down() always
		// re-installs it for later tests.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
	}

	/**
	 * Restore the shared table for any later test, regardless of what this
	 * test did to it.
	 */
	public function tear_down(): void {
		( new Schema() )->install();
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Fully-prefixed plugin table name.
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

	// -------------------------------------------------------------------------
	// activate()
	// -------------------------------------------------------------------------

	public function test_activate_installs_schema(): void {
		// Drop the table so we can verify activate() re-creates it.
		( new Schema() )->uninstall();
		$this->assertFalse( $this->table_exists(), 'Precondition: table should not exist before activate.' );

		Plugin::activate();

		$this->assertTrue( $this->table_exists(), 'activate() must create the index table.' );
	}

	public function test_activate_sets_db_version_option(): void {
		delete_option( SNOPIX_OPTION_DB_VERSION );

		Plugin::activate();

		$this->assertSame( SNOPIX_DB_VERSION, get_option( SNOPIX_OPTION_DB_VERSION ) );
	}

	public function test_activate_schedules_duplicate_daily_hook(): void {
		// Clear any existing schedule so the test starts from scratch.
		wp_clear_scheduled_hook( Duplicate_Scanner::DAILY_HOOK );
		$this->assertFalse( wp_next_scheduled( Duplicate_Scanner::DAILY_HOOK ), 'Precondition: hook must not be scheduled.' );

		Plugin::activate();

		$this->assertNotFalse( wp_next_scheduled( Duplicate_Scanner::DAILY_HOOK ), 'activate() must schedule the daily duplicate hook.' );
	}

	public function test_activate_does_not_duplicate_daily_schedule(): void {
		// Run activate() twice; wp_schedule_event should only add one event.
		wp_clear_scheduled_hook( Duplicate_Scanner::DAILY_HOOK );

		Plugin::activate();
		$first_timestamp = wp_next_scheduled( Duplicate_Scanner::DAILY_HOOK );

		Plugin::activate();
		$second_timestamp = wp_next_scheduled( Duplicate_Scanner::DAILY_HOOK );

		$this->assertSame( $first_timestamp, $second_timestamp, 'activate() must not schedule the hook twice.' );
	}

	public function test_activate_sets_redirect_transient_for_fresh_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		// Ensure tour meta is absent so the transient branch fires.
		delete_user_meta( $user_id, 'snopix_tour_completed' );
		$transient_key = 'snopix_activation_redirect_' . $user_id;
		delete_transient( $transient_key );

		Plugin::activate();

		$this->assertNotFalse( get_transient( $transient_key ), 'activate() must set the redirect transient for a fresh user.' );

		// Cleanup.
		delete_transient( $transient_key );
		wp_set_current_user( 0 );
	}

	public function test_activate_does_not_set_redirect_transient_when_tour_completed(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'snopix_tour_completed', 'completed' );
		$transient_key = 'snopix_activation_redirect_' . $user_id;
		delete_transient( $transient_key );

		Plugin::activate();

		$this->assertFalse( get_transient( $transient_key ), 'activate() must not set the redirect transient when the tour is already completed.' );

		// Cleanup.
		delete_user_meta( $user_id, 'snopix_tour_completed' );
		wp_set_current_user( 0 );
	}

	public function test_activate_does_not_set_redirect_transient_when_tour_skipped(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'snopix_tour_completed', 'skipped' );
		$transient_key = 'snopix_activation_redirect_' . $user_id;
		delete_transient( $transient_key );

		Plugin::activate();

		$this->assertFalse( get_transient( $transient_key ), 'activate() must not set the redirect transient when the tour is already skipped.' );

		// Cleanup.
		delete_user_meta( $user_id, 'snopix_tour_completed' );
		wp_set_current_user( 0 );
	}

	// -------------------------------------------------------------------------
	// deactivate()
	// -------------------------------------------------------------------------

	public function test_deactivate_clears_daily_duplicate_hook(): void {
		wp_schedule_event( time() + 3600, 'daily', Duplicate_Scanner::DAILY_HOOK );
		$this->assertNotFalse( wp_next_scheduled( Duplicate_Scanner::DAILY_HOOK ), 'Precondition: hook must be scheduled.' );

		Plugin::deactivate();

		$this->assertFalse( wp_next_scheduled( Duplicate_Scanner::DAILY_HOOK ), 'deactivate() must clear the daily duplicate hook.' );
	}

	public function test_deactivate_clears_duplicate_scan_hook(): void {
		wp_schedule_event( time() + 3600, 'daily', Duplicate_Scanner::CRON_HOOK );
		$this->assertNotFalse( wp_next_scheduled( Duplicate_Scanner::CRON_HOOK ), 'Precondition: hook must be scheduled.' );

		Plugin::deactivate();

		$this->assertFalse( wp_next_scheduled( Duplicate_Scanner::CRON_HOOK ), 'deactivate() must clear the duplicate scan cron hook.' );
	}

	public function test_deactivate_clears_bulk_index_batch_hook(): void {
		wp_schedule_event( time() + 3600, 'daily', Bulk_Indexer::CRON_HOOK );
		$this->assertNotFalse( wp_next_scheduled( Bulk_Indexer::CRON_HOOK ), 'Precondition: hook must be scheduled.' );

		Plugin::deactivate();

		$this->assertFalse( wp_next_scheduled( Bulk_Indexer::CRON_HOOK ), 'deactivate() must clear the bulk index batch hook.' );
	}

	public function test_deactivate_is_safe_when_no_hooks_are_scheduled(): void {
		wp_clear_scheduled_hook( Duplicate_Scanner::DAILY_HOOK );
		wp_clear_scheduled_hook( Duplicate_Scanner::CRON_HOOK );
		wp_clear_scheduled_hook( Bulk_Indexer::CRON_HOOK );

		// Must not throw.
		Plugin::deactivate();

		$this->assertFalse( wp_next_scheduled( Duplicate_Scanner::DAILY_HOOK ) );
		$this->assertFalse( wp_next_scheduled( Duplicate_Scanner::CRON_HOOK ) );
		$this->assertFalse( wp_next_scheduled( Bulk_Indexer::CRON_HOOK ) );
	}

	// -------------------------------------------------------------------------
	// uninstall() - drop_on_uninstall = true
	// -------------------------------------------------------------------------

	public function test_uninstall_drops_table_when_drop_setting_is_true(): void {
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'drop_on_uninstall' => true ) ) );
		$this->assertTrue( $this->table_exists(), 'Precondition: table must exist before uninstall.' );

		Plugin::uninstall();

		$this->assertFalse( $this->table_exists(), 'uninstall() must drop the table when drop_on_uninstall is true.' );
	}

	public function test_uninstall_deletes_settings_option_when_drop_is_true(): void {
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'drop_on_uninstall' => true ) ) );

		Plugin::uninstall();

		$this->assertFalse( get_option( Settings::OPTION_NAME ), 'uninstall() must delete snopix_settings when drop_on_uninstall is true.' );
	}

	public function test_uninstall_deletes_db_version_option_when_drop_is_true(): void {
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'drop_on_uninstall' => true ) ) );
		update_option( SNOPIX_OPTION_DB_VERSION, SNOPIX_DB_VERSION );

		Plugin::uninstall();

		$this->assertFalse( get_option( SNOPIX_OPTION_DB_VERSION ), 'uninstall() must delete the db version option when drop_on_uninstall is true.' );
	}

	// -------------------------------------------------------------------------
	// uninstall() - drop_on_uninstall = false
	// -------------------------------------------------------------------------

	public function test_uninstall_keeps_table_when_drop_setting_is_false(): void {
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'drop_on_uninstall' => false ) ) );
		$this->assertTrue( $this->table_exists(), 'Precondition: table must exist before uninstall.' );

		Plugin::uninstall();

		$this->assertTrue( $this->table_exists(), 'uninstall() must keep the table when drop_on_uninstall is false.' );
	}

	public function test_uninstall_keeps_settings_option_when_drop_is_false(): void {
		$settings = array_merge( Settings::defaults(), array( 'drop_on_uninstall' => false ) );
		update_option( Settings::OPTION_NAME, $settings );

		Plugin::uninstall();

		$stored = get_option( Settings::OPTION_NAME );
		$this->assertIsArray( $stored, 'uninstall() must leave snopix_settings intact when drop_on_uninstall is false.' );
		$this->assertFalse( (bool) $stored['drop_on_uninstall'] );
	}

	public function test_uninstall_keeps_db_version_option_when_drop_is_false(): void {
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'drop_on_uninstall' => false ) ) );
		update_option( SNOPIX_OPTION_DB_VERSION, SNOPIX_DB_VERSION );

		Plugin::uninstall();

		$this->assertSame( SNOPIX_DB_VERSION, get_option( SNOPIX_OPTION_DB_VERSION ), 'uninstall() must leave the db version option intact when drop_on_uninstall is false.' );
	}

	// -------------------------------------------------------------------------
	// uninstall() - transients deleted when drop_on_uninstall is true
	// -------------------------------------------------------------------------

	public function test_uninstall_deletes_bulk_pending_transient(): void {
		set_transient( Bulk_Indexer::PENDING_KEY, 1, 3600 );
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'drop_on_uninstall' => true ) ) );

		Plugin::uninstall();

		$this->assertFalse( get_transient( Bulk_Indexer::PENDING_KEY ), 'uninstall() must delete the bulk pending transient.' );
	}

	public function test_uninstall_deletes_duplicate_scan_state_transient(): void {
		set_transient( 'snopix_duplicate_scan_state', array( 'status' => 'running' ), 3600 );
		update_option( Settings::OPTION_NAME, array_merge( Settings::defaults(), array( 'drop_on_uninstall' => true ) ) );

		Plugin::uninstall();

		$this->assertFalse( get_transient( 'snopix_duplicate_scan_state' ), 'uninstall() must delete the duplicate scan state transient.' );
	}
}
