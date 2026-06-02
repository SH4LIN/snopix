<?php
/**
 * Integration tests for Snopix\Infrastructure\Action_Scheduler.
 *
 * @package Snopix
 */

use Snopix\Infrastructure\Action_Scheduler;

/**
 * @covers \Snopix\Infrastructure\Action_Scheduler
 */
final class Action_Scheduler_Test extends Snopix_Integration_TestCase {

	private Action_Scheduler $scheduler;

	public function set_up(): void {
		parent::set_up();
		$this->scheduler = new Action_Scheduler();
	}

	public function test_schedule_makes_wp_next_scheduled_return_timestamp(): void {
		$hook = 'snopix_test_schedule_hook';
		$args = array( 99 );

		$this->assertFalse( wp_next_scheduled( $hook, $args ) );

		$ok = $this->scheduler->schedule( $hook, $args );
		$this->assertTrue( $ok );

		$this->assertIsInt( wp_next_scheduled( $hook, $args ) );
	}

	public function test_schedule_honours_delay_in_event_timestamp(): void {
		$hook  = 'snopix_test_delay_hook';
		$args  = array( 7 );
		$delay = 600;
		$before = time();

		$this->scheduler->schedule( $hook, $args, $delay );

		$timestamp = wp_next_scheduled( $hook, $args );
		$this->assertGreaterThanOrEqual( $before + $delay, $timestamp );
	}

	public function test_scheduled_event_is_single_not_recurring(): void {
		$hook = 'snopix_test_single_hook';
		$args = array( 1 );

		$this->scheduler->schedule( $hook, $args );

		$event = wp_get_scheduled_event( $hook, $args );
		$this->assertIsObject( $event );
		$this->assertFalse( $event->schedule );
		$this->assertNull( $event->interval ?? null );
	}

	public function test_cancel_all_clears_pending_event(): void {
		// Production always schedules this scheduler's hooks with empty args
		// (see Bulk_Indexer / Duplicate_Scanner), so cancel_all() — which wraps
		// wp_clear_scheduled_hook() — is exercised the same way here.
		$hook = 'snopix_test_cancel_hook';

		$this->scheduler->schedule( $hook, array() );
		$this->assertIsInt( wp_next_scheduled( $hook ) );

		$this->scheduler->cancel_all( $hook );
		$this->assertFalse( wp_next_scheduled( $hook ) );
	}

	public function test_has_pending_reflects_schedule_and_cancel(): void {
		$hook = 'snopix_test_has_pending_hook';

		$this->assertFalse( $this->scheduler->has_pending( $hook ) );

		$this->scheduler->schedule( $hook, array() );
		$this->assertTrue( $this->scheduler->has_pending( $hook ) );

		$this->scheduler->cancel_all( $hook );
		$this->assertFalse( $this->scheduler->has_pending( $hook ) );
	}
}
