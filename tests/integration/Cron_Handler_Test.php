<?php
/**
 * Integration tests for Snopix\Hooks\Cron_Handler + Snopix\Indexing\Bulk_Indexer.
 *
 * Verifies that:
 *   - register() wires up the batch action hook.
 *   - Bulk_Indexer::schedule() enqueues a WP-Cron event for the batch hook.
 *   - Firing the batch hook callback advances indexing for real attachments.
 *
 * Row DML is auto-rolled-back by WP_UnitTestCase; WP-Cron events are cleared
 * in tear_down() because they live outside the transaction.
 *
 * @package Snopix
 */

use Snopix\Hooks\Cron_Handler;
use Snopix\Indexing\Bulk_Indexer;
use Snopix\Indexing\Image_Indexer;
use Snopix\Indexing\Index_Progress;
use Snopix\Indexing\Mime_Validator;
use Snopix\Infrastructure\Action_Scheduler;
use Snopix\Repository\Index_Repository;
use Snopix\Search\Fingerprint_Factory;
use Snopix\Imaging\GD_Loader;
use Snopix\Imaging\PHash_Processor;
use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Edge_Processor;

/**
 * @covers \Snopix\Hooks\Cron_Handler
 * @covers \Snopix\Indexing\Bulk_Indexer
 */
final class Cron_Handler_Test extends Snopix_Integration_TestCase {

	private Cron_Handler     $handler;
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

		$this->repo      = new Index_Repository( $wpdb );
		$this->progress  = new Index_Progress();
		$this->scheduler = new Action_Scheduler();

		// Reset progress transient and any leftover cron event so each test
		// starts from a known-idle state (transients live outside the DB
		// transaction that WP_UnitTestCase rolls back).
		$this->progress->reset();
		wp_clear_scheduled_hook( Bulk_Indexer::CRON_HOOK );

		$factory = new Fingerprint_Factory(
			new GD_Loader(),
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);

		$indexer = new Image_Indexer(
			new Mime_Validator(),
			$factory,
			$this->repo
		);

		$this->bulk_indexer = new Bulk_Indexer(
			$this->repo,
			$indexer,
			$this->progress,
			$this->scheduler
		);

