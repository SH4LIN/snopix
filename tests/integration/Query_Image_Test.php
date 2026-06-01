<?php
/**
 * Integration tests for Snopix\Search\Query_Image.
 *
 * Exercises the from_upload() rejection path, constant contracts, and the
 * cleanup() pipeline against real fixture images and a live WordPress + DB
 * environment. Every test rolls back its DB changes automatically
 * (WP_UnitTestCase).
 *
 * WHY some tests are skipped:
 * from_upload()'s success path calls wp_handle_upload(), which hardcodes
 * action 'wp_handle_upload' and therefore calls is_uploaded_file() (WP core
 * wp-admin/includes/file.php line 929). is_uploaded_file() always returns
 * false outside a real HTTP POST; there is no filter to bypass this check.
 * Those tests are documented as skipped so the intent is preserved and they
 * can be promoted to E2E tests.
 *
 * @package Snopix
 */

use Snopix\Search\Query_Image;
use Snopix\Hooks\Settings;

/**
 * @covers \Snopix\Search\Query_Image
 */
final class Query_Image_Test extends Snopix_Integration_TestCase {

	/**
	 * System under test.
	 *
	 * @var Query_Image
	 */
	private Query_Image $query_image;

	// -------------------------------------------------------------------------
	// Lifecycle
	// -------------------------------------------------------------------------

	public function set_up(): void {
		parent::set_up();
		$this->query_image = new Query_Image();
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a $_FILES-style array for the size-guard rejection test only.
	 * tmp_name points at the real fixture so the array is structurally valid,
	 * but the size key is overridden to trigger the guard before any disk I/O.
	 *
	 * @param string $src  Absolute source path.
	 * @param int    $size Value to set for the 'size' key.
	 *
	 * @return array<string, mixed>
	 */
	private function build_file_entry_with_size( string $src, int $size ): array {
		$ext = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
		$map = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'bmp'  => 'image/bmp',
		);
		return array(
			'name'     => basename( $src ),
			'type'     => $map[ $ext ] ?? 'application/octet-stream',
			'tmp_name' => $src,
			'error'    => UPLOAD_ERR_OK,
			'size'     => $size,
		);
	}

	// -------------------------------------------------------------------------
	// Happy-path tests — SKIPPED: from_upload() success requires is_uploaded_file()
	// -------------------------------------------------------------------------

	/**
	 * A valid JPEG fixture produces a positive attachment ID.
	 */
	public function test_from_upload_returns_attachment_id_for_valid_jpeg(): void {
		$this->markTestSkipped( 'from_upload() success path requires is_uploaded_file(), unsatisfiable in PHPUnit; covered by E2E.' );
	}

	/**
	 * The created attachment row exists in the DB and carries the probe meta flag.
	 */
	public function test_from_upload_sets_probe_meta_flag(): void {
		$this->markTestSkipped( 'from_upload() success path requires is_uploaded_file(), unsatisfiable in PHPUnit; covered by E2E.' );
	}

	/**
	 * The attachment has the correct MIME type stored.
	 */
	public function test_from_upload_stores_correct_mime_type(): void {
		$this->markTestSkipped( 'from_upload() success path requires is_uploaded_file(), unsatisfiable in PHPUnit; covered by E2E.' );
	}

	/**
	 * A PNG variant is accepted and returns a valid attachment ID.
	 */
	public function test_from_upload_accepts_png_variant(): void {
		$this->markTestSkipped( 'from_upload() success path requires is_uploaded_file(), unsatisfiable in PHPUnit; covered by E2E.' );
	}

	/**
	 * A WebP variant is accepted and returns a valid attachment ID.
	 */
	public function test_from_upload_accepts_webp_variant(): void {
		$this->markTestSkipped( 'from_upload() success path requires is_uploaded_file(), unsatisfiable in PHPUnit; covered by E2E.' );
	}

	/**
	 * Two successive uploads each yield distinct attachment IDs.
	 */
	public function test_from_upload_two_uploads_yield_distinct_ids(): void {
		$this->markTestSkipped( 'from_upload() success path requires is_uploaded_file(), unsatisfiable in PHPUnit; covered by E2E.' );
	}

	// -------------------------------------------------------------------------
	// Rejection: file-size guard (fires BEFORE wp_handle_upload — testable)
	// -------------------------------------------------------------------------

	/**
	 * When the 'size' key exceeds 10 MB the upload is rejected before any disk
	 * I/O — from_upload() returns false immediately.
	 *
	 * The guard reads $file['size'] from the supplied array; it does NOT stat
	 * tmp_name. Passing an inflated size value is therefore sufficient to
	 * exercise the branch without needing a real 10 MB file.
	 */
	public function test_from_upload_rejects_oversized_file(): void {
		$oversized_bytes = 10_485_761; // MAX_FILE_SIZE + 1.
		$entry           = $this->build_file_entry_with_size( self::fixture_path( 1 ), $oversized_bytes );

		$result = $this->query_image->from_upload( $entry );

		$this->assertFalse( $result );
	}

