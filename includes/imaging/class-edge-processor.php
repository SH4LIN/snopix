<?php
/**
 * Sobel edge-orientation processor - produces a 64-float normalised edge histogram.
 *
 * @package Snopix
 */

namespace Snopix\Imaging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Generates a 64-element edge-orientation histogram via Sobel filtering.
 *
 * The thumbnail is divided into a 4×4 grid of cells; each cell accumulates a
 * 4-bin histogram of unsigned gradient orientation, weighted by gradient
 * magnitude. This captures both where edges are and which way they run,
 * unlike a plain per-block magnitude average. The flattened 64-value vector
 * is normalised to sum to 1.0 so it compares as a distribution.
 */
class Edge_Processor implements Processor_Interface {

	/**
	 * Thumbnail side length for edge detection.
	 */
	private const THUMB_SIZE = 64;

	/**
	 * Number of spatial cells per axis (4×4 grid of 16×16-pixel cells).
	 */
	private const CELL_COUNT = 4;

	/**
	 * Number of unsigned orientation bins covering [0, π).
	 */
	private const ORIENTATION_BINS = 4;

	/**
	 * Bin count of each histogram component concatenated into the vector.
	 *
	 * The whole vector is one distribution summing to 1.0; consumers compare
	 * it with a single-component Bhattacharyya coefficient.
	 */
	public const COMPONENT_SIZES = array( self::CELL_COUNT * self::CELL_COUNT * self::ORIENTATION_BINS );

	/**
	 * Total number of floats in the vector.
	 */
	private const VECTOR_LENGTH = self::CELL_COUNT * self::CELL_COUNT * self::ORIENTATION_BINS;

	/**
	 * Generate edge-orientation fingerprint for an image.
	 *
	 * @param mixed $gd_resource  GD image resource or GdImage object.
	 * @param int   $attachment_id WordPress attachment ID.
	 *
	 * @return array<string, array<int, float>> ['edge_vector' => [64 floats]]
	 */
	public function process( $gd_resource, int $attachment_id ): array {
		$small = imagescale( $gd_resource, self::THUMB_SIZE, self::THUMB_SIZE );
		if ( false === $small ) {
			return array( 'edge_vector' => array_fill( 0, self::VECTOR_LENGTH, 0.0 ) );
		}

		imagefilter( $small, IMG_FILTER_GRAYSCALE );

		$pixels = $this->extract_pixels( $small );
		imagedestroy( $small ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated

		$histogram  = $this->compute_histogram( $pixels );
		$normalised = $this->normalise( $histogram );

		return array( 'edge_vector' => $normalised );
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
		$pixels = array();
		for ( $x = 0; $x < $size; $x++ ) {
			for ( $y = 0; $y < $size; $y++ ) {
				$rgb                = imagecolorat( $gd, $x, $y );
				$pixels[ $x ][ $y ] = (float) ( ( $rgb >> 16 ) & 0xFF );
			}
		}
		return $pixels;
	}

	/**
	 * Apply the Sobel operator and accumulate magnitude-weighted orientation
	 * votes into per-cell histograms.
	 *
	 * Border pixels (x=0, x=63, y=0, y=63) are skipped because Sobel requires
	 * a 3×3 neighbourhood. Each vote is split between the two nearest
	 * orientation bins, circularly: orientation is unsigned (period π), so the
	 * first and last bins are adjacent.
	 *
	 * @param array<int, array<int, float>> $p Pixel matrix [x][y].
	 *
	 * @return array<int, float> 64 accumulated weights (cells in row-major
	 *                           order, orientation bins within each cell).
	 */
	private function compute_histogram( array $p ): array {
		$size      = self::THUMB_SIZE;
		$cell_size = $size / self::CELL_COUNT; // 16.
		$bins      = array_fill( 0, self::VECTOR_LENGTH, 0.0 );

		for ( $x = 1; $x < $size - 1; $x++ ) {
			for ( $y = 1; $y < $size - 1; $y++ ) {
				$gx = -$p[ $x - 1 ][ $y - 1 ] + $p[ $x + 1 ][ $y - 1 ]
					+ -2.0 * $p[ $x - 1 ][ $y ] + 2.0 * $p[ $x + 1 ][ $y ]
					+ -$p[ $x - 1 ][ $y + 1 ] + $p[ $x + 1 ][ $y + 1 ];

				$gy = -$p[ $x - 1 ][ $y - 1 ] - 2.0 * $p[ $x ][ $y - 1 ] - $p[ $x + 1 ][ $y - 1 ]
					+ $p[ $x - 1 ][ $y + 1 ] + 2.0 * $p[ $x ][ $y + 1 ] + $p[ $x + 1 ][ $y + 1 ];

				$magnitude = sqrt( ( $gx * $gx ) + ( $gy * $gy ) );
				if ( $magnitude <= 0.0 ) {
					continue;
				}

				// Fold the gradient angle onto [0, π) - an edge has no sign.
				$theta = atan2( $gy, $gx );
				if ( $theta < 0.0 ) {
					$theta += M_PI;
				}
				if ( $theta >= M_PI ) {
					$theta -= M_PI;
				}

				$cell = ( intdiv( $x, $cell_size ) * self::CELL_COUNT ) + intdiv( $y, $cell_size );
				$base = $cell * self::ORIENTATION_BINS;

				// Split the vote between the two nearest orientation bins.
				$scaled = ( ( $theta / M_PI ) * self::ORIENTATION_BINS ) - 0.5;
				$lower  = (int) floor( $scaled );
				$frac   = $scaled - $lower;
				$low    = ( ( $lower % self::ORIENTATION_BINS ) + self::ORIENTATION_BINS ) % self::ORIENTATION_BINS;
				$high   = ( $low + 1 ) % self::ORIENTATION_BINS;

				$bins[ $base + $low ]  += ( 1.0 - $frac ) * $magnitude;
				$bins[ $base + $high ] += $frac * $magnitude;
			}
		}

		return $bins;
	}

	/**
	 * Normalise values into a distribution summing to 1.0.
	 *
	 * @param array<int, float> $values Raw accumulated weights.
	 *
	 * @return array<int, float> Normalised values.
	 */
	private function normalise( array $values ): array {
		$total = array_sum( $values );
		if ( $total <= 0.0 ) {
			return $values;
		}
		return array_map( static fn( float $v ): float => $v / $total, $values );
	}
}