		$this->handler = new Cron_Handler( $this->bulk_indexer );
	}

	public function tear_down(): void {
		// WP-Cron events live outside the DB transaction — clear them explicitly.
		wp_clear_scheduled_hook( Bulk_Indexer::CRON_HOOK );
		delete_transient( Bulk_Indexer::PENDING_KEY );
		$this->progress->reset();

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// register() wires the action hook
	// -------------------------------------------------------------------------

	/**
	 * After register() the batch action is hooked to process_batch().
	 */
	public function test_register_adds_action_hook(): void {
		$this->handler->register();

		$this->assertGreaterThan(
			0,
			has_action( Bulk_Indexer::CRON_HOOK, array( $this->handler, 'process_batch' ) ),
			'register() must add an action for ' . Bulk_Indexer::CRON_HOOK
		);
	}

	// -------------------------------------------------------------------------
	// Bulk_Indexer::schedule() enqueues a WP-Cron event
	// -------------------------------------------------------------------------

	/**
	 * schedule() with at least one unindexed attachment creates a pending cron event.
	 */
	public function test_schedule_creates_cron_event_when_attachments_exist(): void {
		$this->attach_fixture( 1 );

		$scheduled = $this->bulk_indexer->schedule();

		$this->assertTrue( $scheduled, 'schedule() must return true when there are unindexed attachments' );
		$this->assertNotFalse(
			wp_next_scheduled( Bulk_Indexer::CRON_HOOK ),
			'A WP-Cron event must be pending for ' . Bulk_Indexer::CRON_HOOK . ' after schedule()'
		);
	}

	/**
	 * schedule() with no unindexed attachments does not leave a cron event pending.
	 */
	public function test_schedule_no_event_when_queue_empty(): void {
		// No attachments → queue is empty → progress is reset to idle → no cron.
		$this->bulk_indexer->schedule();

		$this->assertFalse(
			wp_next_scheduled( Bulk_Indexer::CRON_HOOK ),
			'No cron event must be scheduled when there are no unindexed attachments'
		);
	}

	/**
	 * schedule() returns false when a job is already running (no double-scheduling).
	 */
	public function test_schedule_returns_false_when_already_running(): void {
		$this->attach_fixture( 2 );

		// Prime the progress envelope to RUNNING exactly as reserve_running_slot()
		// does, so is_running() / Job_Status::is_active() sees an active job.
		$this->progress->set( 0, 1 );

		$second = $this->bulk_indexer->schedule();

		$this->assertFalse( $second, 'schedule() must return false when a job is already in flight' );
	}

	// -------------------------------------------------------------------------
	// process_batch() / Cron_Handler::process_batch() advances indexing
	// -------------------------------------------------------------------------

	/**
	 * Firing the batch hook callback indexes the queued attachments.
	 *
	 * Manually loads the pending transient and calls the handler so we do not
	 * depend on WP-Cron actually firing — the unit under test is the callback
	 * logic, not the cron scheduler.
	 */
	public function test_process_batch_indexes_attachments(): void {
		$id1 = $this->attach_fixture( 3 );
		$id2 = $this->attach_fixture( 4 );

		// Load IDs into the transient queue and prime the progress counter.
		set_transient( Bulk_Indexer::PENDING_KEY, array( $id1, $id2 ), DAY_IN_SECONDS );
		$this->progress->set( 0, 2 );

		$this->handler->register();
		do_action( Bulk_Indexer::CRON_HOOK );

		$indexed = $this->repo->get_all_indexed();
		$ids     = array_column( $indexed, 'attachment_id' );

		$this->assertContains(
			(string) $id1,
			$ids,
			'Attachment #' . $id1 . ' must appear in the index after process_batch()'
		);
		$this->assertContains(
			(string) $id2,
			$ids,
			'Attachment #' . $id2 . ' must appear in the index after process_batch()'
		);
	}

	/**
	 * After a batch that exhausts the queue the pending transient is removed.
	 */
	public function test_process_batch_clears_pending_transient_when_queue_exhausted(): void {
		$id = $this->attach_fixture( 5 );

		set_transient( Bulk_Indexer::PENDING_KEY, array( $id ), DAY_IN_SECONDS );
		$this->progress->set( 0, 1 );

		$this->handler->register();
		do_action( Bulk_Indexer::CRON_HOOK );

		$this->assertFalse(
			get_transient( Bulk_Indexer::PENDING_KEY ),
			'Pending transient must be deleted once the queue is exhausted'
		);
	}

	/**
	 * After a fully-consumed batch the progress status transitions to done.
	 */
	public function test_process_batch_marks_progress_done_when_all_indexed(): void {
		$id = $this->attach_fixture( 6 );

		set_transient( Bulk_Indexer::PENDING_KEY, array( $id ), DAY_IN_SECONDS );
		$this->progress->set( 0, 1 );

		$this->handler->register();
		do_action( Bulk_Indexer::CRON_HOOK );

		$state = $this->progress->get();
		$this->assertSame(
			'done',
			$state['status'],
			'Progress status must be "done" after the last batch succeeds'
		);
	}

	/**
	 * process_batch() is a no-op when the pending transient is absent.
	 */
	public function test_process_batch_no_op_without_pending_transient(): void {
		// Ensure no transient exists.
		delete_transient( Bulk_Indexer::PENDING_KEY );
		$this->progress->reset();

		$this->handler->register();
		do_action( Bulk_Indexer::CRON_HOOK );

		$this->assertCount(
			0,
			$this->repo->get_all_indexed(),
			'No rows must be indexed when the pending transient is absent'
		);
	}
}
