<?php
/**
 * Tests for Image_Indexer single-image indexing flow.
 *
 * @package Snopix
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use Snopix\Indexing\Image_Indexer;
use Snopix\Indexing\Mime_Validator;
use Snopix\Repository\Index_Repository;
use Snopix\Search\Fingerprint_Factory;

/**
 * Image_Indexer unit tests.
 */
class Snopix_Image_Indexer_Test extends Snopix_TestCase {

	/**
	 * Build a fingerprint payload matching the factory shape.
	 *
	 * @return array<string, mixed>
	 */
	private function valid_fingerprint(): array {
		return array(
			'phash'        => 'abcdef1234567890',
			'color_vector' => array_fill( 0, 48, 0.5 ),
			'edge_vector'  => array_fill( 0, 32, 0.5 ),
		);
	}

	/**
	 * Unsupported MIME types must be marked failed and skipped.
	 *
	 * @return void
	 */
	public function test_unsupported_mime_marked_failed(): void {
		$id = (int) self::factory()->attachment->create( array( 'post_mime_type' => 'image/tiff' ) );

		$repo = $this->createMock( Index_Repository::class );
		$repo->expects( $this->once() )
			->method( 'mark_failed' )
			->with( $id, 'unsupported_mime' )
			->willReturn( true );
		$repo->expects( $this->never() )->method( 'upsert' );

		$indexer = new Image_Indexer(
			new Mime_Validator(),
			$this->createMock( Fingerprint_Factory::class ),
			$repo
		);

		$this->assertFalse( $indexer->index_single( $id ) );
	}

	/**
	 * An empty fingerprint must be marked unfingerprintable, not upserted.
	 *
	 * @return void
	 */
	public function test_empty_fingerprint_marked_unfingerprintable(): void {
		$id = (int) self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );

		$factory = $this->createMock( Fingerprint_Factory::class );
		$factory->method( 'generate' )->willReturn( array() );

		$repo = $this->createMock( Index_Repository::class );
		$repo->expects( $this->once() )
			->method( 'mark_failed' )
			->with( $id, 'unfingerprintable' )
			->willReturn( true );
		$repo->expects( $this->never() )->method( 'upsert' );

		$indexer = new Image_Indexer( new Mime_Validator(), $factory, $repo );
		$this->assertFalse( $indexer->index_single( $id ) );
	}

	/**
	 * A valid fingerprint is enriched with metadata and upserted.
	 *
	 * @return void
	 */
	public function test_valid_fingerprint_is_upserted(): void {
		$id = (int) self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );

		$factory = $this->createMock( Fingerprint_Factory::class );
		$factory->method( 'generate' )->willReturn( $this->valid_fingerprint() );

		$captured = null;
		$repo     = $this->createMock( Index_Repository::class );
		$repo->expects( $this->once() )
			->method( 'upsert' )
			->willReturnCallback(
				static function ( $aid, $fp ) use ( &$captured ) {
					$captured = $fp;
					return true;
				}
			);

		$indexer = new Image_Indexer( new Mime_Validator(), $factory, $repo );
		$this->assertTrue( $indexer->index_single( $id ) );
		$this->assertIsArray( $captured );
		$this->assertSame( 'image/jpeg', $captured['mime_type'] );
		$this->assertArrayHasKey( 'width', $captured );
		$this->assertArrayHasKey( 'height', $captured );
		$this->assertArrayHasKey( 'file_size', $captured );
	}

	/**
	 * `on_delete` proxies to the repository delete method.
	 *
	 * @return void
	 */
	public function test_on_delete_proxies_to_repository(): void {
		$repo = $this->createMock( Index_Repository::class );
		$repo->expects( $this->once() )->method( 'delete' )->with( 42 )->willReturn( true );

		$indexer = new Image_Indexer(
			new Mime_Validator(),
			$this->createMock( Fingerprint_Factory::class ),
			$repo
		);
		$this->assertTrue( $indexer->on_delete( 42 ) );
	}
}
