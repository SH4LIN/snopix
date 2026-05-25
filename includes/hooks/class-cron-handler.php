<?php
/**
 * WordPress scheduled event handler for bulk indexing.
 *
 * @package Snopix
 */

namespace Snopix\Hooks;

use Snopix\Indexing\Bulk_Indexer;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles cron events for batch processing.
 */
class Cron_Handler {
	/**
	 * Constructor.
	 *
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
		add_action( 'snopix_bulk_index_batch', array( $this, 'process_batch' ), 10, 0 );
	}

	/**
	 * Process next batch from the pending transient queue.
	 *
	 * @return void
	 */
	public function process_batch(): void {
		$this->bulk_indexer->process_batch();
	}
}
