<?php
/**
 * Score calculator — computes a weighted similarity score between two fingerprints.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Search;

use PixelScout\Imaging\Similarity;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculates a composite similarity score from pHash, color, and edge fingerprints.
 */
class Score_Calculator {

	private const PHASH_WEIGHT = 0.40;
	private const COLOR_WEIGHT = 0.35;
	private const EDGE_WEIGHT  = 0.25;

	/**
	 * Constructor.
	 *
	 * @param Similarity $similarity Similarity metrics provider.
	 */
	public function __construct( private Similarity $similarity ) {}

	/**
	 * Calculate the composite similarity score between two fingerprint arrays.
	 *
	 * Color_vector and edge_vector may be JSON-encoded strings (as stored in DB).
	 * Returns 0.0 if any required key is missing from either fingerprint.
	 *
	 * @param array<string, mixed> $query_fp  Fingerprint of the query image.
	 * @param array<string, mixed> $stored_fp Fingerprint row from the index.
	 *
	 * @return float Composite score in the range 0.0–1.0.
	 */
	public function calculate( array $query_fp, array $stored_fp ): float {
		$required = array( 'phash', 'color_vector', 'edge_vector' );

		foreach ( $required as $key ) {
			if ( ! isset( $query_fp[ $key ], $stored_fp[ $key ] ) ) {
				return 0.0;
			}
		}

		$phash_score = 1.0 - ( $this->similarity->hamming_distance( $query_fp['phash'], $stored_fp['phash'] ) / 64.0 );

		$query_color  = $this->decode_vector( $query_fp['color_vector'] );
		$stored_color = $this->decode_vector( $stored_fp['color_vector'] );
		$color_score  = $this->similarity->bhattacharyya_similarity( $query_color, $stored_color, 3 );

		$query_edge  = $this->decode_vector( $query_fp['edge_vector'] );
		$stored_edge = $this->decode_vector( $stored_fp['edge_vector'] );
		$edge_score  = $this->similarity->cosine_similarity( $query_edge, $stored_edge );

		return ( self::PHASH_WEIGHT * $phash_score )
			+ ( self::COLOR_WEIGHT * $color_score )
			+ ( self::EDGE_WEIGHT * $edge_score );
	}

	/**
	 * Decode a vector that may be a JSON string or already an array.
	 *
	 * @param mixed $value JSON string or array.
	 *
	 * @return array<int, float>
	 */
	private function decode_vector( mixed $value ): array {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			return is_array( $decoded ) ? $decoded : array();
		}

		return is_array( $value ) ? $value : array();
	}
}
