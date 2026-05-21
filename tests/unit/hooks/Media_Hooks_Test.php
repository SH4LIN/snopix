<?php
/**
 * Tests for Media_Hooks attachment lifecycle wiring.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Hooks\Media_Hooks;
use PixelScout\Indexing\Image_Indexer;

/**
 * Media_Hooks unit tests.
 */
class Pixel_Scout_Media_Hooks_Test extends Pixel_Scout_TestCase {

	/**
	 * `register` attaches handlers to add_attachment and delete_attachment.
	 *
	 * @return void
	 */
	public function test_register_adds_attachment_listeners(): void {
		$hooks = new Media_Hooks( $this->createMock( Image_Indexer::class ) );
		$hooks->register();

		$this->assertNotFalse( has_action( 'add_attachment', array( $hooks, 'on_upload' ) ) );
		$this->assertNotFalse( has_action( 'delete_attachment', array( $hooks, 'on_delete' ) ) );

		remove_action( 'add_attachment', array( $hooks, 'on_upload' ) );
		remove_action( 'delete_attachment', array( $hooks, 'on_delete' ) );
	}

	/**
	 * `on_upload` delegates to the indexer.
	 *
	 * @return void
	 */
	public function test_on_upload_delegates_to_indexer(): void {
		$indexer = $this->createMock( Image_Indexer::class );
		$indexer->expects( $this->once() )->method( 'index_single' )->with( 42 );

		( new Media_Hooks( $indexer ) )->on_upload( 42 );
	}

	/**
	 * `on_delete` delegates to the indexer.
	 *
	 * @return void
	 */
	public function test_on_delete_delegates_to_indexer(): void {
		$indexer = $this->createMock( Image_Indexer::class );
		$indexer->expects( $this->once() )->method( 'on_delete' )->with( 42 );

		( new Media_Hooks( $indexer ) )->on_delete( 42 );
	}
}
