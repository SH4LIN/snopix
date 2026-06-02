<?php
/**
 * Integration tests for Snopix\Duplicates\Duplicate_Progress.
 *
 * @package Snopix
 */

use Snopix\Duplicates\Duplicate_Progress;
use Snopix\Infrastructure\Job_Status;

/**
 * @covers \Snopix\Duplicates\Duplicate_Progress
 */
final class Duplicate_Progress_Test extends Snopix_Integration_TestCase {

	private Duplicate_Progress $progress;

	public function set_up(): void {
		parent::set_up();
		$this->progress = new Duplicate_Progress();
	}

	public function test_get_returns_idle_sentinel_when_unset(): void {
		$state = $this->progress->get();

		$this->assertSame(
			array(
				'done'   => 0,
				'total'  => 0,
				'status' => Job_Status::IDLE,
			),
			$state
		);
		$this->assertSame( 'idle', $state['status'] );
	}

	public function test_set_marks_running_with_counters(): void {
		$this->progress->set( 3, 10 );

		$state = $this->progress->get();
		$this->assertSame( 3, $state['done'] );
		$this->assertSame( 10, $state['total'] );
		$this->assertSame( Job_Status::RUNNING, $state['status'] );
		$this->assertSame( 'running', $state['status'] );
	}

	public function test_increment_bumps_done_without_completing(): void {
		$this->progress->set( 0, 4 );

		$this->progress->increment();
		$this->progress->increment();

		$state = $this->progress->get();
		$this->assertSame( 2, $state['done'] );
		$this->assertSame( 4, $state['total'] );
		$this->assertSame( Job_Status::RUNNING, $state['status'] );
	}

	public function test_increment_transitions_to_done_when_full(): void {
		$this->progress->set( 1, 2 );

		$this->progress->increment();

		$state = $this->progress->get();
		$this->assertSame( 2, $state['done'] );
		$this->assertSame( Job_Status::DONE, $state['status'] );
	}

	public function test_increment_from_idle_does_not_complete_with_zero_total(): void {
		// No set() call: total is 0, so increment must not flip to done.
		$this->progress->increment();

		$state = $this->progress->get();
		$this->assertSame( 1, $state['done'] );
		$this->assertSame( 0, $state['total'] );
		$this->assertSame( Job_Status::IDLE, $state['status'] );
	}

	public function test_reset_clears_state_back_to_idle_sentinel(): void {
		$this->progress->set( 5, 5 );
		$this->assertSame( Job_Status::RUNNING, $this->progress->get()['status'] );

		$this->progress->reset();

		$this->assertSame(
			array(
				'done'   => 0,
				'total'  => 0,
				'status' => Job_Status::IDLE,
			),
			$this->progress->get()
		);
	}

	public function test_mark_done_forces_completion_regardless_of_counters(): void {
		$this->progress->set( 1, 7 );

		$this->progress->mark_done();

		$state = $this->progress->get();
		$this->assertSame( 7, $state['done'] );
		$this->assertSame( Job_Status::DONE, $state['status'] );
	}

	public function test_mark_done_uses_floor_of_one_when_total_is_zero(): void {
		// Total stays 0 (never set); mark_done() must still report at least 1.
		$this->progress->mark_done();

		$state = $this->progress->get();
		$this->assertSame( 1, $state['done'] );
		$this->assertSame( 0, $state['total'] );
		$this->assertSame( Job_Status::DONE, $state['status'] );
	}

	public function test_mark_stalled_sets_stalled_status_and_keeps_counters(): void {
		$this->progress->set( 2, 9 );

		$this->progress->mark_stalled();

		$state = $this->progress->get();
		$this->assertSame( 2, $state['done'] );
		$this->assertSame( 9, $state['total'] );
		$this->assertSame( Job_Status::STALLED, $state['status'] );
	}

	public function test_status_tokens_are_active_for_running_and_stalled(): void {
		$this->progress->set( 0, 5 );
		$this->assertTrue( Job_Status::is_active( $this->progress->get()['status'] ) );

		$this->progress->mark_stalled();
		$this->assertTrue( Job_Status::is_active( $this->progress->get()['status'] ) );

		$this->progress->mark_done();
		$this->assertFalse( Job_Status::is_active( $this->progress->get()['status'] ) );

		$this->progress->reset();
		$this->assertFalse( Job_Status::is_active( $this->progress->get()['status'] ) );
	}
}
