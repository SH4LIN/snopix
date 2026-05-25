<?php
/**
 * WordPress media attachment hooks for automatic indexing.
 *
 * @package Snopix
 */

namespace Snopix\Hooks;

use Snopix\Indexing\Image_Indexer;
use Snopix\Search\Query_Image;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks into WordPress media lifecycle for automatic indexing.
 */
class Media_Hooks {
	/**
	 * Constructor.
	 *
	 * @param Image_Indexer $indexer Single image indexer.
	 */
	public function __construct(
		private Image_Indexer $indexer
	) {}

	/**
	 * Register media hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_attachment', array( $this, 'on_upload' ) );
		add_action( 'delete_attachment', array( $this, 'on_delete' ) );
	}

	/**
	 * Handle newly uploaded attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public function on_upload( int $attachment_id ): void {
		// Skip probe images uploaded by the /search endpoint — they are
		// throwaway and would create an orphan index row if cleanup() fails.
		if ( get_post_meta( $attachment_id, Query_Image::PROBE_META_KEY, true ) ) {
			return;
		}
		$this->indexer->index_single( $attachment_id );
	}

	/**
	 * Handle deleted attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public function on_delete( int $attachment_id ): void {
		$this->indexer->on_delete( $attachment_id );
	}
}
