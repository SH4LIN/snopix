<?php
/**
 * Similarity metrics - Hamming distance for pHash, Bhattacharyya coefficient for histograms.
 *
 * @package Snopix
 */

namespace Snopix\Imaging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Provides image similarity scoring utilities.
 */
class Similarity {

	/**
	 * Compute the Hamming distance between two 16-character hex pHash strings.
	 *
	 * Returns the count of differing bits (0-64).
	 * Returns 64 if the strings are not the same length.
	 *
	 * @param string $h1 First hex pHash (16 chars = 64 bits).
	 * @param string $h2 Second hex pHash (16 chars = 64 bits).
	 *
	 * @return int Hamming distance 0-64.
	 */
	public function hamming_distance( string $h1, string $h2 ): int {
		if ( strlen( $h1 ) !== strlen( $h2 ) ) {
			return 64;
		}

		$bits1 = $this->hex_to_binary( $h1 );
		$bits2 = $this->hex_to_binary( $h2 );

		// XOR the binary strings and count differing positions.
		$diff = $bits1 ^ $bits2;
		return substr_count( $diff, "\x01" );
	}

	/**
	 * Bhattacharyya coefficient between two normalised histograms.
	 *
	 * Better than cosine for histogram comparison: penalises bin-by-bin divergence,
	 * not just direction. The input vectors are expected to be concatenations of
	 * normalised histogram components. Component sizes are explicit because the
	 * histograms may use different bin counts.
	 *
	 * @param array<int, float> $a               First histogram vector.
	 * @param array<int, float> $b               Second histogram vector.
	 * @param array<int, int>   $component_sizes Bin count for each concatenated histogram.
	 *
	 * @return float Bhattacharyya similarity in [0.0, 1.0].
	 */
	public function bhattacharyya_similarity( array $a, array $b, array $component_sizes ): float {
		if ( empty( $component_sizes ) ) {
			return 0.0;
		}

		foreach ( $component_sizes as $component_size ) {
			if ( $component_size < 1 ) {
				return 0.0;
			}
		}

		$expected_length = array_sum( $component_sizes );
		if ( count( $a ) !== $expected_length || count( $b ) !== $expected_length ) {
			return 0.0;
		}

		$sum    = 0.0;
		$offset = 0;

		foreach ( $component_sizes as $component_size ) {
			for ( $i = $offset; $i < $offset + $component_size; $i++ ) {
				$av   = max( 0.0, (float) $a[ $i ] );
				$bv   = max( 0.0, (float) $b[ $i ] );
				$sum += sqrt( $av * $bv );
			}
			$offset += $component_size;
		}

		return max( 0.0, min( 1.0, $sum / (float) count( $component_sizes ) ) );
	}

	/**
	 * Convert a hex string to a binary string where each byte is 0x00 or 0x01.
	 *
	 * Using byte-level comparison allows direct XOR via PHP string XOR operator.
	 *
	 * @param string $hex Hex string.
	 *
	 * @return string Binary string of 0x00/0x01 bytes.
	 */
	private function hex_to_binary( string $hex ): string {
		$binary = '';
		$len    = strlen( $hex );

		for ( $i = 0; $i < $len; $i++ ) {
			$nibble = hexdec( $hex[ $i ] );
			for ( $bit = 3; $bit >= 0; $bit-- ) {
				$binary .= chr( ( $nibble >> $bit ) & 1 );
			}
		}

		return $binary;
	}
}
