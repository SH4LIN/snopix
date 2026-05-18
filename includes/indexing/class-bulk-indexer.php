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
	 * @return void
	 */
	public function schedule(): void {
		$ids = $this->repository->get_unindexed_ids();
		$this->schedule_ids( $ids );
	}

	/**
	 * Wipe the index and schedule every attachment for fresh indexing.
	 *
	 * @return void
	 */
	public function schedule_all(): void {
		$this->repository->clear_all();
		$ids = $this->repository->get_unindexed_ids();
		$this->schedule_ids( $ids );
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

		foreach ( $batch as $id ) {
			$this->indexer->index_single( $id );
			$this->progress->increment();
		}

		if ( ! empty( $remaining ) ) {
			$this->scheduler->schedule( self::CRON_HOOK, array(), self::BATCH_DELAY );
		}
	}
}
