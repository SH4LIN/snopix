<?php
/**
 * Score calculator - computes a weighted similarity score between two fingerprints.
 *
 * @package Snopix
 */

namespace Snopix\Search;

use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Edge_Processor;
use Snopix\Imaging\Similarity;
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
	 * Per-request memo of decoded vectors. Keyed by the SHA-1 of the encoded
	 * payload (cheap and collision-free in practice). Cleared at the end of
	 * the request when the calculator instance is GC'd.
	 *
	 * @var array<string, array<int, float>>
	 */
	private array $decode_cache = array();

	/**
	 * Calculate the composite similarity score between two fingerprint arrays.
	 *
	 * Color_vector and edge_vector may be JSON-encoded strings (as stored in DB).
	 * Returns 0.0 if any required key is missing from either fingerprint.
	 *
	 * @param array<string, mixed> $query_fp  Fingerprint of the query image.
	 * @param array<string, mixed> $stored_fp Fingerprint row from the index.
	 *
	 * @return float Composite score in the range 0.0-1.0.
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
		$color_score  = $this->similarity->bhattacharyya_similarity(
			$query_color,
			$stored_color,
			Color_Processor::COMPONENT_SIZES
		);

		$query_edge  = $this->decode_vector( $query_fp['edge_vector'] );
		$stored_edge = $this->decode_vector( $stored_fp['edge_vector'] );
		$edge_score  = $this->similarity->bhattacharyya_similarity(
			$query_edge,
			$stored_edge,
			Edge_Processor::COMPONENT_SIZES
		);

		return ( self::PHASH_WEIGHT * $phash_score )
			+ ( self::COLOR_WEIGHT * $color_score )
			+ ( self::EDGE_WEIGHT * $edge_score );
	}

	/**
	 * Highest pHash hamming distance that can still reach the composite
	 * threshold, assuming perfect colour and edge scores:
	 *
	 *     PHASH_WEIGHT · (1 - d/64) + COLOR_WEIGHT + EDGE_WEIGHT >= threshold
	 *
	 * Used by the search pipeline's candidate pre-filter so it never excludes
	 * a row the final composite-score gate could have accepted.
	 *
	 * @param float $threshold Composite-score floor in [0.0, 1.0].
	 *
	 * @return int Hamming distance in [0, 64].
	 */
	public static function max_passing_hamming( float $threshold ): int {
		$bound = 64.0 * ( 1.0 - ( ( $threshold - self::COLOR_WEIGHT - self::EDGE_WEIGHT ) / self::PHASH_WEIGHT ) );
		return max( 0, min( 64, (int) floor( $bound ) ) );
	}

	/**
	 * Decode a vector that may be a JSON string or already an array.
	 *
	 * @param mixed $value JSON string or array.
	 *
	 * @return array<int, float>
	 */
	private function decode_vector( mixed $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		$key = sha1( $value );
		if ( isset( $this->decode_cache[ $key ] ) ) {
			return $this->decode_cache[ $key ];
		}

		$decoded = json_decode( $value, true );
		$vector  = is_array( $decoded ) ? $decoded : array();

		$this->decode_cache[ $key ] = $vector;
		return $vector;
	}
}
