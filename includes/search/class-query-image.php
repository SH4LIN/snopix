<?php
/**
 * Query image handler — uploads a temporary image for similarity search.
 *
 * @package Snopix
 */

namespace Snopix\Search;

use Snopix\Hooks\Settings;

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
	private const ALLOWED_MIMES = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp' );

	/**
	 * Maximum allowed file size in bytes (10 MB).
	 */
	private const MAX_FILE_SIZE = 10485760;

	/**
	 * Maximum allowed decoded pixel count (16 MP ≈ 4096×4096). A compressed
	 * file under MAX_FILE_SIZE can still decompress into hundreds of MB of
	 * pixel data and OOM the PHP worker (classic decompression bomb), so we
	 * gate on dimensions before any GD function ever touches the file.
	 */
	private const MAX_PIXELS = 16_777_216;

	/**
	 * Postmeta flag set on probe attachments so Media_Hooks::on_upload can skip
	 * auto-indexing — the probe is a throwaway search input, not library media.
	 */
	public const PROBE_META_KEY = '_snopix_probe';

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

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Constrain wp_handle_upload to our explicit MIME allow-list. WP will
		// sniff the actual file bytes (finfo) and reject mismatches, which
		// closes the extension-only spoofing gap that wp_check_filetype()
		// alone would leave open.
		$mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'bmp'          => 'image/bmp',
		);

		$overrides = array(
			'test_form' => false,
			'mimes'     => $mimes,
		);
		$upload    = \wp_handle_upload( $file, $overrides );

		if ( isset( $upload['error'] ) || ! isset( $upload['file'] ) ) {
			return false;
		}

		if ( ! in_array( $upload['type'] ?? '', self::ALLOWED_MIMES, true ) ) {
			wp_delete_file( $upload['file'] );
			return false;
		}

		// Reject decompression bombs before any GD function decodes the file.
		$dims = @getimagesize( $upload['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $dims ) ) {
			wp_delete_file( $upload['file'] );
			return false;
		}
		if ( ( (int) $dims[0] * (int) $dims[1] ) > self::MAX_PIXELS ) {
			wp_delete_file( $upload['file'] );
			return false;
		}

		// Downscale oversized probes so the fingerprinting pipeline operates
		// on a bounded canvas. Failures here are non-fatal — fingerprinting
		// will fall back to the original file.
		$this->downscale_if_needed( $upload['file'], $upload['type'], (int) $dims[0], (int) $dims[1] );

		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( basename( $upload['file'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			// Setting the probe flag via meta_input means the meta row is
			// written inside wp_insert_attachment BEFORE the add_attachment
			// action fires, so Media_Hooks::on_upload can detect and skip.
			'meta_input'     => array(
				self::PROBE_META_KEY => 1,
			),
		);

		$attachment_id = \wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		$metadata = \wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		\wp_update_attachment_metadata( $attachment_id, $metadata );

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

	/**
	 * Downscale the uploaded probe file in-place when its longest edge exceeds
	 * the configured `downscale_max`. Uses WP's image editor abstraction so we
	 * get GD or Imagick depending on what the host has installed.
	 *
	 * @param string $path   Absolute file path on disk.
	 * @param string $mime   Mime type reported by wp_handle_upload.
	 * @param int    $width  Decoded pixel width.
	 * @param int    $height Decoded pixel height.
	 *
	 * @return void
	 */
	private function downscale_if_needed( string $path, string $mime, int $width, int $height ): void {
		$max_edge = Settings::get_downscale_max();
		if ( $max_edge <= 0 ) {
			return;
		}
		if ( $width <= $max_edge && $height <= $max_edge ) {
			return;
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return;
		}
		$resized = $editor->resize( $max_edge, $max_edge, false );
		if ( is_wp_error( $resized ) ) {
			return;
		}
		$editor->save( $path, $mime );
	}
}
