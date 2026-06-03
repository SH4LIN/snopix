<?php
/**
 * Integration tests for Snopix\Indexing\Bulk_Indexer.
 *
 * @package Snopix
 */

use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Edge_Processor;
use Snopix\Imaging\GD_Loader;
use Snopix\Imaging\PHash_Processor;
use Snopix\Indexing\Bulk_Indexer;
use Snopix\Indexing\Image_Indexer;
use Snopix\Indexing\Index_Progress;
use Snopix\Indexing\Mime_Validator;
use Snopix\Infrastructure\Action_Scheduler;
use Snopix\Infrastructure\Job_Status;
use Snopix\Repository\Index_Repository;
use Snopix\Search\Fingerprint_Factory;

/**
 * @covers \Snopix\Indexing\Bulk_Indexer
 */
final class Bulk_Indexer_Test extends Snopix_Integration_TestCase {

	private Bulk_Indexer     $bulk_indexer;
	private Index_Repository $repo;
	private Index_Progress   $progress;
	private Action_Scheduler $scheduler;

	public function set_up(): void {
		parent::set_up();
		// Prevent the live Media_Hooks from auto-indexing attachments the moment
		// they are created, so fixtures stay unindexed until the code under test
		// indexes them.
		remove_all_actions( 'add_attachment' );
		remove_all_actions( 'delete_attachment' );
		global $wpdb;

		$this->repo     = new Index_Repository( $wpdb );
		$this->progress = new Index_Progress();
		$this->scheduler = new Action_Scheduler();

		$indexer = new Image_Indexer(
			new Mime_Validator(),
			new Fingerprint_Factory(
				new GD_Loader(),
				new PHash_Processor(),
				new Color_Processor(),
				new Edge_Processor()
			),
			$this->repo
		);

		$this->bulk_indexer = new Bulk_Indexer(
			$this->repo,
			$indexer,
			$this->progress,
			$this->scheduler
		);
	}

	public function tear_down(): void {
		// Cancel any scheduled cron events and clean up transients.
		$this->scheduler->cancel_all( Bulk_Indexer::CRON_HOOK );
		delete_transient( Bulk_Indexer::PENDING_KEY );
		$this->progress->reset();
		parent::tear_down();
	}

	/**
	 * schedule() returns false when no attachments exist to index.
	 */
	public function test_schedule_with_no_attachments_resets_to_idle(): void {
		$result = $this->bulk_indexer->schedule();

		// schedule() returns true (slot was reserved) but immediately resets
		// because the resolved ID list is empty.
		$this->assertTrue( $result );
		$state = $this->progress->get();
		$this->assertSame( Job_Status::IDLE, $state['status'] );
	}

	/**
	 * schedule() returns false when a job is already running.
	 */
	public function test_schedule_blocks_when_job_is_running(): void {
		// Prime the progress to running state manually.
		$this->progress->set( 0, 5 );
		$this->assertSame( Job_Status::RUNNING, $this->progress->get()['status'] );

		$result = $this->bulk_indexer->schedule();

		$this->assertFalse( $result );
	}

	/**
	 * schedule() populates the pending transient and schedules the cron hook
	 * when attachments are present.
	 */
	public function test_schedule_populates_pending_and_schedules_cron(): void {
		$id1 = $this->attach_fixture( 1 );
		$id2 = $this->attach_fixture( 2 );

		$result = $this->bulk_indexer->schedule();

		$this->assertTrue( $result );

		// Pending transient must contain both attachment IDs.
		$pending = get_transient( Bulk_Indexer::PENDING_KEY );
		$this->assertIsArray( $pending );
		$this->assertCount( 2, $pending );
		$this->assertContains( $id1, $pending );
		$this->assertContains( $id2, $pending );

		// Progress status must be running with the correct total.
		$state = $this->progress->get();
		$this->assertSame( Job_Status::RUNNING, $state['status'] );
		$this->assertSame( 2, $state['total'] );

		// Cron hook must be scheduled (with empty args, as per production contract).
		$this->assertNotFalse( wp_next_scheduled( Bulk_Indexer::CRON_HOOK ) );
	}

	/**
	 * is_running() reflects the current progress status.
	 */
	public function test_is_running_reflects_progress_status(): void {
		$this->assertFalse( $this->bulk_indexer->is_running() );

		$this->progress->set( 0, 3 );
		$this->assertTrue( $this->bulk_indexer->is_running() );

		$this->progress->reset();
		$this->assertFalse( $this->bulk_indexer->is_running() );
	}

	/**
	 * abort() clears the cron chain, pending transient, and resets progress.
	 */
	public function test_abort_clears_state(): void {
		$this->attach_fixture( 3 );
		$this->bulk_indexer->schedule();

		// Verify state was set before abort.
		$this->assertSame( Job_Status::RUNNING, $this->progress->get()['status'] );
		$this->assertNotFalse( get_transient( Bulk_Indexer::PENDING_KEY ) );

		$this->bulk_indexer->abort();

		$this->assertFalse( wp_next_scheduled( Bulk_Indexer::CRON_HOOK ) );
		$this->assertFalse( get_transient( Bulk_Indexer::PENDING_KEY ) );
		$this->assertSame( Job_Status::IDLE, $this->progress->get()['status'] );
		$this->assertFalse( $this->bulk_indexer->is_running() );
	}

