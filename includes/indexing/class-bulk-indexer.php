<?php
/**
 * Bulk indexing orchestrator for background processing.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Indexing;

use PixelScout\Repository\Index_Repository;
use PixelScout\Infrastructure\Action_Scheduler;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages bulk indexing via chained cron batches.
 *
 * Instead of scheduling all batches upfront with fixed delays, only the first
 * batch is scheduled immediately. Each batch schedules the next one after it
 * finishes, so the gap is always BATCH_DELAY seconds from actual completion.
 */
class Bulk_Indexer {
	/**
	 * Batch size for processing.
	 */
	private const BATCH_SIZE = 50;

	/**
	 * Seconds between consecutive chained batches.
	 */
	private const BATCH_DELAY = 60;

	/**
	 * Cron hook name.
	 */
	public const CRON_HOOK = 'ps_bulk_index_batch';

	/**
	 * Transient key for the pending attachment ID queue.
	 */
	public const PENDING_KEY = 'ps_bulk_pending';

	/**
	 * Constructor.
	 *
	 * @param Index_Repository $repository Index repository.
	 * @param Image_Indexer    $indexer    Single image indexer.
	 * @param Index_Progress   $progress   Progress tracker.
	 * @param Action_Scheduler $scheduler  Action scheduler.
	 */
	public function __construct(
		private Index_Repository $repository,
		private Image_Indexer $indexer,
		private Index_Progress $progress,
		private Action_Scheduler $scheduler
	) {}

	/**
	 * Schedule bulk indexing for all unindexed attachments.
	 *
	 * @return bool True if scheduled, false if a job is already running.
	 */
	public function schedule(): bool {
		if ( $this->is_running() ) {
			return false;
		}
		$ids = $this->repository->get_unindexed_ids();
		$this->schedule_ids( $ids );
		return true;
	}

	/**
	 * Wipe the index and schedule every attachment for fresh indexing.
	 *
	 * @return bool True if scheduled, false if a job is already running.
	 */
	public function schedule_all(): bool {
		if ( $this->is_running() ) {
			return false;
		}
		$this->repository->clear_all();
		$ids = $this->repository->get_unindexed_ids();
		$this->schedule_ids( $ids );
		return true;
	}

	/**
	 * Cancel any in-flight bulk job: clear the cron chain, drop the pending
	 * queue, and reset the progress envelope to idle.
	 *
	 * @return void
	 */
	public function abort(): void {
		$this->scheduler->cancel_all( self::CRON_HOOK );
		delete_transient( self::PENDING_KEY );
		$this->progress->reset();
	}

	/**
	 * Whether the progress envelope reports an in-flight bulk job.
	 *
	 * @return bool
	 */
	public function is_running(): bool {
		return 'running' === $this->progress->get()['status'];
	}

	/**
	 * Schedule the first batch immediately, storing the rest in a transient queue.
	 *
	 * @param array<int> $ids Attachment IDs to schedule.
	 *
	 * @return void
	 */
	private function schedule_ids( array $ids ): void {
		if ( empty( $ids ) ) {
			return;
		}

		$this->scheduler->cancel_all( self::CRON_HOOK );
		$this->progress->reset();
		$this->progress->set( 0, count( $ids ) );

		set_transient( self::PENDING_KEY, array_values( $ids ), DAY_IN_SECONDS );
		$this->scheduler->schedule( self::CRON_HOOK, array(), 0 );
	}

	/**
	 * Process the next batch from the pending queue and chain the following one.
	 *
	 * Called by WP-Cron with no args. Reads from the pending transient,
	 * processes one batch, then schedules the next batch after BATCH_DELAY seconds.
	 *
	 * @return void
	 */
	public function process_batch(): void {
		$pending = get_transient( self::PENDING_KEY );

		if ( ! is_array( $pending ) || empty( $pending ) ) {
			return;
		}

		$batch     = array_slice( $pending, 0, self::BATCH_SIZE );
		$remaining = array_slice( $pending, self::BATCH_SIZE );

		if ( ! empty( $remaining ) ) {
			set_transient( self::PENDING_KEY, array_values( $remaining ), DAY_IN_SECONDS );
		} else {
			delete_transient( self::PENDING_KEY );
		}

		// Prime the post + postmeta object cache for the batch so per-image
		// metadata reads inside Image_Indexer hit the cache, not SQL.
		$batch_ids = array_map( 'absint', $batch );
		if ( ! empty( $batch_ids ) ) {
			_prime_post_caches( $batch_ids, true, true );
		}

		$succeeded = 0;
		foreach ( $batch_ids as $id ) {
			if ( $this->indexer->index_single( $id ) ) {
				++$succeeded;
			}
		}

		// Increment once per batch — N transient round-trips collapse into one.
		$this->progress->increment_by( count( $batch_ids ) );

		// If every image in the batch failed AND there are still images
		// remaining, halt the chain so we don't burn through the queue on a
		// fundamentally broken environment (missing GD ext, all-corrupt media).
		if ( ! empty( $remaining ) && 0 === $succeeded ) {
			delete_transient( self::PENDING_KEY );
			$this->progress->mark_stalled();
			return;
		}

		if ( ! empty( $remaining ) ) {
			$this->scheduler->schedule( self::CRON_HOOK, array(), self::BATCH_DELAY );
		}
	}
}
