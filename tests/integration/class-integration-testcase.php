<?php
/**
 * Base test case for integration tests.
 *
 * Boots on top of WordPress' WP_UnitTestCase (real WP + DB, transaction-rolled
 * back per test) and adds helpers that copy the committed fixture images into
 * the uploads dir and register them as real attachments, so the full PHP
 * pipeline (loader → processors → repository → search/duplicates) can run
 * against genuine media.
 *
 * @package Snopix
 */

/**
 * Shared fixture-attachment helpers for Snopix integration tests.
 */
abstract class Snopix_Integration_TestCase extends WP_UnitTestCase {

	/**
	 * Extension → MIME map for the committed fixtures and their format variants.
	 *
	 * @var array<string, string>
	 */
	private const MIME_BY_EXT = array(
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'webp' => 'image/webp',
		'gif'  => 'image/gif',
		'bmp'  => 'image/bmp',
	);

	/**
	 * Absolute path to the committed fixture image directory.
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
	 * Resolve a committed variant path (e.g. 'blur'/'compressed' → 001_blur.jpg,
	 * or format variants 'png'/'webp'/'gif'/'bmp' → 001.png).
	 *
	 * @param int    $id      Fixture index.
	 * @param string $variant Variant suffix.
	 *
	 * @return string
	 */
	protected static function variation_path( int $id, string $variant ): string {
		$dir = self::fixtures_dir() . '/variations';
		if ( in_array( $variant, array( 'png', 'webp', 'gif', 'bmp' ), true ) ) {
			return sprintf( '%s/%03d.%s', $dir, $id, $variant );
		}
		return sprintf( '%s/%03d_%s.jpg', $dir, $id, $variant );
	}

	/**
	 * Copy a file into the uploads dir and register it as an attachment with
	 * generated metadata. The uploaded file is cleaned up by WP_UnitTestCase's
	 * upload teardown.
	 *
	 * @param string $src Absolute source image path.
	 *
	 * @return int Attachment ID.
	 */
	protected function attach_file( string $src ): int {
		$this->assertFileExists( $src, "Fixture missing: {$src}" );

		$ext  = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
		$mime = self::MIME_BY_EXT[ $ext ] ?? 'application/octet-stream';

		$uploads = wp_upload_dir();
		$dest    = trailingslashit( $uploads['path'] ) . wp_unique_filename( $uploads['path'], basename( $src ) );
		copy( $src, $dest );

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => basename( $src ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$dest
		);

		$this->assertIsInt( $attachment_id );
		$this->assertGreaterThan( 0, $attachment_id );

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $dest )
		);

		return (int) $attachment_id;
	}

	/**
	 * Attach a base fixture image.
	 *
	 * @param int $id Fixture index 1-25.
	 *
	 * @return int Attachment ID.
	 */
	protected function attach_fixture( int $id ): int {
		return $this->attach_file( self::fixture_path( $id ) );
	}

	/**
	 * Attach a committed variant of a fixture.
	 *
	 * @param int    $id      Fixture index.
	 * @param string $variant Variant suffix (blur, compressed, downscale, png, ...).
	 *
	 * @return int Attachment ID.
	 */
	protected function attach_variant( int $id, string $variant ): int {
		return $this->attach_file( self::variation_path( $id, $variant ) );
	}

	/**
	 * Attach the same fixture bytes twice - two attachments, identical content.
	 * Used for exact-duplicate (file_hash) detection.
	 *
	 * @param int $id Fixture index.
	 *
	 * @return array{0: int, 1: int} Attachment IDs.
	 */
	protected function attach_fixture_twice( int $id ): array {
		return array( $this->attach_fixture( $id ), $this->attach_fixture( $id ) );
	}
}