	/**
	 * process_batch() indexes the attachments in the batch and advances progress.
	 */
	public function test_process_batch_indexes_attachments_and_advances_progress(): void {
		$id1 = $this->attach_fixture( 1 );
		$id2 = $this->attach_fixture( 2 );

		// Set up state as schedule() would.
		$ids = array( $id1, $id2 );
		set_transient( Bulk_Indexer::PENDING_KEY, $ids, DAY_IN_SECONDS );
		$this->progress->set( 0, count( $ids ) );

		$this->bulk_indexer->process_batch();

		// Both attachments must now appear in the index.
		$indexed = $this->repo->get_all_indexed();
		$indexed_ids = array_column( $indexed, 'attachment_id' );
		$this->assertContains( (string) $id1, $indexed_ids );
		$this->assertContains( (string) $id2, $indexed_ids );

		// Progress must advance — done equals total, so status becomes done.
		$state = $this->progress->get();
		$this->assertSame( 2, $state['done'] );
		$this->assertSame( 2, $state['total'] );
		$this->assertSame( Job_Status::DONE, $state['status'] );
	}

	/**
	 * process_batch() chains the next batch when the queue has remaining items.
	 * NOTE: Bulk_Indexer::BATCH_DELAY is private; we assert only that the next
	 * cron event is scheduled, not its exact timestamp.
	 */
	public function test_process_batch_chains_next_batch_when_remaining(): void {
		$id1 = $this->attach_fixture( 1 );
		$id2 = $this->attach_fixture( 2 );
		$id3 = $this->attach_fixture( 3 );

		// Force batch size to 1 by placing only one ID in the "batch" slice
		// and two in the remaining slice via the transient directly.
		// NOTE: Settings::get_batch_size() is a static read of a WP option;
		// we work around it by stuffing pending such that at the default batch
		// size (≥1) the first batch processes $id1 and $id2/$id3 remain.
		// Instead, prime the full queue and let the default batch size apply.
		$ids = array( $id1, $id2, $id3 );
		set_transient( Bulk_Indexer::PENDING_KEY, $ids, DAY_IN_SECONDS );
		$this->progress->set( 0, count( $ids ) );

		// Override batch size option to 1 so we can guarantee a two-step chain.
		update_option( 'snopix_settings', array( 'batch_size' => 1 ) );

		// Cancel any previous event so the assert is clean.
		$this->scheduler->cancel_all( Bulk_Indexer::CRON_HOOK );

		$this->bulk_indexer->process_batch();

		// Remaining transient must have two IDs left.
		$remaining = get_transient( Bulk_Indexer::PENDING_KEY );
		$this->assertIsArray( $remaining );
		$this->assertCount( 2, $remaining );

		// A follow-up cron event must be scheduled.
		$this->assertNotFalse( wp_next_scheduled( Bulk_Indexer::CRON_HOOK ) );

		// Progress must be running with done=1, total=3.
		$state = $this->progress->get();
		$this->assertSame( Job_Status::RUNNING, $state['status'] );
		$this->assertSame( 1, $state['done'] );

		// Restore default.
		delete_option( 'snopix_settings' );
	}

	/**
	 * schedule_all() clears the index before re-scheduling all attachments.
	 */
	public function test_schedule_all_clears_index_and_reschedules(): void {
		global $wpdb;

		$id1 = $this->attach_fixture( 4 );
		$id2 = $this->attach_fixture( 5 );

		// Pre-seed an index row directly.
		$this->repo->upsert(
			$id1,
			array(
				'phash'        => str_repeat( 'a', 16 ),
				'color_vector' => array( 0.5 ),
				'edge_vector'  => array( 1.0 ),
				'file_hash'    => str_repeat( 'b', 32 ),
			)
		);
		$this->assertCount( 1, $this->repo->get_all_indexed() );

		$result = $this->bulk_indexer->schedule_all();

		$this->assertTrue( $result );

		// Index must have been cleared before re-queuing.
		$this->assertCount( 0, $this->repo->get_all_indexed() );

		// Both IDs must be in the pending queue.
		$pending = get_transient( Bulk_Indexer::PENDING_KEY );
		$this->assertIsArray( $pending );
		$this->assertCount( 2, $pending );
	}

	/**
	 * process_batch() is a no-op when the pending transient is absent.
	 */
	public function test_process_batch_noop_when_pending_empty(): void {
		delete_transient( Bulk_Indexer::PENDING_KEY );
		// No progress set — status is idle.
		$this->bulk_indexer->process_batch();

		$this->assertCount( 0, $this->repo->get_all_indexed() );
		$this->assertSame( Job_Status::IDLE, $this->progress->get()['status'] );
	}

