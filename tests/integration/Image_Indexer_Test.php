<?php
/**
 * Integration tests for Snopix\Indexing\Image_Indexer.
 *
 * @package Snopix
 */

use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Edge_Processor;
use Snopix\Imaging\GD_Loader;
use Snopix\Imaging\PHash_Processor;
use Snopix\Indexing\Image_Indexer;
use Snopix\Indexing\Mime_Validator;
use Snopix\Repository\Index_Repository;
use Snopix\Search\Fingerprint_Factory;

/**
 * @covers \Snopix\Indexing\Image_Indexer
 */
final class Image_Indexer_Test extends Snopix_Integration_TestCase {

	private Image_Indexer    $indexer;
	private Index_Repository $repo;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$this->repo    = new Index_Repository( $wpdb );
		$factory       = new Fingerprint_Factory(
			new GD_Loader(),
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);
		$this->indexer = new Image_Indexer(
			new Mime_Validator(),
			$factory,
			$this->repo
		);
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	/**
	 * Indexing a real JPEG fixture writes a row that is retrievable via
	 * get_all_indexed() with non-empty phash, color_vector, and edge_vector.
	 */
	public function test_index_single_writes_full_fingerprint_row(): void {
		$attachment_id = $this->attach_fixture( 1 );

		$result = $this->indexer->index_single( $attachment_id );

		$this->assertTrue( $result, 'index_single() must return true for a valid JPEG fixture.' );

		$rows = $this->repo->get_all_indexed();
		$this->assertCount( 1, $rows, 'Exactly one row should exist after indexing one attachment.' );

		$row = $rows[0];
		$this->assertSame( (string) $attachment_id, (string) $row['attachment_id'] );
		$this->assertNotEmpty( $row['phash'], 'phash must be non-empty.' );
		$this->assertNotEmpty( $row['color_vector'], 'color_vector must be non-empty.' );
		$this->assertNotEmpty( $row['edge_vector'], 'edge_vector must be non-empty.' );
	}

	/**
	 * Indexing a real JPEG fixture also persists a non-empty file_hash, visible
	 * via get_all_with_hash().
	 */
	public function test_index_single_writes_non_empty_file_hash(): void {
		$attachment_id = $this->attach_fixture( 2 );

		$this->indexer->index_single( $attachment_id );

		$rows = $this->repo->get_all_with_hash();
		$this->assertCount( 1, $rows, 'One row expected via get_all_with_hash().' );

		$row = $rows[0];
		$this->assertSame( (string) $attachment_id, (string) $row['attachment_id'] );
		$this->assertNotEmpty( $row['file_hash'], 'file_hash must be a non-empty MD5 string.' );
		// MD5 hex strings are always 32 characters.
		$this->assertSame( 32, strlen( $row['file_hash'] ) );
	}

	/**
	 * Re-indexing the same attachment updates the existing row rather than
	 * inserting a duplicate.
	 */
	public function test_reindexing_same_attachment_updates_not_duplicates(): void {
		$attachment_id = $this->attach_fixture( 3 );

		$this->indexer->index_single( $attachment_id );
		$this->assertCount( 1, $this->repo->get_all_indexed() );

		// Second call on the same ID: still exactly one row.
		$result = $this->indexer->index_single( $attachment_id );
		$this->assertTrue( $result );

		$rows = $this->repo->get_all_indexed();
		$this->assertCount( 1, $rows, 'Re-indexing must upsert, not insert a second row.' );
		$this->assertSame( (string) $attachment_id, (string) $rows[0]['attachment_id'] );
	}

	/**
	 * Attaching an attachment with an unsupported MIME type causes
	 * mark_failed('unsupported_mime') - the row has a non-empty error_code and
	 * does not appear in get_all_indexed().
	 */
	public function test_unsupported_mime_produces_failed_row(): void {
		// Register an attachment whose MIME type is not in the allowed list.
		// We insert the post directly so we can force-set an arbitrary MIME type
		// without needing a real file of that format.
		$attachment_id = wp_insert_post(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'fake-pdf',
			)
		);
		$this->assertIsInt( $attachment_id );
		$this->assertGreaterThan( 0, $attachment_id );

		$result = $this->indexer->index_single( $attachment_id );

		$this->assertFalse( $result, 'index_single() must return false for an unsupported MIME.' );

		// Row must NOT appear in the indexed (success) list.
		$this->assertCount( 0, $this->repo->get_all_indexed() );

		// But the raw row must exist with error_code = 'unsupported_mime'.
		global $wpdb;
		$table = $wpdb->prefix . 'snopix_index';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$error_code = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT error_code FROM $table WHERE attachment_id = %d",
				$attachment_id
			)
		);
		$this->assertSame( 'unsupported_mime', $error_code );
	}

	/**
	 * An attachment with an allowed MIME type but a missing/corrupt file cannot
	 * be fingerprinted, so index_single() calls mark_failed('unfingerprintable').
	 */
	public function test_unfingerprintable_attachment_produces_failed_row(): void {
		// Insert an attachment that claims to be a JPEG but points to a
		// non-existent file so GD_Loader::load() returns false.
		$attachment_id = wp_insert_post(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'ghost-jpeg',
			)
		);
		$this->assertIsInt( $attachment_id );
		$this->assertGreaterThan( 0, $attachment_id );

		// Do NOT add attached-file metadata, so get_attached_file() returns false
		// and GD_Loader bails, making generate() return [].

		$result = $this->indexer->index_single( $attachment_id );

		$this->assertFalse( $result, 'index_single() must return false when fingerprint generation fails.' );

		// Must not appear in the success index.
		$this->assertCount( 0, $this->repo->get_all_indexed() );

		// Raw row must record the failure reason.
		global $wpdb;
		$table = $wpdb->prefix . 'snopix_index';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$error_code = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT error_code FROM $table WHERE attachment_id = %d",
				$attachment_id
			)
		);
		$this->assertSame( 'unfingerprintable', $error_code );
	}
}
