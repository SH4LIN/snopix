<?php
/**
 * Integration tests for plugin version upgrades.
 *
 * Simulates a site that ran an older plugin version (stale db-version option,
 * old-format index rows) and verifies that loading the new version runs every
 * upgrade step without fatals: schema migration, db-version bump, index wipe,
 * and bulk reindex scheduling.
 *
 * @package Snopix
 */

use Snopix\Indexing\Bulk_Indexer;
use Snopix\Indexing\Index_Progress;
use Snopix\Infrastructure\Job_Status;
use Snopix\Infrastructure\Plugin;
use Snopix\Repository\Index_Repository;
use Snopix\Repository\Schema;

/**
 * @covers \Snopix\Infrastructure\Upgrader
 */
final class Upgrader_Test extends Snopix_Integration_TestCase {

	private Index_Repository $repo;
	private Index_Progress   $progress;

	public function set_up(): void {
		parent::set_up();

		// Schema::install() runs dbDelta CREATE TABLE; WP_UnitTestCase rewrites
		// CREATE/DROP into TEMPORARY ops which would target a separate namespace
		// from the real bootstrap-created table. Remove those filters so the
		// upgrade acts on the actual plugin table; tear_down() re-installs it.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		// Prevent the live Media_Hooks from auto-indexing attachments the moment
		// they are created, so fixtures stay unindexed until the upgrade
		// schedules them itself.
		remove_all_actions( 'add_attachment' );
		remove_all_actions( 'delete_attachment' );

		global $wpdb;
		$this->repo     = new Index_Repository( $wpdb );
		$this->progress = new Index_Progress();
	}

