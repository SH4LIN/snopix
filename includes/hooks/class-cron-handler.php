<?php
/**
 * WordPress scheduled event handler for bulk indexing.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Hooks;

use PixelScout\Indexing\Bulk_Indexer;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles cron events for batch processing.
 */
class Cron_Handler {
	/**
	 * @param Bulk_Indexer $bulk_indexer Bulk indexer.
	 */
	public function __construct(
		private Bulk_Indexer $bulk_indexer
	) {}

	/**
	 * Register cron hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'ps_bulk_index_batch', array( $this, 'process_batch' ) );
	}

	/**
	 * Process batch of attachment IDs.
	 *
	 * @param array<int> $ids Attachment IDs to process.
	 *
	 * @return void
	 */
	public function process_batch( array $ids ): void {
		$this->bulk_indexer->process_batch( $ids );
	}
}