	/**
	 * MAX_FILE_SIZE constant is exactly 10 MB (10 485 760 bytes).
	 */
	public function test_from_upload_size_guard_threshold_is_ten_megabytes(): void {
		$rc  = new ReflectionClass( Query_Image::class );
		$max = $rc->getConstant( 'MAX_FILE_SIZE' );

		$this->assertSame( 10_485_760, $max );
	}

	// -------------------------------------------------------------------------
	// Rejection: decompression-bomb guard — constant contract
	// -------------------------------------------------------------------------

	/**
	 * The MAX_PIXELS constant enforces 16 777 216 (4096 × 4096) as the
	 * decoded-pixel ceiling.
	 */
	public function test_decompression_bomb_constant_is_16_megapixels(): void {
		$rc         = new ReflectionClass( Query_Image::class );
		$max_pixels = $rc->getConstant( 'MAX_PIXELS' );

		$this->assertSame( 16_777_216, $max_pixels );
	}

	/**
	 * A committed 'downscale' variant does not exceed the pixel limit —
	 * verified via reflection only; the real upload path is skipped.
	 */
	public function test_from_upload_normal_image_passes_pixel_check(): void {
		$this->markTestSkipped( 'from_upload() success path requires is_uploaded_file(), unsatisfiable in PHPUnit; covered by E2E.' );
	}

	// -------------------------------------------------------------------------
	// Allowed MIME list
	// -------------------------------------------------------------------------

	/**
	 * The ALLOWED_MIMES constant contains the five expected types and no others.
	 */
	public function test_allowed_mimes_constant_covers_expected_types(): void {
		$rc    = new ReflectionClass( Query_Image::class );
		$mimes = $rc->getConstant( 'ALLOWED_MIMES' );

		$this->assertIsArray( $mimes );
		$this->assertContains( 'image/jpeg', $mimes );
		$this->assertContains( 'image/png', $mimes );
		$this->assertContains( 'image/gif', $mimes );
		$this->assertContains( 'image/webp', $mimes );
		$this->assertContains( 'image/bmp', $mimes );
		$this->assertCount( 5, $mimes );
	}

	// -------------------------------------------------------------------------
	// Cleanup — uses attach_fixture() (real attachment, no from_upload())
	// -------------------------------------------------------------------------

	/**
	 * cleanup() removes the attachment post from the DB.
	 */
	public function test_cleanup_removes_attachment_from_db(): void {
		$id = $this->attach_fixture( 1 );

		// Confirm it exists before cleanup.
		$this->assertNotNull( get_post( $id ) );

		$this->query_image->cleanup( $id );

		// After cleanup the post must be gone (wp_delete_attachment with $force=true).
		$this->assertNull( get_post( $id ) );
	}

	/**
	 * cleanup() removes the uploaded file from disk.
	 */
	public function test_cleanup_removes_file_from_disk(): void {
		$id = $this->attach_fixture( 2 );

		// Record the attached file path before cleanup.
		$attached_file = get_attached_file( $id );
		$this->assertNotFalse( $attached_file );
		$this->assertFileExists( $attached_file );

		$this->query_image->cleanup( $id );

		$this->assertFileDoesNotExist( $attached_file );
	}

	/**
	 * cleanup() is idempotent — calling it twice does not throw or return an error.
	 */
	public function test_cleanup_is_idempotent(): void {
		$id = $this->attach_fixture( 3 );

		$this->query_image->cleanup( $id );
		// Second call must not throw or produce a PHP warning.
		$this->query_image->cleanup( $id );

		$this->assertNull( get_post( $id ) );
	}

	// -------------------------------------------------------------------------
	// Downscale integration — SKIPPED: requires from_upload() success
	// -------------------------------------------------------------------------

	/**
	 * When downscale_max is set and the probe exceeds it, the file on disk is
	 * resized in-place.
	 */
	public function test_from_upload_downscales_oversized_probe(): void {
		$this->markTestSkipped( 'from_upload() success path requires is_uploaded_file(), unsatisfiable in PHPUnit; covered by E2E.' );
	}

	/**
	 * When downscale_max is 0 (disabled) the image is stored as-is.
	 */
	public function test_from_upload_skips_downscale_when_max_is_zero(): void {
		$this->markTestSkipped( 'from_upload() success path requires is_uploaded_file(), unsatisfiable in PHPUnit; covered by E2E.' );
	}

	// -------------------------------------------------------------------------
	// PROBE_META_KEY constant
	// -------------------------------------------------------------------------

	/**
	 * The public PROBE_META_KEY constant has the expected string value.
	 */
	public function test_probe_meta_key_constant_value(): void {
		$this->assertSame( '_snopix_probe', Query_Image::PROBE_META_KEY );
	}
}
