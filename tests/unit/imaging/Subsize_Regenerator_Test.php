<?php
/**
 * Tests for Subsize_Regenerator offset-based queue + chained cron.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Imaging\Image_Subsize_Service;
use PixelScout\Imaging\Subsize_Regenerator;
use PixelScout\Imaging\Subsize_Watcher;
use PixelScout\Indexing\Index_Progress;
use PixelScout\Infrastructure\Action_Scheduler;

/**
 * Subsize_Regenerator unit tests.
 */
class Pixel_Scout_Subsize_Regenerator_Test extends Pixel_Scout_TestCase {

	private const PROGRESS_KEY = 'ps_regen_progress_state';

	public function setUp(): void {
		parent::setUp();
		delete_transient( Subsize_Regenerator::PENDING_KEY );
		delete_option( Subsize_Watcher::OPTION_KEY );
		( new Index_Progress( self::PROGRESS_KEY ) )->reset();
	}

	public function tearDown(): void {
		delete_transient( Subsize_Regenerator::PENDING_KEY );
		delete_option( Subsize_Watcher::OPTION_KEY );
		( new Index_Progress( self::PROGRESS_KEY ) )->reset();
		parent::tearDown();
	}

	/**
	 * Build a regenerator with stubbed counter + slice provider.
	 *
	 * @param int                          $total    Reported total image count.
	 * @param array<int, array<int, int>>  $slices   Sequential slices returned by slice_provider on each call.
	 * @param Image_Subsize_Service|null   $service  Optional service mock.
	 * @param Action_Scheduler|null        $sched    Optional scheduler mock.
	 *
	 * @return Subsize_Regenerator
	 */
	private function regen( int $total, array $slices, ?Image_Subsize_Service $service = null, ?Action_Scheduler $sched = null ): Subsize_Regenerator {
		$idx = 0;
		return new Subsize_Regenerator(
			$service ?? $this->createMock( Image_Subsize_Service::class ),
			new Subsize_Watcher(),
			new Index_Progress( self::PROGRESS_KEY ),
			$sched ?? $this->createMock( Action_Scheduler::class ),
			static fn(): int => $total,
			static function ( int $offset, int $size ) use ( &$idx, $slices ): array {
				$out = $slices[ $idx ] ?? array();
				++$idx;
				return $out;
			}
		);
	}

	public function test_schedule_missing_noop_when_total_zero(): void {
		$sched = $this->createMock( Action_Scheduler::class );
		$sched->expects( $this->never() )->method( 'schedule' );

		$count = $this->regen( 0, array(), null, $sched )->schedule_missing();

		$this->assertSame( 0, $count );
		$this->assertFalse( get_transient( Subsize_Regenerator::PENDING_KEY ) );
	}

	public function test_schedule_missing_stores_envelope_and_triggers_cron(): void {
		$sched = $this->createMock( Action_Scheduler::class );
		$sched->expects( $this->once() )->method( 'cancel_all' )->with( Subsize_Regenerator::CRON_HOOK );
		$sched->expects( $this->once() )->method( 'schedule' )->with( Subsize_Regenerator::CRON_HOOK, array(), 0 );

		$count = $this->regen( 73, array(), null, $sched )->schedule_missing();

		$this->assertSame( 73, $count );
		$env = get_transient( Subsize_Regenerator::PENDING_KEY );
		$this->assertSame(
			array(
				'mode'   => Subsize_Regenerator::MODE_MISSING,
				'offset' => 0,
				'total'  => 73,
			),
			$env
		);
		$this->assertSame( 'running', ( new Index_Progress( self::PROGRESS_KEY ) )->get()['status'] );
	}

	public function test_schedule_all_acknowledges_snapshot(): void {
		$watcher = new Subsize_Watcher();
		$watcher->diff(); // Seed.

		add_image_size( 'ps_test_ack_all', 444, 444, true );
		$this->assertTrue( $watcher->diff()['has_changes'] );

		$sched = $this->createMock( Action_Scheduler::class );
		$sched->method( 'schedule' )->willReturn( true );

		$regen = new Subsize_Regenerator(
			$this->createMock( Image_Subsize_Service::class ),
			$watcher,
			new Index_Progress( self::PROGRESS_KEY ),
			$sched,
			static fn(): int => 1,
			static fn( int $o, int $s ): array => array(),
		);
		$regen->schedule_all();

		$this->assertFalse( $watcher->diff()['has_changes'] );

		remove_image_size( 'ps_test_ack_all' );
	}

