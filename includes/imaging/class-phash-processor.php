<?php
/**
 * Perceptual hash (pHash) processor using 2D DCT on a 32×32 greyscale thumbnail.
 *
 * @package Snopix
 */

namespace Snopix\Imaging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Generates a 64-bit perceptual hash encoded as a 16-character hex string.
 */
class PHash_Processor implements Processor_Interface {

	/**
	 * Generate pHash fingerprint for an image.
	 *
	 * @param mixed $gd_resource  GD image resource or GdImage object.
	 * @param int   $attachment_id WordPress attachment ID.
	 *
	 * @return array<string, string> ['phash' => '16-char hex']
	 */
	public function process( $gd_resource, int $attachment_id ): array {
		$small = imagescale( $gd_resource, 32, 32 );
		if ( false === $small ) {
			// Signal failure rather than emitting an all-zero hash: a zero hash
			// is indistinguishable from a legitimately blank image, so failed
			// images would cluster together as false perceptual duplicates. The
			// factory maps an empty fragment to "unprocessable".
			return array();
		}

		imagefilter( $small, IMG_FILTER_GRAYSCALE );

		$pixels = $this->extract_pixels( $small, 32 );
		imagedestroy( $small ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated

		$dct  = $this->compute_dct( $pixels );
		$bits = $this->compute_bits( $dct );

		return array( 'phash' => $this->bits_to_hex( $bits ) );
	}

	/**
	 * Extract greyscale pixel values from a GD resource into a 2D array.
	 *
	 * @param mixed $gd  GD resource.
	 * @param int   $size Width/height (assumed square).
	 *
	 * @return array<int, array<int, float>>
	 */
	private function extract_pixels( $gd, int $size ): array {
		$pixels = array();
		for ( $x = 0; $x < $size; $x++ ) {
			for ( $y = 0; $y < $size; $y++ ) {
				$rgb = imagecolorat( $gd, $x, $y );
				// After greyscale filter all channels are equal; use red channel.
				$pixels[ $x ][ $y ] = (float) ( ( $rgb >> 16 ) & 0xFF );
			}
		}
		return $pixels;
	}

	/**
	 * Compute a separable 2D DCT and return the 8×8 low-frequency AC block
	 * (u, v ∈ 1..8 - the DC row and column are excluded entirely).
	 *
	 * The normalisation factors for u=0 / v=0 never apply to this block, so
	 * the coefficient is a plain (1/4) scale.
	 *
	 * @param array<int, array<int, float>> $pixels 32×32 pixel values.
	 *
	 * @return array<int, array<int, float>> DCT coefficients indexed [u][v], u, v ∈ 1..8.
	 */
	private function compute_dct( array $pixels ): array {
		$size         = 32;
		$table        = self::cosine_table();
		$intermediate = array();

		for ( $x = 0; $x < $size; $x++ ) {
			for ( $v = 1; $v < 9; $v++ ) {
				$sum = 0.0;
				for ( $y = 0; $y < $size; $y++ ) {
					$sum += $pixels[ $x ][ $y ] * $table[ $v ][ $y ];
				}
				$intermediate[ $x ][ $v ] = $sum;
			}
		}

		$dct = array();
		for ( $u = 1; $u < 9; $u++ ) {
			for ( $v = 1; $v < 9; $v++ ) {
				$sum = 0.0;
				for ( $x = 0; $x < $size; $x++ ) {
					$sum += $intermediate[ $x ][ $v ] * $table[ $u ][ $x ];
				}
				$dct[ $u ][ $v ] = ( 1.0 / 4.0 ) * $sum;
			}
		}

		return $dct;
	}

	/**
	 * Lazy-initialised 8×32 lookup of cos(pi · (2x + 1) · u / 64) for u ∈ 1..8.
	 *
	 * The DCT inner loop calls `cos()` 131k times per image when computed
	 * naively. Caching the values once per PHP process turns the loop into
	 * pure floating-point multiplies.
	 *
	 * @return array<int, array<int, float>> Cosine table indexed by [u][x].
	 */
	private static function cosine_table(): array {
		static $table = null;
		if ( null !== $table ) {
			return $table;
		}
		$table = array();
		for ( $u = 1; $u < 9; $u++ ) {
			$row = array();
			for ( $x = 0; $x < 32; $x++ ) {
				$row[ $x ] = cos( M_PI * ( 2.0 * $x + 1.0 ) * $u / 64.0 );
			}
			$table[ $u ] = $row;
		}
		return $table;
	}

	/**
	 * Compute a 64-bit hash from the 8×8 low-frequency AC block.
	 *
	 * The block (u, v ∈ 1..8) contains 64 genuine AC coefficients and no DC
	 * term: the DC coefficient tracks overall brightness, is typically an
	 * order of magnitude larger than the AC terms, and would skew the mean.
	 * Each bit is 1 if its coefficient exceeds the block mean, else 0.
	 *
	 * @param array<int, array<int, float>> $dct DCT coefficients indexed [u][v], u, v ∈ 1..8.
	 *
	 * @return array<int, int> 64 bits (0 or 1).
	 */
	private function compute_bits( array $dct ): array {
		$coefficients = array();
		for ( $u = 1; $u < 9; $u++ ) {
			for ( $v = 1; $v < 9; $v++ ) {
				$coefficients[] = $dct[ $u ][ $v ];
			}
		}

		$mean = array_sum( $coefficients ) / 64.0;

		$bits = array();
		foreach ( $coefficients as $value ) {
			$bits[] = $value > $mean ? 1 : 0;
		}

		return $bits;
	}

	/**
	 * Pack 64 bits into 8 bytes and encode as a 16-character hex string.
	 *
	 * @param array<int, int> $bits 64 bits.
	 *
	 * @return string 16-char hex string.
	 */
	private function bits_to_hex( array $bits ): string {
		$hex = '';
		for ( $i = 0; $i < 8; $i++ ) {
			$byte = 0;
			for ( $j = 0; $j < 8; $j++ ) {
				$byte |= $bits[ $i * 8 + $j ] << ( 7 - $j );
			}
			$hex .= sprintf( '%02x', $byte );
		}
		return $hex;
	}
}
