<?php
/**
 * Sobel edge-density processor — produces a 32-float normalised edge vector.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Imaging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Generates a 32-element normalised edge-density vector via Sobel filtering.
 */
class Edge_Processor implements Processor_Interface {

	/**
	 * Thumbnail side length for edge detection.
	 */
	private const THUMB_SIZE = 64;

	/**
	 * Number of blocks per axis (8×8 grid of 8×8-pixel blocks).
	 */
	private const BLOCK_COUNT = 8;

	/**
	 * Generate edge-density fingerprint for an image.
	 *
	 * @param mixed $gd_resource  GD image resource or GdImage object.
	 * @param int   $attachment_id WordPress attachment ID.
	 *
	 * @return array<string, array<int, float>> ['edge_vector' => [32 floats]]
	 */
	public function process( $gd_resource, int $attachment_id ): array {
		$small = imagescale( $gd_resource, self::THUMB_SIZE, self::THUMB_SIZE );
		if ( false === $small ) {
			return [ 'edge_vector' => array_fill( 0, 32, 0.0 ) ];
		}

		imagefilter( $small, IMG_FILTER_GRAYSCALE );

		$pixels = $this->extract_pixels( $small );
		imagedestroy( $small );

		$magnitude = $this->compute_sobel( $pixels );
		$blocks    = $this->compute_blocks( $magnitude );
		$reduced   = $this->reduce_to_32( $blocks );
		$normalised = $this->normalise( $reduced );

		return [ 'edge_vector' => $normalised ];
	}

	/**
	 * Extract greyscale pixel values into a 2D array indexed [x][y].
	 *
	 * @param mixed $gd GD resource.
	 *
	 * @return array<int, array<int, float>>
	 */
	private function extract_pixels( $gd ): array {
		$size   = self::THUMB_SIZE;
		$pixels = [];
		for ( $x = 0; $x < $size; $x++ ) {
			for ( $y = 0; $y < $size; $y++ ) {
				$rgb             = imagecolorat( $gd, $x, $y );
				$pixels[ $x ][ $y ] = (float) ( ( $rgb >> 16 ) & 0xFF );
			}
		}
		return $pixels;
	}

	/**
	 * Apply Sobel operator and return gradient magnitude for each pixel.
	 *
	 * Border pixels (x=0, x=63, y=0, y=63) are assigned magnitude 0
	 * because Sobel requires a 3×3 neighbourhood.
	 *
	 * @param array<int, array<int, float>> $p Pixel matrix [x][y].
	 *
	 * @return array<int, array<int, float>> Magnitude matrix [x][y].
	 */
	private function compute_sobel( array $p ): array {
		$size      = self::THUMB_SIZE;
		$magnitude = [];

		for ( $x = 0; $x < $size; $x++ ) {
			for ( $y = 0; $y < $size; $y++ ) {
				// Border pixels have no full 3×3 neighbourhood.
				if ( 0 === $x || $x === $size - 1 || 0 === $y || $y === $size - 1 ) {
					$magnitude[ $x ][ $y ] = 0.0;
					continue;
				}

				$gx = -$p[ $x - 1 ][ $y - 1 ] + $p[ $x + 1 ][ $y - 1 ]
					+ -2.0 * $p[ $x - 1 ][ $y ] + 2.0 * $p[ $x + 1 ][ $y ]
					+ -$p[ $x - 1 ][ $y + 1 ] + $p[ $x + 1 ][ $y + 1 ];

				$gy = -$p[ $x - 1 ][ $y - 1 ] - 2.0 * $p[ $x ][ $y - 1 ] - $p[ $x + 1 ][ $y - 1 ]
					+ $p[ $x - 1 ][ $y + 1 ] + 2.0 * $p[ $x ][ $y + 1 ] + $p[ $x + 1 ][ $y + 1 ];

				$magnitude[ $x ][ $y ] = sqrt( $gx * $gx + $gy * $gy );
			}
		}

		return $magnitude;
	}

	/**
	 * Divide 64×64 magnitude grid into 8×8 blocks and compute average per block.
	 *
	 * @param array<int, array<int, float>> $magnitude Magnitude matrix [x][y].
	 *
	 * @return array<int, float> 64 block averages in row-major order.
	 */
	private function compute_blocks( array $magnitude ): array {
		$block_size = self::THUMB_SIZE / self::BLOCK_COUNT; // 8.
		$flat       = [];

		for ( $bx = 0; $bx < self::BLOCK_COUNT; $bx++ ) {
			for ( $by = 0; $by < self::BLOCK_COUNT; $by++ ) {
				$sum = 0.0;
				for ( $x = $bx * $block_size; $x < ( $bx + 1 ) * $block_size; $x++ ) {
					for ( $y = $by * $block_size; $y < ( $by + 1 ) * $block_size; $y++ ) {
						$sum += $magnitude[ $x ][ $y ];
					}
				}
				$flat[] = $sum / (float) ( $block_size * $block_size );
			}
		}

		return $flat;
	}

	/**
	 * Average adjacent pairs in the 64-element flat array to produce 32 values.
	 *
	 * @param array<int, float> $flat 64 block averages.
	 *
	 * @return array<int, float> 32 values.
	 */
	private function reduce_to_32( array $flat ): array {
		$reduced = [];
		for ( $i = 0; $i < 32; $i++ ) {
			$reduced[] = ( $flat[ $i * 2 ] + $flat[ $i * 2 + 1 ] ) / 2.0;
		}
		return $reduced;
	}

	/**
	 * Normalise values to 0.0–1.0 by dividing by the maximum value.
	 *
	 * @param array<int, float> $values Raw edge values.
	 *
	 * @return array<int, float> Normalised values.
	 */
	private function normalise( array $values ): array {
		$max = max( $values );
		if ( $max <= 0.0 ) {
			return $values;
		}
		return array_map( static fn( float $v ): float => $v / $max, $values );
	}
}
