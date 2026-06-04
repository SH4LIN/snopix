<?php
/**
 * Integration tests for Snopix\Duplicates\Duplicate_Scanner.
 *
 * Boots a real WordPress + DB environment (transactions rolled back per test).
 * Attachments are registered via the base-class helpers; the full indexing
 * pipeline (GD_Loader → processors → Image_Indexer → Index_Repository) runs
 * against real fixture images so that file_hash and phash values are genuine.
 *
 * @package Snopix
 */

use Snopix\Duplicates\Duplicate_Finder;
use Snopix\Duplicates\Duplicate_Progress;
use Snopix\Duplicates\Duplicate_Scanner;
use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Edge_Processor;
use Snopix\Imaging\GD_Loader;
use Snopix\Imaging\PHash_Processor;
use Snopix\Imaging\Similarity;
use Snopix\Indexing\Image_Indexer;
use Snopix\Indexing\Mime_Validator;
use Snopix\Infrastructure\Action_Scheduler;
use Snopix\Infrastructure\Job_Status;
use Snopix\Repository\Index_Repository;
use Snopix\Search\Fingerprint_Factory;

/**
 * @covers \Snopix\Duplicates\Duplicate_Scanner
 */
final class Duplicate_Scanner_Test extends Snopix_Integration_TestCase {

	private Duplicate_Scanner $scanner;
	private Duplicate_Progress $progress;
	private Index_Repository $repo;
	private Image_Indexer $indexer;

	public function set_up(): void {
		parent::set_up();

		global $wpdb;

		$this->repo = new Index_Repository( $wpdb );

		$factory = new Fingerprint_Factory(
			new GD_Loader(),
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);

		$this->indexer = new Image_Indexer(
			new Mime_Validator(),
			$factory,
			$this->repo
		);

		$this->progress = new Duplicate_Progress();
		$finder         = new Duplicate_Finder( new Similarity() );
		$scheduler      = new Action_Scheduler();

		$this->scanner = new Duplicate_Scanner(
			$this->repo,
			$finder,
			$this->progress,
			$scheduler
		);
	}

