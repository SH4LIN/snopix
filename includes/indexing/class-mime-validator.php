<?php
/**
 * MIME type validator for image indexing.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Indexing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Validates whether an attachment's MIME type is indexable.
 */
class Mime_Validator {
	/**
	 * Allowed MIME types for indexing.
	 *
	 * @var array<string>
	 */
	private const ALLOWED = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
	);

	/**
	 * Check if MIME type is allowed for indexing.
	 *
	 * @param string $mime MIME type string.
	 *
	 * @return bool
	 */
	public function is_allowed( string $mime ): bool {
		return in_array( $mime, self::ALLOWED, true );
	}

	/**
	 * Get list of allowed MIME types.
	 *
	 * @return array<string>
	 */
	public function get_allowed(): array {
		return self::ALLOWED;
	}
}
