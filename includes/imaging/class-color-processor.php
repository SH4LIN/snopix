<?php
/**
 * Color histogram processor — produces a 48-float RGB channel histogram vector.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Imaging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Generates a normalised 48-element colour histogram (16 bins per RGB channel).
 */
class Color_Processor implements Processor_Interface {

	/**
	 * Number of bins per channel (0-255 divided into 16-value buckets).
	 */
	private const BINS = 16;

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
	 * @return array<string, array<int, float>> ['color_vector' => [48 floats]]
	 */
	public function process( $gd_resource, int $attachment_id ): array {
		$small = imagescale( $gd_resource, self::THUMB_SIZE, self::THUMB_SIZE );
		if ( false === $small ) {
			return array( 'color_vector' => array_fill( 0, 48, 0.0 ) );
		}

		$r_bins = array_fill( 0, self::BINS, 0 );
		$g_bins = array_fill( 0, self::BINS, 0 );
		$b_bins = array_fill( 0, self::BINS, 0 );

		for ( $x = 0; $x < self::THUMB_SIZE; $x++ ) {
			for ( $y = 0; $y < self::THUMB_SIZE; $y++ ) {
				$rgb = imagecolorat( $small, $x, $y );
				$r   = ( $rgb >> 16 ) & 0xFF;
				$g   = ( $rgb >> 8 ) & 0xFF;
				$b   = $rgb & 0xFF;

				++$r_bins[ (int) floor( $r / 16.0 ) ];
				++$g_bins[ (int) floor( $g / 16.0 ) ];
				++$b_bins[ (int) floor( $b / 16.0 ) ];
			}
		}

		imagedestroy( $small ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated

		$vector = array_merge(
			$this->normalise_bins( $r_bins ),
			$this->normalise_bins( $g_bins ),
			$this->normalise_bins( $b_bins )
		);

		return array( 'color_vector' => $vector );
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
