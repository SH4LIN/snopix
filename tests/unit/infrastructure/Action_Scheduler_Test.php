<?php
/**
 * Tests for Action_Scheduler WP-Cron wrapper.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Infrastructure\Action_Scheduler;

/**
 * Action_Scheduler unit tests.
 */
class Pixel_Scout_Action_Scheduler_Test extends Pixel_Scout_TestCase {

	private Action_Scheduler $scheduler;

	private const HOOK = 'ps_test_action';

	/**
	 * Set up a clean scheduler before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->scheduler = new Action_Scheduler();
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Clear any leftover scheduled events.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_clear_scheduled_hook( self::HOOK );
		parent::tearDown();
	}

	/**
	 * `schedule` queues a single event and `has_pending` confirms it.
	 *
	 * @return void
	 */
	public function test_schedule_queues_event(): void {
		$result = $this->scheduler->schedule( self::HOOK, array(), 0 );
		$this->assertTrue( $result );
		$this->assertTrue( $this->scheduler->has_pending( self::HOOK ) );
	}

	/**
	 * Scheduling with a delay places the event in the future.
	 *
	 * @return void
	 */
	public function test_schedule_with_delay_sets_future_timestamp(): void {
		$before = time();
		$this->scheduler->schedule( self::HOOK, array(), 30 );
		$next   = wp_next_scheduled( self::HOOK );
		$this->assertIsInt( $next );
		$this->assertGreaterThanOrEqual( $before + 30, $next );
	}

	/**
	 * `cancel_all` removes every queued event for the hook.
	 *
	 * @return void
	 */
	public function test_cancel_all_removes_queued_event(): void {
		$this->scheduler->schedule( self::HOOK, array(), 60 );
		$this->assertTrue( $this->scheduler->has_pending( self::HOOK ) );

		$this->scheduler->cancel_all( self::HOOK );
		$this->assertFalse( $this->scheduler->has_pending( self::HOOK ) );
	}

	/**
	 * `has_pending` returns false when nothing has been scheduled for the hook.
	 *
	 * @return void
	 */
	public function test_has_pending_false_when_empty(): void {
		$this->assertFalse( $this->scheduler->has_pending( self::HOOK ) );
	}

	/**
	 * Args are forwarded to the underlying WP-Cron registration.
	 *
	 * @return void
	 */
	public function test_schedule_with_args_records_args(): void {
		$this->scheduler->schedule( self::HOOK, array( 42 ), 0 );
		$next = wp_next_scheduled( self::HOOK, array( 42 ) );
		$this->assertIsInt( $next );
	}
}