	/**
	 * 700 attachments (10 real + 690 broken) processed across 14 chained batches.
	 *
	 * Verifies:
	 * - schedule() correctly enqueues all 700 IDs.
	 * - Broken images (no physical file → unfingerprintable) do not abort the chain.
	 * - The first batch succeeds (real images first in queue) preventing a stall.
	 * - All 14 batches complete; final status is DONE, not STALLED.
	 * - Repository counts: 10 indexed, 690 failed, 0 pending.
	 * - Every broken attachment row carries error_code = 'unfingerprintable'.
	 */
	public function test_large_bulk_index_with_broken_images_completes_without_abort(): void {
		global $wpdb;

		$total_count  = 700;
		$real_count   = 10;
		$broken_count = $total_count - $real_count;
		$batch_size   = 50;

		// Real attachments first so they land at lower IDs.
		// get_unindexed_ids() orders by p.ID ASC, so the first batch of 50
		// includes all 10 real images → guaranteed successes → no first-batch stall.
		$real_ids = array();
		for ( $i = 1; $i <= $real_count; $i++ ) {
			$real_ids[] = $this->attach_fixture( $i ); // fixtures 001.jpg – 010.jpg
		}

		// Broken attachments: valid JPEG MIME, no physical file.
		// GD_Loader::load() returns false → mark_failed( 'unfingerprintable' ).
		$broken_ids = array();
		for ( $i = 0; $i < $broken_count; $i++ ) {
			$id = wp_insert_post(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image/jpeg',
					'post_title'     => sprintf( 'broken-%04d', $i ),
				)
			);
			$this->assertIsInt( $id );
			$this->assertGreaterThan( 0, $id );
			$broken_ids[] = $id;
		}

		update_option( 'snopix_settings', array( 'batch_size' => $batch_size ) );

		// ── Schedule ──────────────────────────────────────────────────────────
		$scheduled = $this->bulk_indexer->schedule();
		$this->assertTrue( $scheduled, 'schedule() must return true when attachments exist.' );

		$pending = get_transient( Bulk_Indexer::PENDING_KEY );
		$this->assertIsArray( $pending );
		$this->assertCount( $total_count, $pending, 'Pending queue must contain all 700 IDs.' );

		$state = $this->progress->get();
		$this->assertSame( Job_Status::RUNNING, $state['status'] );
		$this->assertSame( $total_count, $state['total'] );

		// ── Drive all batches ─────────────────────────────────────────────────
		// In production each batch is triggered by a separate WP-Cron request, so
		// the object-cache lock (snopix_bulk_lock) naturally expires between ticks.
		// In the test process the cache persists, so we clear it manually before
		// each call to prevent process_batch() from bailing on the lock check.
		$expected_batches = (int) ceil( $total_count / $batch_size ); // 14
		$batches_run      = 0;

		while ( false !== get_transient( Bulk_Indexer::PENDING_KEY ) ) {
			wp_cache_delete( 'snopix_bulk_lock' );
			$this->bulk_indexer->process_batch();
			$batches_run++;

			// Safety valve — prevent an infinite loop if the logic is broken.
			if ( $batches_run > $expected_batches + 2 ) {
				$this->fail( sprintf( 'process_batch() ran %d times without draining the queue.', $batches_run ) );
			}
		}

		$this->assertSame( $expected_batches, $batches_run, 'Exactly 14 batches must be needed to drain 700 images at batch_size=50.' );

		// ── Final state ───────────────────────────────────────────────────────
		$final = $this->progress->get();
		$this->assertSame(
			Job_Status::DONE,
			$final['status'],
			'Bulk job must finish as DONE — broken images must be skipped, not abort the chain.'
		);
		$this->assertSame( $total_count, $final['done'], 'All 700 images must be counted in progress.' );
		$this->assertFalse( get_transient( Bulk_Indexer::PENDING_KEY ), 'Pending queue must be empty after completion.' );

		// ── Repository counts ─────────────────────────────────────────────────
		$counts = $this->repo->get_counts();
		$this->assertSame( $real_count, $counts['indexed'], '10 real images must be indexed successfully.' );
		$this->assertSame( $broken_count, $counts['failed'], '690 broken images must be recorded as failed.' );
		$this->assertSame( 0, $counts['pending'], 'No images must remain pending after the job finishes.' );

		// ── Per-attachment verification ────────────────────────────────────────
		$indexed_rows = $this->repo->get_all_indexed();
		$indexed_ids  = array_map( 'intval', array_column( $indexed_rows, 'attachment_id' ) );
		foreach ( $real_ids as $real_id ) {
			$this->assertContains( $real_id, $indexed_ids, "Real attachment ID {$real_id} must appear in the index." );
		}

		$table = $wpdb->prefix . 'snopix_index';
		foreach ( $broken_ids as $broken_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$error_code = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT error_code FROM $table WHERE attachment_id = %d",
					$broken_id
				)
			);
			$this->assertSame(
				'unfingerprintable',
				$error_code,
				"Broken attachment ID {$broken_id} must carry error_code='unfingerprintable'."
			);
		}

		delete_option( 'snopix_settings' );
	}
}
