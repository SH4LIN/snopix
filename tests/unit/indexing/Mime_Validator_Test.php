<?php
/**
 * Tests for Mime_Validator allowed-MIME enforcement.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Indexing\Mime_Validator;

/**
 * Mime_Validator unit tests.
 */
class Pixel_Scout_Mime_Validator_Test extends Pixel_Scout_TestCase {

	private Mime_Validator $validator;

	/**
	 * Build a fresh validator before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->validator = new Mime_Validator();
	}

	/**
	 * JPEG must be allowed.
	 *
	 * @return void
	 */
	public function test_allows_jpeg(): void {
		$this->assertTrue( $this->validator->is_allowed( 'image/jpeg' ) );
	}

	/**
	 * PNG must be allowed.
	 *
	 * @return void
	 */
	public function test_allows_png(): void {
		$this->assertTrue( $this->validator->is_allowed( 'image/png' ) );
	}

	/**
	 * GIF must be allowed.
	 *
	 * @return void
	 */
	public function test_allows_gif(): void {
		$this->assertTrue( $this->validator->is_allowed( 'image/gif' ) );
	}

	/**
	 * WebP must be allowed.
	 *
	 * @return void
	 */
	public function test_allows_webp(): void {
		$this->assertTrue( $this->validator->is_allowed( 'image/webp' ) );
	}

	/**
	 * BMP must be allowed.
	 *
	 * @return void
	 */
	public function test_allows_bmp(): void {
		$this->assertTrue( $this->validator->is_allowed( 'image/bmp' ) );
	}

	/**
	 * SVG is not in the allow-list (vector format, can't be fingerprinted).
	 *
	 * @return void
	 */
	public function test_rejects_svg(): void {
		$this->assertFalse( $this->validator->is_allowed( 'image/svg+xml' ) );
	}

	/**
	 * TIFF/HEIC and other unsupported raster formats must be rejected.
	 *
	 * @return void
	 */
	public function test_rejects_unsupported_raster_formats(): void {
		$this->assertFalse( $this->validator->is_allowed( 'image/tiff' ) );
		$this->assertFalse( $this->validator->is_allowed( 'image/heic' ) );
		$this->assertFalse( $this->validator->is_allowed( 'image/avif' ) );
	}

	/**
	 * Non-image MIME types are rejected.
	 *
	 * @return void
	 */
	public function test_rejects_non_image_mimes(): void {
		$this->assertFalse( $this->validator->is_allowed( 'application/pdf' ) );
		$this->assertFalse( $this->validator->is_allowed( 'video/mp4' ) );
		$this->assertFalse( $this->validator->is_allowed( 'text/plain' ) );
	}

	/**
	 * Empty input must be rejected.
	 *
	 * @return void
	 */
	public function test_rejects_empty_string(): void {
		$this->assertFalse( $this->validator->is_allowed( '' ) );
	}

	/**
	 * Comparison must be strict — case mismatch does not count.
	 *
	 * @return void
	 */
	public function test_rejects_uppercase_variant(): void {
		$this->assertFalse( $this->validator->is_allowed( 'IMAGE/JPEG' ) );
	}

	/**
	 * `get_allowed()` returns the full canonical list of supported MIME types.
	 *
	 * @return void
	 */
	public function test_get_allowed_returns_full_list(): void {
		$allowed = $this->validator->get_allowed();
		$this->assertContains( 'image/jpeg', $allowed );
		$this->assertContains( 'image/png', $allowed );
		$this->assertContains( 'image/gif', $allowed );
		$this->assertContains( 'image/webp', $allowed );
		$this->assertContains( 'image/bmp', $allowed );
	}
}
