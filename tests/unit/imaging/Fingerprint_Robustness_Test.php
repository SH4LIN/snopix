<?php
/**
 * Cross-processor robustness tests.
 *
 * Variations are generated at runtime with GD from the committed base
 * fixtures, and the complex images are drawn synthetically, so these tests
 * add zero bytes to the fixture archive.
 *
 * @package Snopix
 */

use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Edge_Processor;
use Snopix\Imaging\PHash_Processor;
use Snopix\Imaging\Similarity;

/**
 * @covers \Snopix\Imaging\PHash_Processor
 * @covers \Snopix\Imaging\Color_Processor
 * @covers \Snopix\Imaging\Edge_Processor
 */
final class Fingerprint_Robustness_Test extends Snopix_Unit_TestCase {

	private PHash_Processor $phash;
	private Color_Processor $color;
	private Edge_Processor $edge;
	private Similarity $similarity;

	protected function setUp(): void {
		parent::setUp();
		$this->phash      = new PHash_Processor();
		$this->color      = new Color_Processor();
		$this->edge       = new Edge_Processor();
		$this->similarity = new Similarity();
	}

	/* ── Runtime variation generators ─────────────────────────────────────── */

	/**
	 * Decode a base fixture and apply a GD filter to it.
	 *
	 * @param int   $id     Fixture index.
	 * @param int   $filter IMG_FILTER_* constant.
	 * @param int   ...$args Filter arguments.
	 *
	 * @return \GdImage
	 */
	private static function filtered_fixture( int $id, int $filter, int ...$args ): \GdImage {
		$gd = self::gd_from_fixture( $id );
		self::assertTrue( imagefilter( $gd, $filter, ...$args ) );
		return $gd;
	}

