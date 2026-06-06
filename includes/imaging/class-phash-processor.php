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
			return array( 'phash' => str_repeat( '0', 16 ) );
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
	 * Compute a separable 2D DCT and return the 9×9 top-left block.
	 *
	 * @param array<int, array<int, float>> $pixels 32×32 pixel values.
	 *
	 * @return array<int, array<int, float>> 9×9 DCT coefficients.
	 */
	private function compute_dct( array $pixels ): array {
		$size         = 32;
		$table        = self::cosine_table();
		$intermediate = array();

		for ( $x = 0; $x < $size; $x++ ) {
			for ( $v = 0; $v < 9; $v++ ) {
				$sum = 0.0;
				for ( $y = 0; $y < $size; $y++ ) {
					$sum += $pixels[ $x ][ $y ] * $table[ $v ][ $y ];
				}
				$intermediate[ $x ][ $v ] = $sum;
			}
		}

		$dct = array();
		for ( $u = 0; $u < 9; $u++ ) {
			$cu = ( 0 === $u ) ? ( 1.0 / sqrt( 2.0 ) ) : 1.0;
			for ( $v = 0; $v < 9; $v++ ) {
				$cv  = ( 0 === $v ) ? ( 1.0 / sqrt( 2.0 ) ) : 1.0;
				$sum = 0.0;
				for ( $x = 0; $x < $size; $x++ ) {
					$sum += $intermediate[ $x ][ $v ] * $table[ $u ][ $x ];
				}
				$dct[ $u ][ $v ] = ( 1.0 / 4.0 ) * $cu * $cv * $sum;
			}
		}

		return $dct;
	}

	/**
	 * Lazy-initialised 9×32 lookup of cos(pi · (2x + 1) · u / 64).
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
		for ( $u = 0; $u < 9; $u++ ) {
			$row = array();
			for ( $x = 0; $x < 32; $x++ ) {
				$row[ $x ] = cos( M_PI * ( 2.0 * $x + 1.0 ) * $u / 64.0 );
			}
			$table[ $u ] = $row;
		}
		return $table;
	}

	/**
	 * Compute a 64-bit hash from low-frequency AC coefficients.
	 *
	 * The 63 AC coefficients in the 8×8 block are supplemented by the average
	 * of the next horizontal and vertical frequencies. This keeps all 64 bits
	 * discriminating while excluding the DC brightness coefficient.
	 *
	 * @param array<int, array<int, float>> $dct 9×9 DCT coefficients.
	 *
	 * @return array<int, int> 64 bits (0 or 1).
	 */
	private function compute_bits( array $dct ): array {
		$coefficients = array();
		for ( $u = 0; $u < 8; $u++ ) {
			for ( $v = 0; $v < 8; $v++ ) {
				if ( 0 !== $u || 0 !== $v ) {
					$coefficients[] = $dct[ $u ][ $v ];
				}
			}
		}
		$coefficients[] = ( $dct[8][0] + $dct[0][8] ) / 2.0;

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
