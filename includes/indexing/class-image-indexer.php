<?php
/**
 * Single image indexing service.
 *
 * @package Pixel_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles indexing of individual images via fingerprint generation.
 */
class Pixel_Scout_Image_Indexer {
	/**
	 * @param Pixel_Scout_Mime_Validator $validator MIME validator.
	 * @param Pixel_Scout_Fingerprint_Factory $factory Fingerprint factory.
	 * @param Pixel_Scout_Index_Repository $repository Index repository.
	 */
	public function __construct(
		private Pixel_Scout_Mime_Validator $validator,
		private Pixel_Scout_Fingerprint_Factory $factory,
		private Pixel_Scout_Index_Repository $repository
	) {}

	/**
	 * Index a single attachment image.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool True if indexed, false otherwise.
	 */
	public function index_single( int $attachment_id ): bool {
		$mime = get_post_mime_type( $attachment_id );

		if ( ! $this->validator->is_allowed( $mime ) ) {
			return false;
		}

		$fingerprint = $this->factory->generate( $attachment_id );

		if ( empty( $fingerprint ) ) {
			return false;
		}

		return $this->repository->upsert( $attachment_id, $fingerprint );
	}

	/**
	 * Delete indexed entry for attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool
	 */
	public function on_delete( int $attachment_id ): bool {
		return $this->repository->delete( $attachment_id );
	}
}
