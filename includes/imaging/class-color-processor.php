<?php
/**
 * Color histogram processor - produces a 32-float HSV histogram vector.
 *
 * @package Snopix
 */

namespace Snopix\Imaging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Generates a normalised 32-element colour histogram (16 hue + 8 saturation
 * + 8 value bins).
 *
 * Hue votes are saturation-weighted (the closer a pixel is to grey, the more
 * its hue is noise) with the remaining weight spread uniformly across the hue
 * bins, and every vote is split linearly between its two nearest bins
 * (circularly for hue) so a small colour shift moves mass between adjacent
 * bins instead of jumping.
 */
class Color_Processor implements Processor_Interface {

	/**
	 * Number of hue bins.
	 */
	private const HUE_BINS = 16;

	/**
	 * Number of saturation bins.
	 */
	private const SATURATION_BINS = 8;

	/**
	 * Number of value (brightness) bins.
	 */
	private const VALUE_BINS = 8;

	/**
	 * Bin count of each histogram component concatenated into the vector.
	 *
	 * Single source of truth for consumers that compare stored vectors; each
	 * component is independently normalised to sum to 1.0.
	 */
	public const COMPONENT_SIZES = array( self::HUE_BINS, self::SATURATION_BINS, self::VALUE_BINS );

	/**
	 * Total number of floats in the vector.
	 */
	private const VECTOR_LENGTH = self::HUE_BINS + self::SATURATION_BINS + self::VALUE_BINS;

	/**
	 * Side length of the thumbnail used for histogram sampling.
	 */
	private const THUMB_SIZE = 150;

	/**
	 * Total pixels in the thumbnail (150 × 150).
	 */
	private const TOTAL_PIXELS = 22500;

	/**
	 * Generate colour histogram fingerprint for an image.
	 *
	 * @param mixed $gd_resource  GD image resource or GdImage object.
	 * @param int   $attachment_id WordPress attachment ID.
	 *
	 * @return array<string, array<int, float>> ['color_vector' => [32 floats]]
	 */
	public function process( $gd_resource, int $attachment_id ): array {
		$small = imagescale( $gd_resource, self::THUMB_SIZE, self::THUMB_SIZE );
		if ( false === $small ) {
			return array( 'color_vector' => array_fill( 0, self::VECTOR_LENGTH, 0.0 ) );
		}

		$hue_bins        = array_fill( 0, self::HUE_BINS, 0.0 );
		$saturation_bins = array_fill( 0, self::SATURATION_BINS, 0.0 );
		$value_bins      = array_fill( 0, self::VALUE_BINS, 0.0 );
		$achromatic      = 0.0;

		for ( $x = 0; $x < self::THUMB_SIZE; $x++ ) {
			for ( $y = 0; $y < self::THUMB_SIZE; $y++ ) {
				$rgb = imagecolorat( $small, $x, $y );
				$r   = ( $rgb >> 16 ) & 0xFF;
				$g   = ( $rgb >> 8 ) & 0xFF;
				$b   = $rgb & 0xFF;

				list( $hue, $saturation, $value ) = $this->rgb_to_hsv( $r, $g, $b );

				$this->vote_circular( $hue_bins, self::HUE_BINS, $hue, $saturation );
				$achromatic += 1.0 - $saturation;
				$this->vote_linear( $saturation_bins, self::SATURATION_BINS, $saturation );
				$this->vote_linear( $value_bins, self::VALUE_BINS, $value );
			}
		}

		imagedestroy( $small ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated

		// Grey pixels voted into the hue bins with weight = saturation; spread
		// the remaining weight uniformly ("grey is every hue equally") so the
		// hue component still receives one vote per pixel and sums to 1.0.
		$spread = $achromatic / self::HUE_BINS;
		foreach ( $hue_bins as $i => $weight ) {
			$hue_bins[ $i ] = $weight + $spread;
		}

		$vector = array_merge(
			$this->normalise_bins( $hue_bins ),
			$this->normalise_bins( $saturation_bins ),
			$this->normalise_bins( $value_bins )
		);

		return array( 'color_vector' => $vector );
	}

	/**
	 * Split a vote between the two bins nearest to a position on a circular
	 * axis (used for hue, where the first and last bins are adjacent).
	 *
	 * @param array<int, float> $bins     Bin accumulator (modified in place).
	 * @param int               $count    Number of bins.
	 * @param float             $position Position in [0.0, 1.0).
	 * @param float             $weight   Vote weight.
	 *
	 * @return void
	 */
	private function vote_circular( array &$bins, int $count, float $position, float $weight ): void {
		$scaled = ( $position * $count ) - 0.5;
		$lower  = (int) floor( $scaled );
		$frac   = $scaled - $lower;
		$low    = ( ( $lower % $count ) + $count ) % $count;
		$high   = ( $low + 1 ) % $count;

		$bins[ $low ]  += ( 1.0 - $frac ) * $weight;
		$bins[ $high ] += $frac * $weight;
	}

	/**
	 * Split a unit vote between the two bins nearest to a position on a
	 * linear axis, clamping at the ends.
	 *
	 * @param array<int, float> $bins     Bin accumulator (modified in place).
	 * @param int               $count    Number of bins.
	 * @param float             $position Position in [0.0, 1.0].
	 *
	 * @return void
	 */
	private function vote_linear( array &$bins, int $count, float $position ): void {
		$scaled = ( $position * $count ) - 0.5;
		if ( $scaled <= 0.0 ) {
			$bins[0] += 1.0;
			return;
		}
		if ( $scaled >= (float) ( $count - 1 ) ) {
			$bins[ $count - 1 ] += 1.0;
			return;
		}

		$lower = (int) floor( $scaled );
		$frac  = $scaled - $lower;

		$bins[ $lower ]     += 1.0 - $frac;
		$bins[ $lower + 1 ] += $frac;
	}

	/**
	 * Convert an RGB colour to normalised hue, saturation and value components.
	 *
	 * @param int $r Red channel (0-255).
	 * @param int $g Green channel (0-255).
	 * @param int $b Blue channel (0-255).
	 *
	 * @return array{0: float, 1: float, 2: float} Hue, saturation and value in [0.0, 1.0].
	 */
	private function rgb_to_hsv( int $r, int $g, int $b ): array {
		$red   = $r / 255.0;
		$green = $g / 255.0;
		$blue  = $b / 255.0;
		$max   = max( $red, $green, $blue );
		$min   = min( $red, $green, $blue );
		$delta = $max - $min;

		$saturation = $max > 0.0 ? $delta / $max : 0.0;
		if ( $delta <= 0.0 ) {
			return array( 0.0, $saturation, $max );
		}

		if ( $max === $red ) {
			$hue = fmod( ( $green - $blue ) / $delta, 6.0 );
		} elseif ( $max === $green ) {
			$hue = ( ( $blue - $red ) / $delta ) + 2.0;
		} else {
			$hue = ( ( $red - $green ) / $delta ) + 4.0;
		}

		$hue /= 6.0;
		if ( $hue < 0.0 ) {
			$hue += 1.0;
		}

		return array( $hue, $saturation, $max );
	}

	/**
	 * Normalise a bin array by dividing each weight by total pixel count.
	 *
	 * @param array<int, float> $bins Accumulated bin weights.
	 *
	 * @return array<int, float>
	 */
	private function normalise_bins( array $bins ): array {
		return array_map(
			static fn( float $weight ): float => $weight / self::TOTAL_PIXELS,
			$bins
		);
	}
}
