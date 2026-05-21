<?php
/**
 * Tests for Bulk_Indexer batch orchestration.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Indexing\Bulk_Indexer;
use PixelScout\Indexing\Image_Indexer;
use PixelScout\Indexing\Index_Progress;
use PixelScout\Infrastructure\Action_Scheduler;
use PixelScout\Repository\Index_Repository;

/**
 * Bulk_Indexer unit tests.
 */
class Pixel_Scout_Bulk_Indexer_Test extends Pixel_Scout_TestCase {

	/**
	 * Reset transient state before each test to avoid cross-test bleed.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		delete_transient( Bulk_Indexer::PENDING_KEY );
		( new Index_Progress() )->reset();
	}

	/**
	 * Tear down transients.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_transient( Bulk_Indexer::PENDING_KEY );
		( new Index_Progress() )->reset();
		parent::tearDown();
	}

	/**
	 * `schedule` with no unindexed IDs must be a no-op (no progress, no cron).
	 *
	 * @return void
	 */
	public function test_schedule_noop_when_no_pending(): void {
		$repo = $this->createMock( Index_Repository::class );
		$repo->method( 'get_unindexed_ids' )->willReturn( array() );

		$scheduler = $this->createMock( Action_Scheduler::class );
		$scheduler->expects( $this->never() )->method( 'schedule' );

		$bulk = new Bulk_Indexer(
			$repo,
			$this->createMock( Image_Indexer::class ),
			new Index_Progress(),
			$scheduler
		);
		$bulk->schedule();

		$this->assertSame( 'idle', ( new Index_Progress() )->get()['status'] );
	}

	/**
	 * `schedule` populates the pending transient and triggers the first cron.
	 *
	 * @return void
	 */
	public function test_schedule_stores_pending_and_triggers_cron(): void {
		$ids = array( 1, 2, 3 );

		$repo = $this->createMock( Index_Repository::class );
		$repo->method( 'get_unindexed_ids' )->willReturn( $ids );

		$scheduler = $this->createMock( Action_Scheduler::class );
		$scheduler->expects( $this->once() )
			->method( 'schedule' )
			->with( Bulk_Indexer::CRON_HOOK, array(), 0 );

		$progress = new Index_Progress();
		$bulk     = new Bulk_Indexer(
			$repo,
			$this->createMock( Image_Indexer::class ),
			$progress,
			$scheduler
		);
		$bulk->schedule();

		$this->assertSame( $ids, get_transient( Bulk_Indexer::PENDING_KEY ) );
		$state = $progress->get();
		$this->assertSame( 'running', $state['status'] );
		$this->assertSame( 3, $state['total'] );
	}

	/**
	 * `schedule_all` wipes the index before scheduling fresh batches.
	 *
	 * @return void
	 */
	public function test_schedule_all_clears_index_first(): void {
		$repo = $this->createMock( Index_Repository::class );
		$repo->expects( $this->once() )->method( 'clear_all' );
		$repo->method( 'get_unindexed_ids' )->willReturn( array( 5 ) );

		$bulk = new Bulk_Indexer(
			$repo,
			$this->createMock( Image_Indexer::class ),
			new Index_Progress(),
			$this->createMock( Action_Scheduler::class )
		);
		$bulk->schedule_all();
	}

	/**
	 * `process_batch` is a no-op when nothing pending.
	 *
	 * @return void
	 */
	public function test_process_batch_noop_when_no_pending(): void {
		$indexer = $this->createMock( Image_Indexer::class );
		$indexer->expects( $this->never() )->method( 'index_single' );

		$bulk = new Bulk_Indexer(
			$this->createMock( Index_Repository::class ),
			$indexer,
			new Index_Progress(),
			$this->createMock( Action_Scheduler::class )
		);
		$bulk->process_batch();
	}

	/**
	 * A small pending list (< batch size) is consumed in one tick and no
	 * follow-up cron is queued.
	 *
	 * @return void
	 */
	public function test_process_batch_consumes_small_queue_without_reschedule(): void {
		set_transient( Bulk_Indexer::PENDING_KEY, array( 1, 2 ), DAY_IN_SECONDS );
		( new Index_Progress() )->set( 0, 2 );

		$indexer = $this->createMock( Image_Indexer::class );
		$indexer->method( 'index_single' )->willReturn( true );

		$scheduler = $this->createMock( Action_Scheduler::class );
		$scheduler->expects( $this->never() )->method( 'schedule' );

		$bulk = new Bulk_Indexer(
			$this->createMock( Index_Repository::class ),
			$indexer,
			new Index_Progress(),
			$scheduler
		);
		$bulk->process_batch();

		$this->assertFalse( get_transient( Bulk_Indexer::PENDING_KEY ) );
		$this->assertSame( 'done', ( new Index_Progress() )->get()['status'] );
	}

	/**
	 * A batch that totally fails halts the chain and marks progress stalled.
	 *
	 * @return void
	 */
	public function test_process_batch_marks_stalled_when_full_batch_fails(): void {
		// Provide more than BATCH_SIZE (50) so `remaining` is non-empty after slice.
		$ids = range( 1, 60 );
		set_transient( Bulk_Indexer::PENDING_KEY, $ids, DAY_IN_SECONDS );
		( new Index_Progress() )->set( 0, count( $ids ) );

		$indexer = $this->createMock( Image_Indexer::class );
		$indexer->method( 'index_single' )->willReturn( false );

		$scheduler = $this->createMock( Action_Scheduler::class );
		$scheduler->expects( $this->never() )->method( 'schedule' );

		$bulk = new Bulk_Indexer(
			$this->createMock( Index_Repository::class ),
			$indexer,
			new Index_Progress(),
			$scheduler
		);
		$bulk->process_batch();

		$this->assertSame( 'stalled', ( new Index_Progress() )->get()['status'] );
		$this->assertFalse( get_transient( Bulk_Indexer::PENDING_KEY ) );
	}

	/**
	 * When work remains and at least one item succeeds, the next batch is
	 * scheduled with the configured delay.
	 *
	 * @return void
	 */
	public function test_process_batch_schedules_next_when_remaining(): void {
		$ids = range( 1, 60 );
		set_transient( Bulk_Indexer::PENDING_KEY, $ids, DAY_IN_SECONDS );
		( new Index_Progress() )->set( 0, count( $ids ) );

		$indexer = $this->createMock( Image_Indexer::class );
		$indexer->method( 'index_single' )->willReturn( true );

		$scheduler = $this->createMock( Action_Scheduler::class );
		$scheduler->expects( $this->once() )
			->method( 'schedule' )
			->with( Bulk_Indexer::CRON_HOOK, array(), 60 );

		$bulk = new Bulk_Indexer(
			$this->createMock( Index_Repository::class ),
			$indexer,
			new Index_Progress(),
			$scheduler
		);
		$bulk->process_batch();

		$remaining = get_transient( Bulk_Indexer::PENDING_KEY );
		$this->assertIsArray( $remaining );
		$this->assertCount( 10, $remaining );
	}
}
