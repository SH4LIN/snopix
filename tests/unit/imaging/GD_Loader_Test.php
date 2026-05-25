<?php
/**
 * Tests for GD_Loader attachment-to-resource conversion.
 *
 * @package Snopix
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use Snopix\Imaging\GD_Loader;

/**
 * GD_Loader unit tests.
 */
class Snopix_GD_Loader_Test extends Snopix_TestCase {

	private GD_Loader $loader;

	/**
	 * Build a fresh loader before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->loader = new GD_Loader();
	}

	/**
	 * Write a small JPEG to disk and register it as an attachment so the loader
	 * has something to load.
	 *
	 * @return array{0: int, 1: string} Attachment ID and absolute path.
	 */
	private function make_real_jpeg_attachment(): array {
		$path = wp_tempnam( 'gd-loader-test.jpg' );
		$gd   = imagecreatetruecolor( 32, 32 );
		imagefill( $gd, 0, 0, imagecolorallocate( $gd, 200, 100, 50 ) );
		imagejpeg( $gd, $path );
		imagedestroy( $gd );

		$attachment_id = (int) self::factory()->attachment->create(
			array(
				'file'           => $path,
				'post_mime_type' => 'image/jpeg',
			)
		);
		update_attached_file( $attachment_id, $path );

		return array( $attachment_id, $path );
	}

	/**
	 * A nonexistent attachment ID must return false.
	 *
	 * @return void
	 */
	public function test_returns_false_for_missing_attachment(): void {
		$this->assertFalse( $this->loader->load( 999999 ) );
	}

	/**
	 * Loading a real JPEG must return a GD resource.
	 *
	 * @return void
	 */
	public function test_loads_jpeg_into_gd_resource(): void {
		list( $id, $path ) = $this->make_real_jpeg_attachment();

		$gd = $this->loader->load( $id );

		$this->assertNotFalse( $gd );
		$this->assertInstanceOf( \GdImage::class, $gd );
		$this->assertSame( 32, imagesx( $gd ) );
		$this->assertSame( 32, imagesy( $gd ) );

		$this->loader->destroy( $gd );
		@unlink( $path );
	}

	/**
	 * `destroy` is safe to call on null without errors.
	 *
	 * @return void
	 */
	public function test_destroy_handles_null_silently(): void {
		$this->loader->destroy( null );
		$this->assertTrue( true );
	}

	/**
	 * Attachment whose file no longer exists must return false.
	 *
	 * @return void
	 */
	public function test_returns_false_when_file_missing_on_disk(): void {
		list( $id, $path ) = $this->make_real_jpeg_attachment();
		@unlink( $path );

		$this->assertFalse( $this->loader->load( $id ) );
	}
}
