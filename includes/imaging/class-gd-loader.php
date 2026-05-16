<?php
/**
 * GD image loader — creates and destroys GD resources from WordPress attachments.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Imaging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Loads GD image resources for WordPress media library attachments.
 */
class GD_Loader {

	/**
	 * MIME types supported by this loader.
	 *
	 * @var array<string, callable-string>
	 */
	private const SUPPORTED_MIMES = [
		'image/jpeg' => 'imagecreatefromjpeg',
		'image/png'  => 'imagecreatefrompng',
		'image/gif'  => 'imagecreatefromgif',
		'image/webp' => 'imagecreatefromwebp',
	];

	/**
	 * Load a GD resource for the given attachment.
	 *
	 * @param int $attachment_id WordPress attachment ID.
	 *
	 * @return mixed GdImage resource, or false on failure.
	 */
	public function load( int $attachment_id ) {
		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			return false;
		}

		$mime = $this->get_mime_type( $attachment_id, $file );

		if ( ! isset( self::SUPPORTED_MIMES[ $mime ] ) ) {
			return false;
		}

		$fn = self::SUPPORTED_MIMES[ $mime ];

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$resource = @$fn( $file );

		return $resource ?: false;
	}

	/**
	 * Destroy a GD resource to free memory.
	 *
	 * @param mixed $gd_resource GD image resource or GdImage object.
	 *
	 * @return void
	 */
	public function destroy( $gd_resource ): void {
		if ( $gd_resource ) {
			imagedestroy( $gd_resource );
		}
	}

	/**
	 * Resolve MIME type from attachment metadata or file check.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file          Absolute file path.
	 *
	 * @return string
	 */
	private function get_mime_type( int $attachment_id, string $file ): string {
		$meta = wp_get_attachment_metadata( $attachment_id );

		if ( isset( $meta['mime_type'] ) && '' !== $meta['mime_type'] ) {
			return $meta['mime_type'];
		}

		$checked = wp_check_filetype( $file );
		return isset( $checked['type'] ) ? (string) $checked['type'] : '';
	}
}
