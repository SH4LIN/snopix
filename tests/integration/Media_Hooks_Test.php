<?php
/**
 * Integration tests for Snopix\Hooks\Media_Hooks.
 *
 * Verifies that the add_attachment and delete_attachment hooks registered
 * by Media_Hooks cause the expected index rows to be written / removed.
 *
 * @package Snopix
 */

use Snopix\Hooks\Media_Hooks;
use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Edge_Processor;
use Snopix\Imaging\GD_Loader;
use Snopix\Imaging\PHash_Processor;
use Snopix\Indexing\Image_Indexer;
use Snopix\Indexing\Mime_Validator;
use Snopix\Repository\Index_Repository;
use Snopix\Search\Fingerprint_Factory;
use Snopix\Search\Query_Image;

/**
 * @covers \Snopix\Hooks\Media_Hooks
 */
final class Media_Hooks_Test extends Snopix_Integration_TestCase {

	private Index_Repository $repo;
	private Image_Indexer    $indexer;
	private Media_Hooks      $hooks;

	public function set_up(): void {
		parent::set_up();

		// The full plugin is loaded in bootstrap (snopix.php → Plugin::register()),
		// so a live Media_Hooks instance is already wired to add_attachment and
		// delete_attachment.  Strip those live hooks so only this test controls them.
		remove_all_actions( 'add_attachment' );
		remove_all_actions( 'delete_attachment' );

		global $wpdb;
		$this->repo = new Index_Repository( $wpdb );

		$factory = new Fingerprint_Factory(
			new GD_Loader(),
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);

		$this->indexer = new Image_Indexer( new Mime_Validator(), $factory, $this->repo );
		$this->hooks   = new Media_Hooks( $this->indexer );
	}

	public function tear_down(): void {
		remove_action( 'add_attachment', array( $this->hooks, 'on_upload' ) );
		remove_action( 'delete_attachment', array( $this->hooks, 'on_delete' ) );
		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// on_upload — adds an index row for regular image attachments.
	// -----------------------------------------------------------------------

	/**
	 * Calling on_upload() directly with a real JPEG fixture causes the
	 * attachment to appear in the index.
	 */
	public function test_on_upload_indexes_real_image(): void {
		$attachment_id = $this->attach_fixture( 1 );

		$this->hooks->on_upload( $attachment_id );

		$rows = $this->repo->get_all_indexed();
		$ids  = array_column( $rows, 'attachment_id' );
		$this->assertContains( (string) $attachment_id, $ids, 'Index row should exist after on_upload.' );
	}

	/**
	 * Firing the add_attachment action after register() triggers the same
	 * indexing path as calling on_upload() directly.
	 */
	public function test_add_attachment_action_after_register_indexes_image(): void {
		$this->hooks->register();

		$attachment_id = $this->attach_fixture( 2 );

		// do_action is already fired by wp_insert_attachment inside attach_fixture.
		// Fire it again to confirm the hook is wired and idempotent (upsert).
		do_action( 'add_attachment', $attachment_id );

		$rows = $this->repo->get_all_indexed();
		$ids  = array_column( $rows, 'attachment_id' );
		$this->assertContains( (string) $attachment_id, $ids, 'Index row should exist after add_attachment action.' );
	}

	/**
	 * on_upload() skips attachments that carry the probe meta key, leaving no
	 * index row behind for throwaway search probes.
	 */
	public function test_on_upload_skips_probe_attachments(): void {
		$attachment_id = $this->attach_fixture( 3 );

		// Mark the attachment as a probe (what Query_Image::from_upload does).
		update_post_meta( $attachment_id, Query_Image::PROBE_META_KEY, 1 );

		$this->hooks->on_upload( $attachment_id );

		$rows = $this->repo->get_all_indexed();
		$ids  = array_column( $rows, 'attachment_id' );
		$this->assertNotContains( (string) $attachment_id, $ids, 'Probe attachment must not be indexed.' );
	}

	// -----------------------------------------------------------------------
	// on_delete — removes the index row for a deleted attachment.
	// -----------------------------------------------------------------------

	/**
	 * on_delete() removes an existing index row so the attachment no longer
	 * appears in get_all_indexed().
	 */
	public function test_on_delete_removes_index_row(): void {
		$attachment_id = $this->attach_fixture( 4 );

		// Index it first so there is something to delete.
		$this->hooks->on_upload( $attachment_id );

		$before = $this->repo->get_all_indexed();
		$ids    = array_column( $before, 'attachment_id' );
		$this->assertContains( (string) $attachment_id, $ids, 'Pre-condition: row should exist before delete.' );

		$this->hooks->on_delete( $attachment_id );

		$after    = $this->repo->get_all_indexed();
		$ids_post = array_column( $after, 'attachment_id' );
		$this->assertNotContains( (string) $attachment_id, $ids_post, 'Index row should be gone after on_delete.' );
	}

	/**
	 * Firing the delete_attachment action after register() removes the index row.
	 */
	public function test_delete_attachment_action_after_register_removes_row(): void {
		$this->hooks->register();

		$attachment_id = $this->attach_fixture( 5 );

		// Index via the hook path.
		do_action( 'add_attachment', $attachment_id );

		$before = $this->repo->get_all_indexed();
		$ids    = array_column( $before, 'attachment_id' );
		$this->assertContains( (string) $attachment_id, $ids, 'Pre-condition: row should exist.' );

		do_action( 'delete_attachment', $attachment_id );

		$after    = $this->repo->get_all_indexed();
		$ids_post = array_column( $after, 'attachment_id' );
		$this->assertNotContains( (string) $attachment_id, $ids_post, 'Index row should be gone after delete_attachment action.' );
	}

	/**
	 * on_delete() on a non-indexed attachment is a no-op (no error, row count
	 * remains the same).
	 */
	public function test_on_delete_is_noop_when_row_does_not_exist(): void {
		$attachment_id = $this->attach_fixture( 6 );

		// Do NOT index it — call on_delete against a row that was never written.
		$before_count = count( $this->repo->get_all_indexed() );
		$this->hooks->on_delete( $attachment_id );
		$after_count = count( $this->repo->get_all_indexed() );

		$this->assertSame( $before_count, $after_count, 'Row count should be unchanged when deleting a non-indexed attachment.' );
	}
}
