<?php
/**
 * Tests for Color_Processor.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Imaging\Color_Processor;

/**
 * Color_Processor tests.
 */
class Pixel_Scout_Color_Processor_Test extends Pixel_Scout_TestCase {

	private Color_Processor $processor;
	private static string $fixtures_dir;

	/**
	 * Resolve the fixture image directory once for the whole class.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		self::$fixtures_dir = dirname( dirname( dirname( __DIR__ ) ) ) . '/fixtures/images';
	}

	/**
	 * Build a fresh Color_Processor instance before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->processor = new Color_Processor();
	}

	/**
	 * Create a square GD resource filled with a single RGB colour.
	 *
	 * @param int $r    Red channel value 0–255.
	 * @param int $g    Green channel value 0–255.
	 * @param int $b    Blue channel value 0–255.
	 * @param int $size Pixel size of one side. Defaults to 100.
	 *
	 * @return \GdImage
	 */
	private function solid_gd( int $r, int $g, int $b, int $size = 100 ) {
		$img = imagecreatetruecolor( $size, $size );
		imagefill( $img, 0, 0, imagecolorallocate( $img, $r, $g, $b ) );
		return $img;
	}

	/**
	 * Whether the optional Picsum fixture images have been downloaded.
	 *
	 * @return bool
	 */
	private function fixtures_available(): bool {
		return file_exists( sprintf( '%s/001.jpg', self::$fixtures_dir ) );
	}

	// ── Output format ─────────────────────────────────────────────────────

	/**
	 * Output array must contain the `color_vector` key.
	 *
	 * @return void
	 */
	public function test_returns_color_vector_key(): void {
		$gd     = $this->solid_gd( 128, 128, 128 );
		$result = $this->processor->process( $gd, 1 );
		imagedestroy( $gd );
		$this->assertArrayHasKey( 'color_vector', $result );
	}

	/**
	 * Color vector must be a flat 48-element list (16 bins × RGB).
	 *
	 * @return void
	 */
	public function test_color_vector_has_48_elements(): void {
		$gd     = $this->solid_gd( 200, 100, 50 );
		$vector = $this->processor->process( $gd, 1 )['color_vector'];
		imagedestroy( $gd );
		$this->assertCount( 48, $vector );
	}

	/**
	 * Every element of the colour vector must be a float (normalised bin frequency).
	 *
	 * @return void
	 */
	public function test_all_values_are_float(): void {
		$gd     = $this->solid_gd( 100, 150, 200 );
		$vector = $this->processor->process( $gd, 1 )['color_vector'];
		imagedestroy( $gd );
		foreach ( $vector as $v ) {
			$this->assertIsFloat( $v );
		}
	}

	// ── Normalisation ─────────────────────────────────────────────────────

	/**
	 * Each per-channel histogram (R, G, B) must sum to exactly 1.0.
	 *
	 * @return void
	 */
	public function test_channel_histograms_sum_to_one(): void {
		$gd     = $this->solid_gd( 200, 100, 50 );
		$vector = $this->processor->process( $gd, 1 )['color_vector'];
		imagedestroy( $gd );

		// 3 channels × 16 bins, each channel must sum to 1.0.
		for ( $c = 0; $c < 3; $c++ ) {
			$channel_sum = array_sum( array_slice( $vector, $c * 16, 16 ) );
			$this->assertEqualsWithDelta( 1.0, $channel_sum, 1e-9, "Channel {$c} does not sum to 1.0" );
		}
	}

	/**
	 * All normalised bin values must lie in [0, 1].
	 *
	 * @return void
	 */
	public function test_all_values_in_range_0_to_1(): void {
		$gd     = $this->solid_gd( 123, 45, 67 );
		$vector = $this->processor->process( $gd, 1 )['color_vector'];
		imagedestroy( $gd );
		foreach ( $vector as $v ) {
			$this->assertGreaterThanOrEqual( 0.0, $v );
			$this->assertLessThanOrEqual( 1.0, $v );
		}
	}

	// ── Semantic correctness ──────────────────────────────────────────────

	/**
	 * A solid red image must peak in the last red bin (value 255 → bin 15) and
	 * concentrate all green/blue mass in bin 0.
	 *
	 * @return void
	 */
	public function test_solid_red_image_peaks_in_red_channel(): void {
		$gd     = $this->solid_gd( 255, 0, 0 );
		$vector = $this->processor->process( $gd, 1 )['color_vector'];
		imagedestroy( $gd );

		// Red channel (bins 0-15) should have a high last bin (255 → bin 15).
		$r_max_bin = array_search( max( array_slice( $vector, 0, 16 ) ), array_slice( $vector, 0, 16 ) );
		$this->assertSame( 15, (int) $r_max_bin );

		// Green channel (bins 16-31) should all be near zero.
		$g_sum = array_sum( array_slice( $vector, 16, 16 ) );
		$this->assertEqualsWithDelta( 1.0, $g_sum, 1e-9 );
		// All green pixels land in bin 0 (value 0).
		$this->assertEqualsWithDelta( 1.0, $vector[16], 1e-9 );
	}

	// ── Determinism ───────────────────────────────────────────────────────

	/**
	 * Processing the same GD resource twice must yield identical vectors.
	 *
	 * @return void
	 */
	public function test_same_image_returns_identical_vector(): void {
		$gd = $this->solid_gd( 75, 150, 225 );
		$v1 = $this->processor->process( $gd, 1 )['color_vector'];
		$v2 = $this->processor->process( $gd, 2 )['color_vector'];
		imagedestroy( $gd );
		$this->assertSame( $v1, $v2 );
	}

	// ── Discriminability ─────────────────────────────────────────────────

	/**
	 * Visually distinct colours must produce distinct colour vectors.
	 *
	 * @return void
	 */
	public function test_red_and_blue_images_have_different_vectors(): void {
		$red  = $this->solid_gd( 255, 0, 0 );
		$blue = $this->solid_gd( 0, 0, 255 );
		$v1   = $this->processor->process( $red, 1 )['color_vector'];
		$v2   = $this->processor->process( $blue, 1 )['color_vector'];
		imagedestroy( $red );
		imagedestroy( $blue );
		$this->assertNotSame( $v1, $v2 );
	}

	// ── Fixture-based ─────────────────────────────────────────────────────

	/**
	 * Sweep the 100-image Picsum fixture set and confirm every produced vector
	 * has the right length and per-channel normalisation. Skips silently when
	 * fixtures have not been downloaded.
	 *
	 * @return void
	 */
	public function test_fixture_images_produce_valid_color_vectors(): void {
		if ( ! $this->fixtures_available() ) {
			$this->markTestSkipped( 'Fixture images not downloaded. Run: composer fixtures' );
		}

		for ( $i = 1; $i <= 100; $i++ ) {
			$path = sprintf( '%s/%03d.jpg', self::$fixtures_dir, $i );
			if ( ! file_exists( $path ) ) {
				continue;
			}
			$gd     = imagecreatefromjpeg( $path );
			$vector = $this->processor->process( $gd, $i )['color_vector'];
			imagedestroy( $gd );

			$this->assertCount( 48, $vector, "Image #{$i}: wrong vector length" );
			for ( $c = 0; $c < 3; $c++ ) {
				$sum = array_sum( array_slice( $vector, $c * 16, 16 ) );
				$this->assertEqualsWithDelta( 1.0, $sum, 1e-6, "Image #{$i} channel {$c} does not sum to 1" );
			}
		}
	}
}