	public function test_process_batch_noop_when_no_envelope(): void {
		$service = $this->createMock( Image_Subsize_Service::class );
		$service->expects( $this->never() )->method( 'create_all' );

		$this->regen( 0, array(), $service )->process_batch();
		$this->assertFalse( get_transient( Subsize_Regenerator::PENDING_KEY ) );
	}

	public function test_process_batch_mode_all_invokes_create_all_per_id(): void {
		set_transient(
			Subsize_Regenerator::PENDING_KEY,
			array(
				'mode'   => Subsize_Regenerator::MODE_ALL,
				'offset' => 0,
				'total'  => 2,
			),
			DAY_IN_SECONDS
		);

		$service = $this->createMock( Image_Subsize_Service::class );
		$service->expects( $this->exactly( 2 ) )->method( 'create_all' )->willReturn( true );
		$service->expects( $this->never() )->method( 'create_subset' );

		$this->regen( 2, array( array( 11, 22 ) ), $service )->process_batch();

		$env = get_transient( Subsize_Regenerator::PENDING_KEY );
		$this->assertFalse( $env ); // Finished, transient deleted.
	}

	public function test_process_batch_mode_missing_passes_cached_registered_map(): void {
		set_transient(
			Subsize_Regenerator::PENDING_KEY,
			array(
				'mode'   => Subsize_Regenerator::MODE_MISSING,
				'offset' => 0,
				'total'  => 1,
			),
			DAY_IN_SECONDS
		);

		$service = $this->createMock( Image_Subsize_Service::class );
		$service->expects( $this->once() )
			->method( 'missing_sizes' )
			->with( 7, $this->isType( 'array' ) ) // Registered map passed in.
			->willReturn( array( 'medium' ) );
		$service->expects( $this->once() )
			->method( 'create_subset' )
			->with( 7, array( 'medium' ) )
			->willReturn( true );

		$this->regen( 1, array( array( 7 ) ), $service )->process_batch();
	}

	public function test_process_batch_chains_when_offset_not_done(): void {
		set_transient(
			Subsize_Regenerator::PENDING_KEY,
			array(
				'mode'   => Subsize_Regenerator::MODE_ALL,
				'offset' => 0,
				'total'  => Subsize_Regenerator::BATCH_SIZE + 10,
			),
			DAY_IN_SECONDS
		);

		$service = $this->createMock( Image_Subsize_Service::class );
		$service->method( 'create_all' )->willReturn( true );

		$sched = $this->createMock( Action_Scheduler::class );
		$sched->expects( $this->once() )
			->method( 'schedule' )
			->with( Subsize_Regenerator::CRON_HOOK, array(), Subsize_Regenerator::BATCH_DELAY );

		$slice = range( 1, Subsize_Regenerator::BATCH_SIZE );
		$this->regen( Subsize_Regenerator::BATCH_SIZE + 10, array( $slice ), $service, $sched )->process_batch();

		$env = get_transient( Subsize_Regenerator::PENDING_KEY );
		$this->assertSame( Subsize_Regenerator::BATCH_SIZE, $env['offset'] );
		$this->assertSame( Subsize_Regenerator::BATCH_SIZE + 10, $env['total'] );
	}

	public function test_process_batch_all_failed_halts_and_stalls(): void {
		set_transient(
			Subsize_Regenerator::PENDING_KEY,
			array(
				'mode'   => Subsize_Regenerator::MODE_ALL,
				'offset' => 0,
				'total'  => Subsize_Regenerator::BATCH_SIZE + 5,
			),
			DAY_IN_SECONDS
		);

		$service = $this->createMock( Image_Subsize_Service::class );
		$service->method( 'create_all' )->willReturn( false );

		$sched = $this->createMock( Action_Scheduler::class );
		$sched->expects( $this->never() )->method( 'schedule' );

		$progress = new Index_Progress( self::PROGRESS_KEY );
		$progress->set( 0, Subsize_Regenerator::BATCH_SIZE + 5 );

		$regen = new Subsize_Regenerator(
			$service,
			new Subsize_Watcher(),
			$progress,
			$sched,
			static fn(): int => Subsize_Regenerator::BATCH_SIZE + 5,
			static fn( int $o, int $s ): array => range( 1, Subsize_Regenerator::BATCH_SIZE ),
		);
		$regen->process_batch();

		$this->assertFalse( get_transient( Subsize_Regenerator::PENDING_KEY ) );
		$this->assertSame( 'stalled', $progress->get()['status'] );
	}
}