	/**
	 * Crop a fixture to its centred fraction.
	 *
	 * @param int   $id       Fixture index.
	 * @param float $fraction Kept fraction of each axis (e.g. 0.9).
	 *
	 * @return \GdImage
	 */
	private static function cropped_fixture( int $id, float $fraction ): \GdImage {
		$gd      = self::gd_from_fixture( $id );
		$width   = imagesx( $gd );
		$height  = imagesy( $gd );
		$crop_w  = (int) round( $width * $fraction );
		$crop_h  = (int) round( $height * $fraction );
		$cropped = imagecrop(
			$gd,
			array(
				'x'      => (int) ( ( $width - $crop_w ) / 2 ),
				'y'      => (int) ( ( $height - $crop_h ) / 2 ),
				'width'  => $crop_w,
				'height' => $crop_h,
			)
		);
		imagedestroy( $gd ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
		self::assertInstanceOf( \GdImage::class, $cropped );
		return $cropped;
	}

	/**
	 * Add deterministic per-pixel noise to a fixture.
	 *
	 * @param int $id Fixture index.
	 *
	 * @return \GdImage
	 */
	private static function noisy_fixture( int $id ): \GdImage {
		$gd     = self::gd_from_fixture( $id );
		$width  = imagesx( $gd );
		$height = imagesy( $gd );

		mt_srand( 42 );
		for ( $x = 0; $x < $width; $x += 3 ) {
			for ( $y = 0; $y < $height; $y += 3 ) {
				$rgb   = imagecolorat( $gd, $x, $y );
				$noise = mt_rand( -12, 12 );
				$r     = max( 0, min( 255, ( ( $rgb >> 16 ) & 0xFF ) + $noise ) );
				$g     = max( 0, min( 255, ( ( $rgb >> 8 ) & 0xFF ) + $noise ) );
				$b     = max( 0, min( 255, ( $rgb & 0xFF ) + $noise ) );
				imagesetpixel( $gd, $x, $y, ( $r << 16 ) | ( $g << 8 ) | $b );
			}
		}

		return $gd;
	}

	/* ── Synthetic complex images ─────────────────────────────────────────── */

	/**
	 * Black/white stripes.
	 *
	 * @param bool $vertical Stripe direction.
	 *
	 * @return \GdImage
	 */
	private static function stripes( bool $vertical ): \GdImage {
		$gd    = imagecreatetruecolor( 64, 64 );
		$white = imagecolorallocate( $gd, 255, 255, 255 );
		for ( $i = 0; $i < 64; $i += 16 ) {
			if ( $vertical ) {
				imagefilledrectangle( $gd, $i, 0, $i + 7, 63, $white );
			} else {
				imagefilledrectangle( $gd, 0, $i, 63, $i + 7, $white );
			}
		}
		return $gd;
	}

	/**
	 * Black/white checkerboard of 8px squares.
	 *
	 * @return \GdImage
	 */
	private static function checkerboard(): \GdImage {
		$gd    = imagecreatetruecolor( 64, 64 );
		$white = imagecolorallocate( $gd, 255, 255, 255 );
		for ( $x = 0; $x < 8; $x++ ) {
			for ( $y = 0; $y < 8; $y++ ) {
				if ( 0 === ( $x + $y ) % 2 ) {
					imagefilledrectangle( $gd, $x * 8, $y * 8, ( $x * 8 ) + 7, ( $y * 8 ) + 7, $white );
				}
			}
		}
		return $gd;
	}

	/**
	 * Four equal saturated quadrants: red, green, blue, yellow.
	 *
	 * @return \GdImage
	 */
	private static function quadrants(): \GdImage {
		$gd = imagecreatetruecolor( 150, 150 );
		imagefilledrectangle( $gd, 0, 0, 74, 74, imagecolorallocate( $gd, 255, 0, 0 ) );
		imagefilledrectangle( $gd, 75, 0, 149, 74, imagecolorallocate( $gd, 0, 255, 0 ) );
		imagefilledrectangle( $gd, 0, 75, 74, 149, imagecolorallocate( $gd, 0, 0, 255 ) );
		imagefilledrectangle( $gd, 75, 75, 149, 149, imagecolorallocate( $gd, 255, 255, 0 ) );
		return $gd;
	}

	/**
	 * Horizontal red→green→blue hue ramp.
	 *
	 * @return \GdImage
	 */
	private static function hue_ramp(): \GdImage {
		$gd = imagecreatetruecolor( 150, 150 );
		for ( $x = 0; $x < 150; $x++ ) {
			if ( $x < 75 ) {
				$colour = imagecolorallocate( $gd, 255 - (int) ( $x * 255 / 74 ), (int) ( $x * 255 / 74 ), 0 );
			} else {
				$colour = imagecolorallocate( $gd, 0, 255 - (int) ( ( $x - 75 ) * 255 / 74 ), (int) ( ( $x - 75 ) * 255 / 74 ) );
			}
			imagefilledrectangle( $gd, $x, 0, $x, 149, $colour );
		}
		return $gd;
	}

	/* ── Fingerprint shorthands ───────────────────────────────────────────── */

	/**
	 * @param \GdImage $gd Image (destroyed after processing).
	 *
	 * @return array{phash: string, color: array<int, float>, edge: array<int, float>}
	 */
	private function fingerprints( \GdImage $gd ): array {
		$result = array(
			'phash' => $this->phash->process( $gd, 1 )['phash'],
			'color' => $this->color->process( $gd, 1 )['color_vector'],
			'edge'  => $this->edge->process( $gd, 1 )['edge_vector'],
		);
		imagedestroy( $gd ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
		return $result;
	}

	/**
	 * Assert every metric ranks the variant closer to base than a different fixture.
	 *
	 * @param array<string, mixed> $base    Base fingerprints.
	 * @param array<string, mixed> $variant Variant fingerprints.
	 * @param array<string, mixed> $other   Different-image fingerprints.
	 *
	 * @return void
	 */
	private function assert_variant_ranks_closer( array $base, array $variant, array $other ): void {
		$this->assertLessThan(
			$this->similarity->hamming_distance( $base['phash'], $other['phash'] ),
			$this->similarity->hamming_distance( $base['phash'], $variant['phash'] )
		);
		$this->assertGreaterThan(
			$this->similarity->bhattacharyya_similarity( $base['color'], $other['color'], Color_Processor::COMPONENT_SIZES ),
			$this->similarity->bhattacharyya_similarity( $base['color'], $variant['color'], Color_Processor::COMPONENT_SIZES )
		);
		$this->assertGreaterThan(
			$this->similarity->bhattacharyya_similarity( $base['edge'], $other['edge'], Edge_Processor::COMPONENT_SIZES ),
			$this->similarity->bhattacharyya_similarity( $base['edge'], $variant['edge'], Edge_Processor::COMPONENT_SIZES )
		);
	}

	/* ── Variation tests ──────────────────────────────────────────────────── */

	public function test_brightness_shift_ranks_variant_closer_than_other_fixture(): void {
		$this->assert_variant_ranks_closer(
			$this->fingerprints( self::gd_from_fixture( 1 ) ),
			$this->fingerprints( self::filtered_fixture( 1, IMG_FILTER_BRIGHTNESS, 18 ) ),
			$this->fingerprints( self::gd_from_fixture( 10 ) )
		);
	}

	public function test_contrast_boost_ranks_variant_closer_than_other_fixture(): void {
		$this->assert_variant_ranks_closer(
			$this->fingerprints( self::gd_from_fixture( 3 ) ),
			$this->fingerprints( self::filtered_fixture( 3, IMG_FILTER_CONTRAST, -15 ) ),
			$this->fingerprints( self::gd_from_fixture( 15 ) )
		);
	}

	public function test_center_crop_ranks_variant_closer_than_other_fixture(): void {
		$this->assert_variant_ranks_closer(
			$this->fingerprints( self::gd_from_fixture( 5 ) ),
			$this->fingerprints( self::cropped_fixture( 5, 0.9 ) ),
			$this->fingerprints( self::gd_from_fixture( 20 ) )
		);
	}

	public function test_mild_noise_ranks_variant_closer_than_other_fixture(): void {
		$this->assert_variant_ranks_closer(
			$this->fingerprints( self::gd_from_fixture( 7 ) ),
			$this->fingerprints( self::noisy_fixture( 7 ) ),
			$this->fingerprints( self::gd_from_fixture( 12 ) )
		);
	}

	public function test_horizontal_flip_preserves_color_histogram(): void {
		$gd = self::gd_from_fixture( 1 );
		imageflip( $gd, IMG_FLIP_HORIZONTAL );

		$base    = $this->color->process( self::gd_from_fixture( 1 ), 1 )['color_vector'];
		$flipped = $this->fingerprints( $gd )['color'];

		// Flipping reorders pixels without changing them; only thumbnail
		// resampling introduces drift.
		$this->assertGreaterThan(
			0.99,
			$this->similarity->bhattacharyya_similarity( $base, $flipped, Color_Processor::COMPONENT_SIZES )
		);
	}

	public function test_rotation_preserves_color_histogram(): void {
		$gd      = self::gd_from_fixture( 1 );
		$rotated = imagerotate( $gd, 90, 0 );
		imagedestroy( $gd ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
		$this->assertInstanceOf( \GdImage::class, $rotated );

		$base = $this->color->process( self::gd_from_fixture( 1 ), 1 )['color_vector'];
		$rot  = $this->fingerprints( $rotated )['color'];

		// Same pixel population; the square thumbnail squashes the two aspect
		// ratios differently, so allow slightly more drift than a flip.
		$this->assertGreaterThan(
			0.97,
			$this->similarity->bhattacharyya_similarity( $base, $rot, Color_Processor::COMPONENT_SIZES )
		);
	}

	public function test_grayscale_conversion_spreads_hue_and_empties_saturation(): void {
		$vector = $this->fingerprints( self::filtered_fixture( 1, IMG_FILTER_GRAYSCALE ) )['color'];

		// Grey pixels have saturation 0: the hue component is pure uniform
		// achromatic spread and the saturation component collapses into bin 0.
		foreach ( array_slice( $vector, 0, 16 ) as $bin ) {
			$this->assertEqualsWithDelta( 1.0 / 16.0, $bin, 1e-9 );
		}
		$this->assertEqualsWithDelta( 1.0, $vector[16], 1e-9 );
	}

	/* ── Complex-image tests ──────────────────────────────────────────────── */

	public function test_vertical_stripes_concentrate_edge_orientation(): void {
		$vector = $this->fingerprints( self::stripes( true ) )['edge'];

		// Vertical edges → gradient angle 0 → votes split between the two
		// wrap-around orientation bins (0 and 3) in every cell.
		$vertical_mass = 0.0;
		$other_mass    = 0.0;
		foreach ( $vector as $i => $weight ) {
			if ( in_array( $i % 4, array( 0, 3 ), true ) ) {
				$vertical_mass += $weight;
			} else {
				$other_mass += $weight;
			}
		}

		$this->assertGreaterThan( 0.95, $vertical_mass );
		$this->assertLessThan( 0.05, $other_mass );
	}

	public function test_horizontal_stripes_concentrate_opposite_orientation(): void {
		$vector = $this->fingerprints( self::stripes( false ) )['edge'];

		// Horizontal edges → gradient angle π/2 → votes split between the two
		// middle orientation bins (1 and 2) in every cell.
		$horizontal_mass = 0.0;
		foreach ( $vector as $i => $weight ) {
			if ( in_array( $i % 4, array( 1, 2 ), true ) ) {
				$horizontal_mass += $weight;
			}
		}

		$this->assertGreaterThan( 0.95, $horizontal_mass );
	}

	public function test_checkerboard_hashes_differently_from_stripes(): void {
		$checker = $this->fingerprints( self::checkerboard() )['phash'];
		$striped = $this->fingerprints( self::stripes( true ) )['phash'];

		$this->assertGreaterThan( 8, $this->similarity->hamming_distance( $checker, $striped ) );
	}

	public function test_quadrant_image_bins_each_hue_quarter(): void {
		$vector = $this->fingerprints( self::quadrants() )['color'];

		// Four saturated hues at a quarter of the mass each: red (wraps bins
		// 15/0), yellow (bins 2/3), green (bins 4/5), blue (bins 10/11).
		// Thumbnail resampling blends the quadrant borders, hence the delta.
		$this->assertEqualsWithDelta( 0.25, $vector[15] + $vector[0], 0.05 );
		$this->assertEqualsWithDelta( 0.25, $vector[2] + $vector[3], 0.05 );
		$this->assertEqualsWithDelta( 0.25, $vector[4] + $vector[5], 0.05 );
		$this->assertEqualsWithDelta( 0.25, $vector[10] + $vector[11], 0.05 );

		// Fully saturated, fully bright image: saturation and value mass sit
		// in the top bin bar the blended border pixels.
		$this->assertGreaterThan( 0.9, $vector[16 + 7] );
		$this->assertGreaterThan( 0.85, $vector[24 + 7] );
	}

	public function test_hue_ramp_occupies_many_hue_bins(): void {
		$vector = $this->fingerprints( self::hue_ramp() )['color'];

		$occupied = count(
			array_filter(
				array_slice( $vector, 0, 16 ),
				static fn( float $bin ): bool => $bin > 0.01
			)
		);

		$this->assertGreaterThanOrEqual( 6, $occupied );
	}

	public function test_complex_images_are_deterministic(): void {
		$first  = $this->fingerprints( self::checkerboard() );
		$second = $this->fingerprints( self::checkerboard() );

		$this->assertSame( $first['phash'], $second['phash'] );
		$this->assertSame( $first['color'], $second['color'] );
		$this->assertSame( $first['edge'], $second['edge'] );
	}
}
