<?php
/**
 * Query image handler — uploads a temporary image for similarity search.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Handles uploading and cleaning up a query image for reverse image search.
 */
class Query_Image {

	/**
	 * Allowed MIME types for query images.
	 */
	private const ALLOWED_MIMES = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];

	/**
	 * Maximum allowed file size in bytes (10 MB).
	 */
	private const MAX_FILE_SIZE = 10485760;

	/**
	 * Upload a query image from a $_FILES-style array and insert it as an attachment.
	 *
	 * @param array<string, mixed> $file Entry from $_FILES.
	 *
	 * @return int|false Attachment ID on success, false on failure.
	 */
	public function from_upload( array $file ): int|false {
		if ( isset( $file['size'] ) && $file['size'] > self::MAX_FILE_SIZE ) {
			return false;
		}

		$type_data = wp_check_filetype( $file['name'] );

		if ( ! in_array( $type_data['type'], self::ALLOWED_MIMES, true ) ) {
			return false;
		}

		$overrides = [ 'test_form' => false ];
		$upload    = wp_handle_upload( $file, $overrides );

		if ( isset( $upload['error'] ) || ! isset( $upload['file'] ) ) {
			return false;
		}

		$attachment = [
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( basename( $upload['file'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	/**
	 * Delete the temporary query attachment and its files.
	 *
	 * @param int $attachment_id Attachment ID to remove.
	 *
	 * @return void
	 */
	public function cleanup( int $attachment_id ): void {
		wp_delete_attachment( $attachment_id, true );
	}
}