	public function tear_down(): void {
		// Clear all scanner-owned state so nothing leaks between tests.
		delete_option( 'snopix_duplicate_results' );
		delete_option( 'snopix_duplicate_last_scanned' );
		delete_transient( 'snopix_duplicate_scan_state' );
		wp_clear_scheduled_hook( Duplicate_Scanner::CRON_HOOK );
		wp_clear_scheduled_hook( Duplicate_Scanner::DAILY_HOOK );

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// CRON_HOOK and DAILY_HOOK constants.
	// -------------------------------------------------------------------------

	/**
	 * CRON_HOOK must be the well-known string the rest of the plugin registers
	 * cron callbacks against.
	 */
	public function test_cron_hook_constant_has_expected_value(): void {
		$this->assertSame( 'snopix_duplicate_scan', Duplicate_Scanner::CRON_HOOK );
	}

	/**
	 * DAILY_HOOK must be the well-known string used for the daily schedule.
	 */
	public function test_daily_hook_constant_has_expected_value(): void {
		$this->assertSame( 'snopix_duplicate_daily', Duplicate_Scanner::DAILY_HOOK );
	}

	// -------------------------------------------------------------------------
	// schedule().
	// -------------------------------------------------------------------------

	/**
	 * schedule() resets progress to running (0 of 1) and queues a WP-Cron
	 * event for CRON_HOOK with no args.
	 */
	public function test_schedule_sets_progress_running_and_queues_cron(): void {
		$this->scanner->schedule();

		$state = $this->progress->get();
		$this->assertSame( Job_Status::RUNNING, $state['status'] );
		$this->assertSame( 0, $state['done'] );

		// A cron event must be pending for CRON_HOOK.
		$this->assertNotFalse(
			wp_next_scheduled( Duplicate_Scanner::CRON_HOOK ),
			'schedule() must queue a WP-Cron event for CRON_HOOK.'
		);
	}

	/**
	 * Calling schedule() twice must not leave duplicate cron events - each
	 * call cancels the existing chain before scheduling a new one.
	 */
	public function test_schedule_cancels_existing_event_before_rescheduling(): void {
		$this->scanner->schedule();
		$time_first = wp_next_scheduled( Duplicate_Scanner::CRON_HOOK );

		// Allow one second so wp_schedule_single_event produces a distinct
		// timestamp when called again.
		$this->scanner->schedule();
		$time_second = wp_next_scheduled( Duplicate_Scanner::CRON_HOOK );

		// Both timestamps come back as valid integers; the second schedule
		// replaced the first rather than adding alongside it.
		$this->assertNotFalse( $time_second, 'A cron event must exist after the second schedule() call.' );

		// There must be exactly one event for the hook.
		$events = _get_cron_array();
		$count  = 0;
		if ( is_array( $events ) ) {
			foreach ( $events as $timestamp_events ) {
				if ( isset( $timestamp_events[ Duplicate_Scanner::CRON_HOOK ] ) ) {
					$count += count( $timestamp_events[ Duplicate_Scanner::CRON_HOOK ] );
				}
			}
		}
		$this->assertSame( 1, $count, 'Only one pending event must exist for CRON_HOOK after schedule().' );
	}

	// -------------------------------------------------------------------------
	// abort().
	// -------------------------------------------------------------------------

	/**
	 * abort() cancels any scheduled cron event and resets progress to idle.
	 */
	public function test_abort_clears_cron_and_resets_progress(): void {
		$this->scanner->schedule();

		$this->assertNotFalse(
			wp_next_scheduled( Duplicate_Scanner::CRON_HOOK ),
			'Pre-condition: cron event must exist before abort().'
		);

		$this->scanner->abort();

		$this->assertFalse(
			wp_next_scheduled( Duplicate_Scanner::CRON_HOOK ),
			'abort() must cancel the cron event.'
		);

		$state = $this->progress->get();
		$this->assertSame( Job_Status::IDLE, $state['status'], 'abort() must reset progress to idle.' );
	}

	// -------------------------------------------------------------------------
	// run() - empty index.
	// -------------------------------------------------------------------------

	/**
	 * With an empty index, run() finalises immediately (fewer than 2 rows),
	 * persists an empty results array, and marks progress as done.
	 */
	public function test_run_with_empty_index_finalises_with_no_groups(): void {
		delete_option( 'snopix_duplicate_results' );

		// Prime progress so mark_done() has a total to work against.
		$this->progress->set( 0, 1 );

		$this->scanner->run();

		// Results option must be an empty JSON array.
		$raw    = get_option( 'snopix_duplicate_results', null );
		$groups = json_decode( (string) $raw, true );
		$this->assertIsArray( $groups );
		$this->assertSame( array(), $groups );

		// Progress must be done.
		$state = $this->progress->get();
		$this->assertSame( Job_Status::DONE, $state['status'] );
	}

	/**
	 * With a single indexed attachment, run() finalises with no groups -
	 * a pair is needed to form any group.
	 */
	public function test_run_with_single_indexed_image_produces_no_groups(): void {
		$id = $this->attach_fixture( 7 );
		$this->indexer->index_single( $id );

		$this->progress->set( 0, 1 );
		$this->scanner->run();

		$groups = $this->scanner->get_results();
		$this->assertSame( array(), $groups, 'One image cannot form a duplicate group.' );

		$state = $this->progress->get();
		$this->assertSame( Job_Status::DONE, $state['status'] );
	}

	// -------------------------------------------------------------------------
	// run() - exact duplicate pair detected and persisted.
	// -------------------------------------------------------------------------

	/**
	 * Two attachments backed by identical bytes must produce exactly one
	 * exact-duplicate group after run() completes, and the results option
	 * must contain that group.
	 */
	public function test_run_detects_exact_duplicate_pair_and_persists_results(): void {
		[ $id_a, $id_b ] = $this->attach_fixture_twice( 1 );

		$this->assertTrue( $this->indexer->index_single( $id_a ) );
		$this->assertTrue( $this->indexer->index_single( $id_b ) );

		$this->progress->set( 0, 2 );
		$this->scanner->run();

		$groups = $this->scanner->get_results();
		$this->assertNotEmpty( $groups, 'At least one duplicate group must be found.' );

		// Find the group containing both IDs.
		$exact_group = null;
		foreach ( $groups as $group ) {
			if ( 'exact' === $group['match_type']
				&& in_array( $id_a, $group['ids'], true )
				&& in_array( $id_b, $group['ids'], true )
			) {
				$exact_group = $group;
				break;
			}
		}

		$this->assertNotNull( $exact_group, 'An exact-duplicate group containing both IDs must be persisted.' );
		$this->assertSame( 'exact', $exact_group['match_type'] );
		$this->assertContains( $id_a, $exact_group['ids'] );
		$this->assertContains( $id_b, $exact_group['ids'] );
	}

	/**
	 * After run() completes with an exact-duplicate pair, progress must be
	 * done and the last-scanned option must be a non-empty timestamp.
	 */
	public function test_run_marks_progress_done_and_writes_last_scanned(): void {
		[ $id_a, $id_b ] = $this->attach_fixture_twice( 2 );
		$this->indexer->index_single( $id_a );
		$this->indexer->index_single( $id_b );

		// Ensure last_scanned starts empty.
		delete_option( 'snopix_duplicate_last_scanned' );

		$this->progress->set( 0, 2 );
		$this->scanner->run();

		// Progress must be done.
		$state = $this->progress->get();
		$this->assertSame( Job_Status::DONE, $state['status'] );

		// last_scanned must be a non-empty MySQL datetime string.
		$last_scanned = $this->scanner->get_last_scanned();
		$this->assertNotEmpty( $last_scanned, 'get_last_scanned() must return a non-empty timestamp after run().' );
	}

	// -------------------------------------------------------------------------
	// get_results() round-trip.
	// -------------------------------------------------------------------------

	/**
	 * get_results() returns an empty array before any scan has run.
	 */
	public function test_get_results_returns_empty_array_before_scan(): void {
		delete_option( 'snopix_duplicate_results' );
		$this->assertSame( array(), $this->scanner->get_results() );
	}

	/**
	 * get_results() returns a correctly structured array after a scan that
	 * found an exact-duplicate pair.
	 */
	public function test_get_results_returns_structured_groups_after_scan(): void {
		[ $id_a, $id_b ] = $this->attach_fixture_twice( 3 );
		$this->indexer->index_single( $id_a );
		$this->indexer->index_single( $id_b );

		$this->progress->set( 0, 2 );
		$this->scanner->run();

		$groups = $this->scanner->get_results();
		$this->assertIsArray( $groups );
		$this->assertNotEmpty( $groups );

		foreach ( $groups as $group ) {
			$this->assertArrayHasKey( 'match_type', $group );
			$this->assertArrayHasKey( 'ids', $group );
			$this->assertContains( $group['match_type'], array( 'exact', 'perceptual' ) );
			$this->assertIsArray( $group['ids'] );
			$this->assertGreaterThanOrEqual( 2, count( $group['ids'] ) );
		}
	}

	// -------------------------------------------------------------------------
	// get_last_scanned().
	// -------------------------------------------------------------------------

	/**
	 * get_last_scanned() returns an empty string when no scan has completed.
	 */
	public function test_get_last_scanned_returns_empty_string_before_scan(): void {
		delete_option( 'snopix_duplicate_last_scanned' );
		$this->assertSame( '', $this->scanner->get_last_scanned() );
	}

	// -------------------------------------------------------------------------
	// Unrelated images - no groups produced.
	// -------------------------------------------------------------------------

	/**
	 * Three distinct (unrelated) fixtures must produce zero duplicate groups
	 * after a complete scan.
	 */
	public function test_run_produces_no_groups_for_distinct_fixtures(): void {
		foreach ( array( 2, 4, 6 ) as $fixture_id ) {
			$aid = $this->attach_fixture( $fixture_id );
			$this->indexer->index_single( $aid );
		}

		$this->progress->set( 0, 3 );
		$this->scanner->run();

		$groups = $this->scanner->get_results();
		$this->assertSame(
			array(),
			$groups,
			'Three distinct fixtures must yield no duplicate groups.'
		);
	}

	// -------------------------------------------------------------------------
	// State-transient cleared on finalise.
	// -------------------------------------------------------------------------

	/**
	 * After run() completes (finalise path), the cross-batch state transient
	 * must be absent - it should not persist beyond the completed scan.
	 */
	public function test_state_transient_is_deleted_after_run_completes(): void {
		[ $id_a, $id_b ] = $this->attach_fixture_twice( 4 );
		$this->indexer->index_single( $id_a );
		$this->indexer->index_single( $id_b );

		$this->progress->set( 0, 2 );
		$this->scanner->run();

		$this->assertFalse(
			get_transient( 'snopix_duplicate_scan_state' ),
			'The cross-batch state transient must be deleted once the scan finalises.'
		);
	}

	// -------------------------------------------------------------------------
	// Results option updated on successive scans.
	// -------------------------------------------------------------------------

	/**
	 * Running a second scan after index content changes overwrites the
	 * previous results option with the latest result set.
	 */
	public function test_second_run_overwrites_previous_results(): void {
		// First scan: two distinct images → no groups.
		$id_one  = $this->attach_fixture( 1 );
		$id_five = $this->attach_fixture( 5 );
		$this->indexer->index_single( $id_one );
		$this->indexer->index_single( $id_five );

		$this->progress->set( 0, 2 );
		$this->scanner->run();

		$after_first = $this->scanner->get_results();
		$this->assertSame( array(), $after_first, 'Pre-condition: distinct fixtures yield no groups.' );

		// Second scan: add an exact-duplicate pair on top of the two existing rows.
		[ $id_a, $id_b ] = $this->attach_fixture_twice( 8 );
		$this->indexer->index_single( $id_a );
		$this->indexer->index_single( $id_b );

		// Clear cross-batch state so the second run starts fresh.
		delete_transient( 'snopix_duplicate_scan_state' );
		$this->progress->set( 0, 4 );
		$this->scanner->run();

		$after_second = $this->scanner->get_results();
		$this->assertNotEmpty(
			$after_second,
			'Second scan must overwrite results and detect the newly added duplicate pair.'
		);
	}
}
