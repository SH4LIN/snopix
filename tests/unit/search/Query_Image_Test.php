<?php
/**
 * Tests for Query_Image upload + cleanup.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Search\Query_Image;

/**
 * Query_Image unit tests.
 */
class Pixel_Scout_Query_Image_Test extends Pixel_Scout_TestCase {

	private Query_Image $query_image;

	/**
	 * Build a fresh handler before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->query_image = new Query_Image();
	}

	/**
	 * Write a small JPEG and produce a $_FILES-style array pointing at it.
	 *
	 * @return array<string, mixed>
	 */
	private function make_file_array_jpeg(): array {
		$tmp = wp_tempnam( 'query-image-test.jpg' );
		$gd  = imagecreatetruecolor( 32, 32 );
		imagefill( $gd, 0, 0, imagecolorallocate( $gd, 50, 150, 250 ) );
		imagejpeg( $gd, $tmp );
		imagedestroy( $gd );

		return array(
			'name'     => 'query-image-test.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => filesize( $tmp ),
		);
	}

	/**
	 * Reject files that exceed the 10 MB upper bound.
	 *
	 * @return void
	 */
	public function test_rejects_files_over_size_limit(): void {
		$file = array(
			'name'     => 'big.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/missing',
			'error'    => 0,
			'size'     => 11 * 1024 * 1024,
		);

		$this->assertFalse( $this->query_image->from_upload( $file ) );
	}

	/**
	 * Reject files with disallowed extensions / MIME types.
	 *
	 * @return void
	 */
	public function test_rejects_unsupported_extension(): void {
		$tmp = wp_tempnam( 'query-image-test.pdf' );
		file_put_contents( $tmp, '%PDF-1.4 dummy' );

		$file = array(
			'name'     => 'doc.pdf',
			'type'     => 'application/pdf',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => filesize( $tmp ),
		);

		$this->assertFalse( $this->query_image->from_upload( $file ) );
		@unlink( $tmp );
	}

	/**
	 * `cleanup` removes the attachment from the database.
	 *
	 * @return void
	 */
	public function test_cleanup_deletes_attachment(): void {
		$id = (int) self::factory()->attachment->create(
			array( 'post_mime_type' => 'image/jpeg' )
		);
		$this->query_image->cleanup( $id );
		$this->assertNull( get_post( $id ) );
	}
}
