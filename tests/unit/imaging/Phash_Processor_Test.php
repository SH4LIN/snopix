<?php
/**
 * Tests for PHash_Processor.
 *
 * Programmatic GD resources are used for pure unit tests.
 * Fixture images (tests/fixtures/images/) are used for integration-style tests
 * and are skipped if not yet downloaded (run: composer fixtures).
 *
 * @package Snopix
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use Snopix\Imaging\PHash_Processor;
use Snopix\Imaging\Similarity;

/**
 * PHash_Processor tests.
 */
class Snopix_PHash_Processor_Test extends Snopix_TestCase {

	private PHash_Processor $processor;
	private Similarity $sim;

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
	 * Build a fresh PHash_Processor and Similarity helper before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->processor = new PHash_Processor();
		$this->sim       = new Similarity();
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	/**
	 * Create a rectangular GD resource filled with a single RGB colour.
	 *
	 * @param int $r Red channel value 0–255.
	 * @param int $g Green channel value 0–255.
	 * @param int $b Blue channel value 0–255.
	 * @param int $w Image width in pixels.
	 * @param int $h Image height in pixels.
	 *
	 * @return \GdImage
	 */
	private function solid_gd( int $r, int $g, int $b, int $w = 100, int $h = 100 ) {
		$img = imagecreatetruecolor( $w, $h );
		imagefill( $img, 0, 0, imagecolorallocate( $img, $r, $g, $b ) );
		return $img;
	}

	/**
	 * Generate a 1-pixel black/white checkerboard GD resource for maximum entropy.
	 *
	 * @param int $size Pixel size of one side. Defaults to 64.
	 *
	 * @return \GdImage
	 */
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

	/**
	 * Load a fixture image by Picsum ID (1–100) into a GD resource.
	 *
	 * @param int $id Fixture image index (1–100).
	 *
	 * @return \GdImage|false GD resource on success, false if the fixture is missing.
	 */
	private function load_fixture( int $id ) {
		$path = sprintf( '%s/%03d.jpg', self::$fixtures_dir, $id );
		if ( ! file_exists( $path ) ) {
			return false;
		}
		return imagecreatefromjpeg( $path );
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
	 * Output array must contain the `phash` key.
	 *
	 * @return void
	 */
	public function test_returns_phash_key(): void {
		$gd     = $this->solid_gd( 128, 128, 128 );
		$result = $this->processor->process( $gd, 1 );
		imagedestroy( $gd );
		$this->assertArrayHasKey( 'phash', $result );
	}

	/**
	 * pHash output must be a 16-character lower-case hex string (64 bits).
	 *
	 * @return void
	 */
	public function test_phash_is_16_char_hex_string(): void {
		$gd   = $this->solid_gd( 200, 100, 50 );
		$hash = $this->processor->process( $gd, 1 )['phash'];
		imagedestroy( $gd );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{16}$/', $hash );
	}

	// ── Determinism ───────────────────────────────────────────────────────

	/**
	 * Hashing the same image twice must yield identical pHashes.
	 *
	 * @return void
	 */
	public function test_same_image_produces_identical_hash(): void {
		$gd    = $this->solid_gd( 100, 150, 200 );
		$hash1 = $this->processor->process( $gd, 1 )['phash'];
		$hash2 = $this->processor->process( $gd, 2 )['phash'];
		imagedestroy( $gd );
		$this->assertSame( $hash1, $hash2 );
	}

	// ── Discriminability ─────────────────────────────────────────────────

	/**
	 * Visually distinct images must produce distinct pHashes.
	 *
	 * @return void
	 */
	public function test_different_images_produce_different_hashes(): void {
		$red   = $this->solid_gd( 255, 0, 0 );
		$blue  = $this->solid_gd( 0, 0, 255 );
		$hash1 = $this->processor->process( $red, 1 )['phash'];
		$hash2 = $this->processor->process( $blue, 1 )['phash'];
		imagedestroy( $red );
		imagedestroy( $blue );
		$this->assertNotSame( $hash1, $hash2 );
	}

	/**
	 * A flat image vs a checkerboard must differ by more than 8 bits of Hamming
	 * distance — i.e. the two are clearly perceived as different by pHash.
	 *
	 * @return void
	 */
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

	/**
	 * Every fixture image must hash to the 16-char hex format. Skipped if
	 * fixtures are not present.
	 *
	 * @return void
	 */
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

	/**
	 * Re-loading and re-hashing the same fixture file must produce the same
	 * pHash both times. Skipped if fixtures are not present.
	 *
	 * @return void
	 */
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

	/**
	 * Sanity check: across the first 20 fixture images, at least half of all
	 * pairs should have Hamming distance > 5. Guards against a regression that
	 * collapses unrelated images into identical hashes.
	 *
	 * @return void
	 */
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
