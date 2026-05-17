<?php
/**
 * Perceptual hash (pHash) processor using 2D DCT on a 32×32 greyscale thumbnail.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Imaging;

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
			return [ 'phash' => str_repeat( '0', 16 ) ];
		}

		imagefilter( $small, IMG_FILTER_GRAYSCALE );

		$pixels = $this->extract_pixels( $small, 32 );
		imagedestroy( $small );

		$dct  = $this->compute_dct( $pixels );
		$bits = $this->compute_bits( $dct );

		return [ 'phash' => $this->bits_to_hex( $bits ) ];
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
		$pixels = [];
		for ( $x = 0; $x < $size; $x++ ) {
			for ( $y = 0; $y < $size; $y++ ) {
				$rgb			 = imagecolorat( $gd, $x, $y );
				// After greyscale filter all channels are equal; use red channel.
				$pixels[ $x ][ $y ] = (float) ( ( $rgb >> 16 ) & 0xFF );
			}
		}
		return $pixels;
	}

	/**
	 * Compute 2D DCT of the 32×32 pixel matrix, return 8×8 top-left block.
	 *
	 * @param array<int, array<int, float>> $pixels 32×32 pixel values.
	 *
	 * @return array<int, array<int, float>> 8×8 DCT coefficients.
	 */
	private function compute_dct( array $pixels ): array {
		$size = 32;
		$dct  = [];

		for ( $u = 0; $u < 8; $u++ ) {
			for ( $v = 0; $v < 8; $v++ ) {
				$cu  = ( 0 === $u ) ? ( 1.0 / sqrt( 2.0 ) ) : 1.0;
				$cv  = ( 0 === $v ) ? ( 1.0 / sqrt( 2.0 ) ) : 1.0;
				$sum = 0.0;

				for ( $x = 0; $x < $size; $x++ ) {
					for ( $y = 0; $y < $size; $y++ ) {
						$sum += $pixels[ $x ][ $y ]
							* cos( M_PI * ( 2.0 * $x + 1.0 ) * $u / 64.0 )
							* cos( M_PI * ( 2.0 * $y + 1.0 ) * $v / 64.0 );
					}
				}

				$dct[ $u ][ $v ] = ( 1.0 / 4.0 ) * $cu * $cv * $sum;
			}
		}

		return $dct;
	}

	/**
	 * Compute 64-bit hash from 8×8 DCT block.
	 *
	 * All 64 coefficients (including DC [0][0]) are used to compute the mean.
	 * Each bit is 1 if the coefficient exceeds the mean, otherwise 0.
	 *
	 * @param array<int, array<int, float>> $dct 8×8 DCT coefficients.
	 *
	 * @return array<int, int> 64 bits (0 or 1).
	 */
	private function compute_bits( array $dct ): array {
		$flat = [];
		for ( $u = 0; $u < 8; $u++ ) {
			for ( $v = 0; $v < 8; $v++ ) {
				$flat[] = $dct[ $u ][ $v ];
			}
		}

		$mean = array_sum( $flat ) / 64.0;

		$bits = [];
		foreach ( $flat as $value ) {
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
