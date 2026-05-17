<?php
/**
 * Shared helper functions.
 *
 * @package Pixel_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ps_get_allowed_mime_types' ) ) {
	/**
	 * Get image MIME types allowed by Pixel Scout.
	 *
	 * @return array<string>
	 */
	function ps_get_allowed_mime_types(): array {
		return array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
		);
	}
}

if ( ! function_exists( 'ps_format_filesize' ) ) {
	/**
	 * Format byte size for admin output.
	 *
	 * @param int $bytes Number of bytes.
	 *
	 * @return string
	 */
	function ps_format_filesize( int $bytes ): string {
		return size_format( max( 0, $bytes ) );
	}
}
