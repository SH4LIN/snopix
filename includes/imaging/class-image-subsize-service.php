<?php
/**
 * Wrapper around WordPress core wp_create_image_subsizes() so callers can
 * inject a mock in tests and so the `intermediate_image_sizes_advanced` filter
 * is added + removed in one place under try/finally (council Security #2).
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Imaging;

use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates image subsizes for an attachment.
 */
class Image_Subsize_Service {

	/**
	 * Regenerate every registered subsize for the attachment.
	 *
	 * @param int $attachment_id WP attachment post ID.
	 *
	 * @return bool True on success.
	 */
	public function create_all( int $attachment_id ): bool {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return false;
		}
		$result = wp_create_image_subsizes( $file, $attachment_id );
		return ! is_wp_error( $result ) && is_array( $result );
	}

	/**
	 * Regenerate only the named subset of subsizes for the attachment. Uses a
	 * scoped `intermediate_image_sizes_advanced` filter so core honors the
	 * shortlist for the duration of this one call. Wrapped in try/finally so
	 * an exception in any subsize generator hook cannot leak the filter.
	 *
	 * @param int           $attachment_id WP attachment post ID.
	 * @param array<string> $size_names    Registered size names to (re)create.
	 *
	 * @return bool True on success, false on missing source or error.
	 */
	public function create_subset( int $attachment_id, array $size_names ): bool {
		if ( empty( $size_names ) ) {
			return true;
		}
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return false;
		}

		$allow  = array_flip( $size_names );
		$filter = static function ( array $sizes ) use ( $allow ): array {
			return array_intersect_key( $sizes, $allow );
		};

		add_filter( 'intermediate_image_sizes_advanced', $filter, 999 );
		try {
			$result = wp_create_image_subsizes( $file, $attachment_id );
		} catch ( Throwable $e ) {
			remove_filter( 'intermediate_image_sizes_advanced', $filter, 999 );
			throw $e;
		}
		remove_filter( 'intermediate_image_sizes_advanced', $filter, 999 );

		return ! is_wp_error( $result ) && is_array( $result );
	}

	/**
	 * Compute the set of registered size names that are missing for the
	 * attachment — either absent from metadata or present in metadata but
	 * whose file is missing on disk.
	 *
	 * @param int                                       $attachment_id WP attachment post ID.
	 * @param array<string, array{w:int,h:int,crop:bool}>|null $registered Optional pre-fetched registered-subsize map.
	 *                                                                When the regenerator caches the map at the top of a
	 *                                                                batch and passes it through, this avoids calling
	 *                                                                `wp_get_registered_image_subsizes()` once per attachment.
	 *
	 * @return array<string> Missing size names.
	 */
	public function missing_sizes( int $attachment_id, ?array $registered = null ): array {
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) ) {
			return array();
		}

		$file = get_attached_file( $attachment_id );
		$dir  = $file ? dirname( $file ) : '';

		if ( null === $registered ) {
			$registered = wp_get_registered_image_subsizes();
		}

		$missing = array();
		foreach ( array_keys( $registered ) as $name ) {
			$entry = $meta['sizes'][ $name ] ?? null;
			if ( ! is_array( $entry ) || empty( $entry['file'] ) ) {
				$missing[] = $name;
				continue;
			}
			if ( $dir && ! file_exists( $dir . '/' . $entry['file'] ) ) {
				$missing[] = $name;
			}
		}
		return $missing;
	}
}
