<?php
/**
 * Tests for Cron_Handler bulk batch wiring.
 *
 * @package Snopix
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use Snopix\Hooks\Cron_Handler;
use Snopix\Indexing\Bulk_Indexer;

/**
 * Cron_Handler unit tests.
 */
class Snopix_Cron_Handler_Test extends Snopix_TestCase {

	/**
	 * `register` hooks `process_batch` onto the bulk-index cron action.
	 *
	 * @return void
	 */
	public function test_register_attaches_action(): void {
		$handler = new Cron_Handler( $this->createMock( Bulk_Indexer::class ) );
		$handler->register();
		$this->assertNotFalse( has_action( 'snopix_bulk_index_batch', array( $handler, 'process_batch' ) ) );
		remove_action( 'snopix_bulk_index_batch', array( $handler, 'process_batch' ) );
	}

	/**
	 * `process_batch` delegates to the Bulk_Indexer.
	 *
	 * @return void
	 */
	public function test_process_batch_delegates(): void {
		$bulk = $this->createMock( Bulk_Indexer::class );
		$bulk->expects( $this->once() )->method( 'process_batch' );
		( new Cron_Handler( $bulk ) )->process_batch();
	}
}
