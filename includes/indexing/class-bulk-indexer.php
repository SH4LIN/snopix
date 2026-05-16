<?php
/**
 * Bulk indexing orchestrator for background processing.
 *
 * @package Pixel_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages bulk indexing via scheduled cron batches.
 */
class Pixel_Scout_Bulk_Indexer {
	/**
	 * Batch size for processing.
	 */
	private const BATCH_SIZE = 50;

	/**
	 * Cron hook name.
	 */
	private const CRON_HOOK = 'ps_bulk_index_batch';

	/**
	 * @param Pixel_Scout_Index_Repository $repository Index repository.
	 * @param Pixel_Scout_Image_Indexer $indexer Single image indexer.
	 * @param Pixel_Scout_Index_Progress $progress Progress tracker.
	 */
	public function __construct(
		private Pixel_Scout_Index_Repository $repository,
		private Pixel_Scout_Image_Indexer $indexer,
		private Pixel_Scout_Index_Progress $progress
	) {}

	/**
	 * Schedule bulk indexing for all unindexed attachments.
	 *
	 * @return void
	 */
	public function schedule(): void {
		$ids = $this->repository->get_unindexed_ids();

		if ( empty( $ids ) ) {
			return;
		}

		$this->progress->reset();
		$this->progress->set( 0, count( $ids ) );

		$chunks = array_chunk( $ids, self::BATCH_SIZE );

		foreach ( $chunks as $i => $chunk ) {
			wp_schedule_single_event( time() + ( $i * 60 ), self::CRON_HOOK, [ $chunk ] );
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
