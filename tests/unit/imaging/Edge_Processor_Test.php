<?php
/**
 * Tests for Edge_Processor.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Imaging\Edge_Processor;

/**
 * Edge_Processor tests.
 */
class Pixel_Scout_Edge_Processor_Test extends Pixel_Scout_TestCase {

	private Edge_Processor $processor;
	private static string $fixtures_dir;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		self::$fixtures_dir = dirname( dirname( dirname( __DIR__ ) ) ) . '/fixtures/images';
	}

	public function setUp(): void {
		parent::setUp();
		$this->processor = new Edge_Processor();
	}

	private function solid_gd( int $r, int $g, int $b, int $size = 100 ) {
		$img = imagecreatetruecolor( $size, $size );
		imagefill( $img, 0, 0, imagecolorallocate( $img, $r, $g, $b ) );
		return $img;
	}

	private function high_contrast_gd( int $size = 100 ) {
		$img = imagecreatetruecolor( $size, $size );
		$w   = imagecolorallocate( $img, 255, 255, 255 );
		$k   = imagecolorallocate( $img, 0, 0, 0 );
		for ( $x = 0; $x < $size; $x++ ) {
			for ( $y = 0; $y < $size; $y++ ) {
				imagesetpixel( $img, $x, $y, ( ( $x + $y ) % 2 === 0 ) ? $w : $k );
			}
		}
		return $img;
	}

	private function fixtures_available(): bool {
		return file_exists( sprintf( '%s/001.jpg', self::$fixtures_dir ) );
	}

	// ── Output format ─────────────────────────────────────────────────────

	public function test_returns_edge_vector_key(): void {
		$gd     = $this->solid_gd( 128, 128, 128 );
		$result = $this->processor->process( $gd, 1 );
		imagedestroy( $gd );
		$this->assertArrayHasKey( 'edge_vector', $result );
	}

	public function test_edge_vector_has_32_elements(): void {
		$gd     = $this->solid_gd( 200, 100, 50 );
		$vector = $this->processor->process( $gd, 1 )['edge_vector'];
		imagedestroy( $gd );
		$this->assertCount( 32, $vector );
	}

	public function test_all_values_are_float(): void {
		$gd     = $this->solid_gd( 100, 150, 200 );
		$vector = $this->processor->process( $gd, 1 )['edge_vector'];
		imagedestroy( $gd );
		foreach ( $vector as $v ) {
			$this->assertIsFloat( $v );
		}
	}

	// ── Normalisation ─────────────────────────────────────────────────────

	public function test_values_in_range_0_to_1(): void {
		$gd     = $this->high_contrast_gd();
		$vector = $this->processor->process( $gd, 1 )['edge_vector'];
		imagedestroy( $gd );
		foreach ( $vector as $v ) {
			$this->assertGreaterThanOrEqual( 0.0, $v );
			$this->assertLessThanOrEqual( 1.0, $v );
		}
	}

	// ── Semantic correctness ──────────────────────────────────────────────

	public function test_solid_image_has_near_zero_edges(): void {
		$gd     = $this->solid_gd( 200, 200, 200 );
		$vector = $this->processor->process( $gd, 1 )['edge_vector'];
		imagedestroy( $gd );
		// Solid image → Sobel produces zeros everywhere (interior) or no change.
		$sum = array_sum( $vector );
		$this->assertEqualsWithDelta( 0.0, $sum, 1e-6 );
	}

	public function test_high_contrast_image_has_higher_edges_than_solid(): void {
		$solid   = $this->solid_gd( 128, 128, 128 );
		$checker = $this->high_contrast_gd();
		$v_solid   = $this->processor->process( $solid, 1 )['edge_vector'];
		$v_checker = $this->processor->process( $checker, 1 )['edge_vector'];
		imagedestroy( $solid );
		imagedestroy( $checker );
		$this->assertGreaterThan( array_sum( $v_solid ), array_sum( $v_checker ) );
	}

	// ── Determinism ───────────────────────────────────────────────────────

	public function test_same_image_returns_identical_vector(): void {
		$gd = $this->high_contrast_gd();
		$v1 = $this->processor->process( $gd, 1 )['edge_vector'];
		$v2 = $this->processor->process( $gd, 2 )['edge_vector'];
		imagedestroy( $gd );
		$this->assertSame( $v1, $v2 );
	}

	// ── Fixture-based ─────────────────────────────────────────────────────

	public function test_fixture_images_produce_valid_edge_vectors(): void {
		if ( ! $this->fixtures_available() ) {
			$this->markTestSkipped( 'Fixture images not downloaded. Run: composer fixtures' );
		}

		for ( $i = 1; $i <= 100; $i++ ) {
			$path = sprintf( '%s/%03d.jpg', self::$fixtures_dir, $i );
			if ( ! file_exists( $path ) ) {
				continue;
			}
			$gd     = imagecreatefromjpeg( $path );
			$vector = $this->processor->process( $gd, $i )['edge_vector'];
			imagedestroy( $gd );

			$this->assertCount( 32, $vector, "Image #{$i}: wrong vector length" );
			foreach ( $vector as $v ) {
				$this->assertGreaterThanOrEqual( 0.0, $v, "Image #{$i}: negative edge value" );
				$this->assertLessThanOrEqual( 1.0, $v, "Image #{$i}: edge value > 1.0" );
			}
		}
	}
}
