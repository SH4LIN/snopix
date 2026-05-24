<?php
/**
 * Integration test: real attachment, real subsize regen.
 *
 * @package Pixel_Scout
 */

require_once __DIR__ . '/class-fixture-helper.php';

use PixelScout\Imaging\Image_Subsize_Service;
use PixelScout\Imaging\Subsize_Regenerator;
use PixelScout\Imaging\Subsize_Watcher;
use PixelScout\Indexing\Index_Progress;
use PixelScout\Infrastructure\Action_Scheduler;

/**
 * Integration tests: regen-missing, regen-all ack, acknowledge dismiss.
 *
 * Extends Pixel_Scout_Integration_TestCase (defined in class-fixture-helper.php)
 * to gain access to attach_fixture() which copies real fixture JPEGs from
 * tests/fixtures/images/ into the WP uploads dir and registers them as
 * attachments. The plan referenced a static Pixel_Scout_Fixture_Helper helper
 * that does not exist — we use $this->attach_fixture(1) instead.
 */
class Pixel_Scout_Subsize_Regen_Integration_Test extends Pixel_Scout_Integration_TestCase {

	public function setUp(): void {
		parent::setUp();
		delete_option( Subsize_Watcher::OPTION_KEY );
		delete_transient( Subsize_Regenerator::PENDING_KEY );
		( new Index_Progress( 'ps_regen_progress_state' ) )->reset();
	}

	public function tearDown(): void {
		remove_image_size( 'ps_integration_new' );
		delete_option( Subsize_Watcher::OPTION_KEY );
		delete_transient( Subsize_Regenerator::PENDING_KEY );
		( new Index_Progress( 'ps_regen_progress_state' ) )->reset();
		parent::tearDown();
	}

	/**
	 * Build a Subsize_Regenerator scoped to a fixed set of attachment IDs.
	 *
	 * @param array<int> $ids Attachment IDs the regenerator will iterate over.
	 *
	 * @return Subsize_Regenerator
	 */
	private function regen_for( array $ids ): Subsize_Regenerator {
		return new Subsize_Regenerator(
			new Image_Subsize_Service(),
			new Subsize_Watcher(),
			new Index_Progress( 'ps_regen_progress_state' ),
			new Action_Scheduler(),
			static fn(): int => count( $ids ),
			static function ( int $offset, int $size ) use ( $ids ): array {
				return array_slice( $ids, $offset, $size );
			}
		);
	}

	/**
	 * Registering a new image size and running regen-missing creates the
	 * subsize file on disk without updating the snapshot (so has_changes
	 * remains true after the regen).
	 *
	 * @return void
	 */
	public function test_new_size_is_filled_by_regen_missing(): void {
		$watcher = new Subsize_Watcher();
		$watcher->diff(); // Seed snapshot from current registered sizes.

		// Create a real attachment using fixture #1 (001.jpg).
		$att_id = $this->attach_fixture( 1 );
		$this->assertIsInt( $att_id );

		add_image_size( 'ps_integration_new', 64, 64, true );
		$this->assertContains( 'ps_integration_new', $watcher->diff()['new'] );

		$regen = $this->regen_for( array( $att_id ) );
		$this->assertSame( 1, $regen->schedule_missing() );
		$regen->process_batch();

		$meta = wp_get_attachment_metadata( $att_id );
		$this->assertArrayHasKey( 'ps_integration_new', $meta['sizes'] );
		$dir = dirname( get_attached_file( $att_id ) );
		$this->assertFileExists( $dir . '/' . $meta['sizes']['ps_integration_new']['file'] );

		// regen-missing must NOT acknowledge the snapshot.
		$this->assertTrue( $watcher->diff()['has_changes'] );
	}

	/**
	 * Running regen-all acknowledges the snapshot so has_changes becomes false.
	 *
	 * @return void
	 */
	public function test_regen_all_acknowledges_snapshot(): void {
		$watcher = new Subsize_Watcher();
		$watcher->diff(); // Seed snapshot.

		$att_id = $this->attach_fixture( 1 );
		add_image_size( 'ps_integration_new', 64, 64, true );
		$this->assertTrue( $watcher->diff()['has_changes'] );

		$regen = $this->regen_for( array( $att_id ) );
		$regen->schedule_all();
		$regen->process_batch();

		$this->assertFalse( $watcher->diff()['has_changes'] );
	}

	/**
	 * Calling acknowledge() clears has_changes immediately without regenerating
	 * any subsize — the escape hatch for removed-only diffs.
	 *
	 * @return void
	 */
	public function test_acknowledge_clears_has_changes_without_regen(): void {
		$watcher = new Subsize_Watcher();
		$watcher->diff(); // Seed snapshot.

		add_image_size( 'ps_integration_new', 64, 64, true );
		$this->assertTrue( $watcher->diff()['has_changes'] );

		$this->assertTrue( $watcher->acknowledge() );
		$this->assertFalse( $watcher->diff()['has_changes'] );
	}
}
