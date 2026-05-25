<?php
/**
 * Shared helper functions.
 *
 * @package Snopix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'snopix_get_allowed_mime_types' ) ) {
	/**
	 * Get image MIME types allowed by Snopix.
	 *
	 * @return array<string>
	 */
	function snopix_get_allowed_mime_types(): array {
		return array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/bmp',
		);
	}
}

if ( ! function_exists( 'snopix_format_filesize' ) ) {
	/**
	 * Format byte size for admin output.
	 *
	 * @param int $bytes Number of bytes.
	 *
	 * @return string
	 */
	function snopix_format_filesize( int $bytes ): string {
		return size_format( max( 0, $bytes ) );
	}
}
