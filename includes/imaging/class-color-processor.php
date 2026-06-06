<?php
/**
 * Color histogram processor - produces a 24-float HSV histogram vector.
 *
 * @package Snopix
 */

namespace Snopix\Imaging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Generates a normalised 24-element colour histogram (16 hue + 8 saturation bins).
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
	 * @return array<string, array<int, float>> ['color_vector' => [24 floats]]
	 */
	public function process( $gd_resource, int $attachment_id ): array {
		$small = imagescale( $gd_resource, self::THUMB_SIZE, self::THUMB_SIZE );
		if ( false === $small ) {
			return array( 'color_vector' => array_fill( 0, 24, 0.0 ) );
		}

		$hue_bins        = array_fill( 0, self::HUE_BINS, 0 );
		$saturation_bins = array_fill( 0, self::SATURATION_BINS, 0 );

		for ( $x = 0; $x < self::THUMB_SIZE; $x++ ) {
			for ( $y = 0; $y < self::THUMB_SIZE; $y++ ) {
				$rgb = imagecolorat( $small, $x, $y );
				$r   = ( $rgb >> 16 ) & 0xFF;
				$g   = ( $rgb >> 8 ) & 0xFF;
				$b   = $rgb & 0xFF;

				list( $hue, $saturation ) = $this->rgb_to_hs( $r, $g, $b );

				$hue_bin        = min( self::HUE_BINS - 1, (int) floor( $hue * self::HUE_BINS ) );
				$saturation_bin = min( self::SATURATION_BINS - 1, (int) floor( $saturation * self::SATURATION_BINS ) );

				++$hue_bins[ $hue_bin ];
				++$saturation_bins[ $saturation_bin ];
			}
		}

		imagedestroy( $small ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated

		$vector = array_merge(
			$this->normalise_bins( $hue_bins ),
			$this->normalise_bins( $saturation_bins )
		);

		return array( 'color_vector' => $vector );
	}

	/**
	 * Convert an RGB colour to normalised hue and saturation components.
	 *
	 * @param int $r Red channel (0-255).
	 * @param int $g Green channel (0-255).
	 * @param int $b Blue channel (0-255).
	 *
	 * @return array{0: float, 1: float} Hue and saturation in [0.0, 1.0].
	 */
	private function rgb_to_hs( int $r, int $g, int $b ): array {
		$red   = $r / 255.0;
		$green = $g / 255.0;
		$blue  = $b / 255.0;
		$max   = max( $red, $green, $blue );
		$min   = min( $red, $green, $blue );
		$delta = $max - $min;

		$saturation = $max > 0.0 ? $delta / $max : 0.0;
		if ( $delta <= 0.0 ) {
			return array( 0.0, $saturation );
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

		return array( $hue, $saturation );
	}

	/**
	 * Normalise a bin array by dividing each count by total pixel count.
	 *
	 * @param array<int, int> $bins Raw bin counts.
	 *
	 * @return array<int, float>
	 */
	private function normalise_bins( array $bins ): array {
		return array_map(
			static fn( int $count ): float => (float) $count / self::TOTAL_PIXELS,
			$bins
		);
	}
}
