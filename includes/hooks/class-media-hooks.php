<?php
/**
 * WordPress media attachment hooks for automatic indexing.
 *
 * @package Pixel_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks into WordPress media lifecycle for automatic indexing.
 */
class Pixel_Scout_Media_Hooks {
	/**
	 * @param Pixel_Scout_Image_Indexer $indexer Single image indexer.
	 */
	public function __construct(
		private Pixel_Scout_Image_Indexer $indexer
	) {}

	/**
	 * Register media hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_attachment', [ $this, 'on_upload' ] );
		add_action( 'delete_attachment', [ $this, 'on_delete' ] );
	}

	/**
	 * Handle newly uploaded attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public function on_upload( int $attachment_id ): void {
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
