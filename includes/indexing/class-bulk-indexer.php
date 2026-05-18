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
 * Manages bulk indexing via scheduled cron batches.
 */
class Bulk_Indexer {
	/**
	 * Batch size for processing.
	 */
	private const BATCH_SIZE = 50;

	/**
	 * Cron hook name.
	 */
	public const CRON_HOOK = 'ps_bulk_index_batch';

	/**
	 * Constructor.
	 *
	 * @param Index_Repository $repository Index repository.
	 * @param Image_Indexer    $indexer  Single image indexer.
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
	 * Cancels any in-flight batches before scheduling fresh ones so a
	 * re-trigger always starts from a clean state.
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
	 * Schedule batches for the given attachment IDs.
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

		$chunks = array_chunk( $ids, self::BATCH_SIZE );

		foreach ( $chunks as $i => $chunk ) {
			$this->scheduler->schedule( self::CRON_HOOK, array( $chunk ), $i * 60 );
		}
	}

	/**
	 * Process a batch of attachment IDs.
	 *
	 * @param array<int> $ids Attachment IDs to process.
	 *
	 * @return void
	 */
	public function process_batch( array $ids ): void {
		foreach ( $ids as $id ) {
			$this->indexer->index_single( $id );
			$this->progress->increment();
		}
	}
}
