<?php
/**
 * Integration tests for Snopix\Imaging\GD_Loader.
 *
 * @package Snopix
 */

use Snopix\Imaging\GD_Loader;

/**
 * @covers \Snopix\Imaging\GD_Loader
 */
final class Gd_Loader_Test extends Snopix_Integration_TestCase {

	/**
	 * Loader under test.
	 *
	 * @var GD_Loader
	 */
	private GD_Loader $loader;

	/**
	 * Build a fresh loader before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->loader = new GD_Loader();
	}

	/**
	 * Assert a value is a usable GD image resource across PHP/GD versions.
	 *
	 * @param mixed $resource Value returned by GD_Loader::load().
	 *
	 * @return void
	 */
	private function assert_is_gd_image( $resource ): void {
		$this->assertNotFalse( $resource, 'Expected a GD image, got false.' );
		if ( class_exists( '\GdImage' ) ) {
			$this->assertInstanceOf( \GdImage::class, $resource );
		}
		$this->assertGreaterThan( 0, imagesx( $resource ) );
		$this->assertGreaterThan( 0, imagesy( $resource ) );
	}

	public function test_load_returns_gd_image_for_real_fixture(): void {
		$attachment_id = $this->attach_fixture( 1 );

		$resource = $this->loader->load( $attachment_id );

		$this->assert_is_gd_image( $resource );

		$this->loader->destroy( $resource );
	}

	public function test_load_returns_false_for_nonexistent_attachment(): void {
		$this->assertFalse( $this->loader->load( 999999 ) );
	}

	public function test_load_returns_false_when_file_is_missing(): void {
		$attachment_id = $this->attach_fixture( 2 );

		$file = get_attached_file( $attachment_id );
		$this->assertIsString( $file );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $file );

		$this->assertFalse( $this->loader->load( $attachment_id ) );
	}

	public function test_destroy_frees_resource_without_error(): void {
		$attachment_id = $this->attach_fixture( 3 );
		$resource      = $this->loader->load( $attachment_id );
		$this->assert_is_gd_image( $resource );

		$this->loader->destroy( $resource );

		// A second destroy on a falsey value is a no-op and must not raise.
		$this->loader->destroy( false );

		// assert_is_gd_image() above already asserted the resource was valid;
		// reaching here without error proves destroy() is safe.
		$this->assertTrue( true );
	}

	/**
	 * @dataProvider format_variant_provider
	 *
	 * @param string $variant Committed format-variant suffix.
	 */
	public function test_load_supports_committed_format_variants( string $variant ): void {
		$attachment_id = $this->attach_variant( 1, $variant );

		$resource = $this->loader->load( $attachment_id );

		$this->assert_is_gd_image( $resource );

		$this->loader->destroy( $resource );
	}

	/**
	 * Format variants the loader declares support for.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function format_variant_provider(): array {
		return array(
			'png'  => array( 'png' ),
			'webp' => array( 'webp' ),
			'gif'  => array( 'gif' ),
			'bmp'  => array( 'bmp' ),
		);
	}
}