	public function tear_down(): void {
		wp_clear_scheduled_hook( Bulk_Indexer::CRON_HOOK );
		delete_transient( Bulk_Indexer::PENDING_KEY );
		$this->progress->reset();
		( new Schema() )->install();
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Put the site into "old plugin version" state: stale db-version option
	 * plus an old-format index row for the given attachment.
	 *
	 * @param int $attachment_id Attachment to seed a stale index row for.
	 */
	private function simulate_old_version( int $attachment_id ): void {
		update_option( SNOPIX_OPTION_DB_VERSION, '0.1.4' );
		$this->repo->upsert(
			$attachment_id,
			array(
				'phash'        => str_repeat( 'a', 16 ),
				'color_vector' => array( 0.1, 0.2, 0.3 ),
				'edge_vector'  => array( 0.4, 0.5 ),
			)
		);
	}

	/**
	 * Run the upgrade exactly as production does on plugins_loaded.
	 */
	private function run_upgrade(): void {
		Plugin::instance()->maybe_upgrade_db();
	}

	// -------------------------------------------------------------------------
	// plugins_loaded upgrade path (plugin files replaced while active)
	// -------------------------------------------------------------------------

	public function test_upgrade_from_older_version_completes_without_errors(): void {
		$attachment_id = $this->attach_fixture( 1 );
		$this->simulate_old_version( $attachment_id );

		// Must run the full real path (schema, repository, indexer wiring)
		// without throwing or fataling.
		$this->run_upgrade();

		$this->assertSame( SNOPIX_DB_VERSION, get_option( SNOPIX_OPTION_DB_VERSION ), 'Upgrade must bump the stored db version.' );
	}

	public function test_upgrade_from_older_version_runs_schema_migration(): void {
		global $wpdb;
		$attachment_id = $this->attach_fixture( 1 );
		$this->simulate_old_version( $attachment_id );

		$this->run_upgrade();

		$table = $wpdb->prefix . 'snopix_index';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ), 'Index table must exist after the schema migration.' );
		$this->assertSame( SNOPIX_DB_VERSION, get_option( SNOPIX_OPTION_DB_VERSION ), 'Schema migration must record the new db version.' );
	}

	public function test_upgrade_from_older_version_wipes_stale_index(): void {
		$attachment_id = $this->attach_fixture( 1 );
		$this->simulate_old_version( $attachment_id );
		$this->assertNotEmpty( $this->repo->get_all_indexed(), 'Precondition: stale index row must exist.' );

		$this->run_upgrade();

		$this->assertSame( array(), $this->repo->get_all_indexed(), 'Old-format fingerprints must be wiped on upgrade.' );
	}

	public function test_upgrade_from_older_version_schedules_full_reindex(): void {
		$attachment_id = $this->attach_fixture( 1 );
		$this->simulate_old_version( $attachment_id );

		$this->run_upgrade();

		$pending = get_transient( Bulk_Indexer::PENDING_KEY );
		$this->assertIsArray( $pending, 'Upgrade must queue attachments for reindexing.' );
		$this->assertContains( $attachment_id, $pending, 'The previously indexed attachment must be re-queued.' );
		$this->assertSame( Job_Status::RUNNING, $this->progress->get()['status'], 'Reindex job must be marked running.' );
		$this->assertNotFalse( wp_next_scheduled( Bulk_Indexer::CRON_HOOK ), 'First reindex batch must be scheduled.' );
	}

	public function test_upgrade_is_noop_when_version_is_current(): void {
		$attachment_id = $this->attach_fixture( 1 );
		update_option( SNOPIX_OPTION_DB_VERSION, SNOPIX_DB_VERSION );
		$this->repo->upsert( $attachment_id, array( 'phash' => str_repeat( 'a', 16 ) ) );

		$this->run_upgrade();

		$this->assertNotEmpty( $this->repo->get_all_indexed(), 'Index must be untouched when the version is already current.' );
		$this->assertFalse( wp_next_scheduled( Bulk_Indexer::CRON_HOOK ), 'No reindex may be scheduled when the version is already current.' );
	}

	public function test_fresh_install_sets_version_without_scheduling_reindex(): void {
		$this->attach_fixture( 1 );
		delete_option( SNOPIX_OPTION_DB_VERSION );

		$this->run_upgrade();

		$this->assertSame( SNOPIX_DB_VERSION, get_option( SNOPIX_OPTION_DB_VERSION ), 'Fresh install must record the db version.' );
		$this->assertFalse( wp_next_scheduled( Bulk_Indexer::CRON_HOOK ), 'Fresh installs have nothing to rebuild.' );
	}

	public function test_upgrade_declines_reindex_when_bulk_job_already_running(): void {
		$attachment_id = $this->attach_fixture( 1 );
		$this->simulate_old_version( $attachment_id );
		// A bulk job is mid-flight; schedule_all() must decline rather than
		// wipe the queue out from under it.
		$this->progress->set( 0, 5 );

		$this->run_upgrade();

		$this->assertSame( SNOPIX_DB_VERSION, get_option( SNOPIX_OPTION_DB_VERSION ), 'Schema migration must still run.' );
		$this->assertNotEmpty( $this->repo->get_all_indexed(), 'Index must not be wiped while a bulk job is running.' );
	}

	// -------------------------------------------------------------------------
	// Activation upgrade path (plugin replaced while deactivated)
	// -------------------------------------------------------------------------

	public function test_activation_after_manual_update_schedules_full_reindex(): void {
		$attachment_id = $this->attach_fixture( 1 );
		$this->simulate_old_version( $attachment_id );

		Plugin::activate();

		$this->assertSame( SNOPIX_DB_VERSION, get_option( SNOPIX_OPTION_DB_VERSION ), 'Activation must record the new db version.' );
		$this->assertSame( array(), $this->repo->get_all_indexed(), 'Old-format fingerprints must be wiped on activation upgrade.' );
		$pending = get_transient( Bulk_Indexer::PENDING_KEY );
		$this->assertIsArray( $pending, 'Activation upgrade must queue attachments for reindexing.' );
		$this->assertContains( $attachment_id, $pending, 'The previously indexed attachment must be re-queued.' );
		$this->assertNotFalse( wp_next_scheduled( Bulk_Indexer::CRON_HOOK ), 'First reindex batch must be scheduled.' );
	}
}
