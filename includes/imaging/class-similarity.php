<?php
/**
 * Similarity metrics — Hamming distance for pHash, cosine similarity for vectors.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Imaging;

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
	 * Returns the count of differing bits (0–64).
	 * Returns 64 if the strings are not the same length.
	 *
	 * @param string $h1 First hex pHash (16 chars = 64 bits).
	 * @param string $h2 Second hex pHash (16 chars = 64 bits).
	 *
	 * @return int Hamming distance 0–64.
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
	 * Compute cosine similarity between two float vectors.
	 *
	 * Returns a value in the range 0.0–1.0.
	 * Returns 0.0 if either vector has zero magnitude.
	 *
	 * @param array<int, float> $a First vector.
	 * @param array<int, float> $b Second vector.
	 *
	 * @return float Cosine similarity 0.0–1.0.
	 */
	public function cosine_similarity( array $a, array $b ): float {
		$dot  = 0.0;
		$mag_a = 0.0;
		$mag_b = 0.0;

		$count = min( count( $a ), count( $b ) );
		for ( $i = 0; $i < $count; $i++ ) {
			$dot   += $a[ $i ] * $b[ $i ];
			$mag_a += $a[ $i ] * $a[ $i ];
			$mag_b += $b[ $i ] * $b[ $i ];
		}

		$mag_a = sqrt( $mag_a );
		$mag_b = sqrt( $mag_b );

		if ( $mag_a <= 0.0 || $mag_b <= 0.0 ) {
			return 0.0;
		}

		$similarity = $dot / ( $mag_a * $mag_b );

		// Clamp to [0.0, 1.0] — floating point drift can produce values slightly outside.
		return max( 0.0, min( 1.0, $similarity ) );
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
