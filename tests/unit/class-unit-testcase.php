<?php
/**
 * Base test case for pure unit tests (no WordPress).
 *
 * @package Snopix
 */

use PHPUnit\Framework\TestCase;

/**
 * Base class for Snopix unit tests. Provides fixture-image helpers backed by
 * the committed JPEGs in tests/fixtures/images and their variations.
 */
abstract class Snopix_Unit_TestCase extends TestCase {

	/**
	 * Absolute path to the fixture images directory.
	 *
	 * @return string
	 */
	protected static function fixtures_dir(): string {
		return dirname( __DIR__ ) . '/fixtures/images';
	}

	/**
	 * Resolve a base fixture JPEG path by 1-based index (1-25).
	 *
	 * @param int $id Fixture index.
	 *
	 * @return string
	 */
	protected static function fixture_path( int $id ): string {
		return sprintf( '%s/%03d.jpg', self::fixtures_dir(), $id );
	}

	/**
	 * Resolve a variation fixture path, e.g. variant 'blur' or 'png'.
	 *
	 * Filenames are either `001_blur.jpg` (transform variants) or `001.png`
	 * (format variants), so the extension is supplied explicitly.
	 *
	 * @param int    $id      Fixture index.
	 * @param string $variant Variant suffix (e.g. 'blur', 'compressed', 'png').
	 * @param string $ext     File extension without dot. Defaults to 'jpg'.
	 *
	 * @return string
	 */
	protected static function variation_path( int $id, string $variant, string $ext = 'jpg' ): string {
		$dir = self::fixtures_dir() . '/variations';
		// Format variants drop the underscore (001.png); transform variants keep it (001_blur.jpg).
		if ( in_array( $variant, array( 'png', 'webp', 'gif', 'bmp' ), true ) ) {
			return sprintf( '%s/%03d.%s', $dir, $id, $variant );
		}
		return sprintf( '%s/%03d_%s.%s', $dir, $id, $variant, $ext );
	}

	/**
	 * Load a fixture JPEG into a GD image resource.
	 *
	 * @param int $id Fixture index.
	 *
	 * @return \GdImage
	 */
	protected static function gd_from_fixture( int $id ): \GdImage {
		$path = self::fixture_path( $id );
		self::assertFileExists( $path, "Fixture {$id} missing: {$path}" );
		$gd = imagecreatefromjpeg( $path );
		self::assertInstanceOf( \GdImage::class, $gd, "Could not decode fixture {$id}" );
		return $gd;
	}

	/**
	 * Load any image file into a GD resource using the format-appropriate decoder.
	 *
	 * @param string $path Absolute image path.
	 *
	 * @return \GdImage
	 */
	protected static function gd_from_path( string $path ): \GdImage {
		self::assertFileExists( $path, "Image missing: {$path}" );
		$gd = imagecreatefromstring( (string) file_get_contents( $path ) );
		self::assertInstanceOf( \GdImage::class, $gd, "Could not decode image: {$path}" );
		return $gd;
	}
}
