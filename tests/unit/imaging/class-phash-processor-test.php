<?php
/**
 * Tests for PHash_Processor.
 *
 * Programmatic GD resources are used for pure unit tests.
 * Fixture images (tests/fixtures/images/) are used for integration-style tests
 * and are skipped if not yet downloaded (run: composer fixtures).
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Imaging\PHash_Processor;
use PixelScout\Imaging\Similarity;

/**
 * PHash_Processor tests.
 */
class Pixel_Scout_PHash_Processor_Test extends Pixel_Scout_TestCase {

	private PHash_Processor $processor;
	private Similarity $sim;

	private static string $fixtures_dir;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		self::$fixtures_dir = dirname( dirname( dirname( __DIR__ ) ) ) . '/fixtures/images';
	}

	public function setUp(): void {
		parent::setUp();
		$this->processor = new PHash_Processor();
		$this->sim       = new Similarity();
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	private function solid_gd( int $r, int $g, int $b, int $w = 100, int $h = 100 ) {
		$img = imagecreatetruecolor( $w, $h );
		imagefill( $img, 0, 0, imagecolorallocate( $img, $r, $g, $b ) );
		return $img;
	}

	private function checkerboard_gd( int $size = 64 ) {
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

	private function load_fixture( int $id ) {
		$path = sprintf( '%s/%03d.jpg', self::$fixtures_dir, $id );
		if ( ! file_exists( $path ) ) {
			return false;
		}
		return imagecreatefromjpeg( $path );
	}

	private function fixtures_available(): bool {
		return file_exists( sprintf( '%s/001.jpg', self::$fixtures_dir ) );
	}

	// ── Output format ─────────────────────────────────────────────────────

	public function test_returns_phash_key(): void {
		$gd     = $this->solid_gd( 128, 128, 128 );
		$result = $this->processor->process( $gd, 1 );
		imagedestroy( $gd );
		$this->assertArrayHasKey( 'phash', $result );
	}

	public function test_phash_is_16_char_hex_string(): void {
		$gd   = $this->solid_gd( 200, 100, 50 );
		$hash = $this->processor->process( $gd, 1 )['phash'];
		imagedestroy( $gd );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{16}$/', $hash );
	}

	// ── Determinism ───────────────────────────────────────────────────────

	public function test_same_image_produces_identical_hash(): void {
		$gd    = $this->solid_gd( 100, 150, 200 );
		$hash1 = $this->processor->process( $gd, 1 )['phash'];
		$hash2 = $this->processor->process( $gd, 2 )['phash'];
		imagedestroy( $gd );
		$this->assertSame( $hash1, $hash2 );
	}

	// ── Discriminability ─────────────────────────────────────────────────

	public function test_different_images_produce_different_hashes(): void {
		$red   = $this->solid_gd( 255, 0, 0 );
		$blue  = $this->solid_gd( 0, 0, 255 );
		$hash1 = $this->processor->process( $red, 1 )['phash'];
		$hash2 = $this->processor->process( $blue, 1 )['phash'];
		imagedestroy( $red );
		imagedestroy( $blue );
		$this->assertNotSame( $hash1, $hash2 );
	}

	public function test_solid_vs_checkerboard_high_hamming(): void {
		$solid   = $this->solid_gd( 128, 128, 128 );
		$checker = $this->checkerboard_gd();
		$h1      = $this->processor->process( $solid, 1 )['phash'];
		$h2      = $this->processor->process( $checker, 1 )['phash'];
		imagedestroy( $solid );
		imagedestroy( $checker );
		// Structurally very different — Hamming must be > 8.
		$this->assertGreaterThan( 8, $this->sim->hamming_distance( $h1, $h2 ) );
	}

	// ── Fixture-based tests (skipped if not downloaded) ───────────────────

	public function test_fixture_images_produce_valid_hashes(): void {
		if ( ! $this->fixtures_available() ) {
			$this->markTestSkipped( 'Fixture images not downloaded. Run: composer fixtures' );
		}

		for ( $i = 1; $i <= 100; $i++ ) {
			$gd = $this->load_fixture( $i );
			if ( false === $gd ) {
				continue;
			}
			$hash = $this->processor->process( $gd, $i )['phash'];
			imagedestroy( $gd );
			$this->assertMatchesRegularExpression(
				'/^[0-9a-f]{16}$/',
				$hash,
				"Image #{$i} produced invalid hash"
			);
		}
	}

	public function test_same_fixture_image_produces_identical_hash(): void {
		if ( ! $this->fixtures_available() ) {
			$this->markTestSkipped( 'Fixture images not downloaded. Run: composer fixtures' );
		}

		$gd1   = $this->load_fixture( 1 );
		$gd2   = $this->load_fixture( 1 );
		$hash1 = $this->processor->process( $gd1, 1 )['phash'];
		$hash2 = $this->processor->process( $gd2, 1 )['phash'];
		imagedestroy( $gd1 );
		imagedestroy( $gd2 );
		$this->assertSame( $hash1, $hash2 );
	}

	public function test_fixture_images_are_pairwise_discriminable(): void {
		if ( ! $this->fixtures_available() ) {
			$this->markTestSkipped( 'Fixture images not downloaded. Run: composer fixtures' );
		}

		$hashes = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$gd = $this->load_fixture( $i );
			if ( false === $gd ) {
				continue;
			}
			$hashes[ $i ] = $this->processor->process( $gd, $i )['phash'];
			imagedestroy( $gd );
		}

		// At least half of distinct pairs should have Hamming > 5.
		$discriminable = 0;
		$total         = 0;
		$keys          = array_keys( $hashes );
		for ( $a = 0; $a < count( $keys ) - 1; $a++ ) {
			for ( $b = $a + 1; $b < count( $keys ); $b++ ) {
				++$total;
				if ( $this->sim->hamming_distance( $hashes[ $keys[ $a ] ], $hashes[ $keys[ $b ] ] ) > 5 ) {
					++$discriminable;
				}
			}
		}

		if ( $total > 0 ) {
			$this->assertGreaterThan( $total * 0.5, $discriminable, 'pHash is not discriminating images well' );
		}
	}
}
