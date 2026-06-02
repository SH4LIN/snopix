<?php
/**
 * Integration tests for Snopix\Indexing\Index_Progress.
 *
 * @package Snopix
 */

use Snopix\Indexing\Index_Progress;
use Snopix\Infrastructure\Job_Status;

/**
 * @covers \Snopix\Indexing\Index_Progress
 */
final class Index_Progress_Test extends Snopix_Integration_TestCase {

	private Index_Progress $progress;

	public function set_up(): void {
		parent::set_up();
		$this->progress = new Index_Progress();
		// Guarantee a clean envelope; transients may survive between tests.
		$this->progress->reset();
	}

	public function tear_down(): void {
		$this->progress->reset();
		parent::tear_down();
	}

	/**
	 * A missing transient yields the idle sentinel, not a confusing zero state.
	 */
	public function test_get_returns_idle_defaults_when_unset(): void {
		$state = $this->progress->get();

		$this->assertSame( 0, $state['done'] );
		$this->assertSame( 0, $state['total'] );
		$this->assertSame( Job_Status::IDLE, $state['status'] );
	}

	/**
	 * set() seeds done/total and marks the envelope running.
	 */
	public function test_set_seeds_counts_and_marks_running(): void {
		$this->progress->set( 2, 10 );

		$state = $this->progress->get();
		$this->assertSame( 2, $state['done'] );
		$this->assertSame( 10, $state['total'] );
		$this->assertSame( Job_Status::RUNNING, $state['status'] );
	}

	/**
	 * increment() bumps done by exactly one and leaves status running while
	 * the job is incomplete.
	 */
	public function test_increment_adds_one_and_stays_running(): void {
		$this->progress->set( 0, 3 );

		$this->progress->increment();

		$state = $this->progress->get();
		$this->assertSame( 1, $state['done'] );
		$this->assertSame( Job_Status::RUNNING, $state['status'] );
	}

	/**
	 * increment_by() adds the given count in one write.
	 */
	public function test_increment_by_adds_count(): void {
		$this->progress->set( 1, 10 );

		$this->progress->increment_by( 4 );

		$state = $this->progress->get();
		$this->assertSame( 5, $state['done'] );
		$this->assertSame( Job_Status::RUNNING, $state['status'] );
	}

	/**
	 * A non-positive increment is a no-op and never writes state.
	 */
	public function test_increment_by_ignores_non_positive(): void {
		$this->progress->set( 2, 10 );

		$this->progress->increment_by( 0 );
		$this->progress->increment_by( -5 );

		$state = $this->progress->get();
		$this->assertSame( 2, $state['done'] );
		$this->assertSame( Job_Status::RUNNING, $state['status'] );
	}

	/**
	 * Reaching the total transitions the status to done.
	 */
	public function test_increment_to_total_transitions_to_done(): void {
		$this->progress->set( 1, 2 );

		$this->progress->increment();

		$state = $this->progress->get();
		$this->assertSame( 2, $state['done'] );
		$this->assertSame( Job_Status::DONE, $state['status'] );
	}

	/**
	 * Overshooting the total (done > total) still marks the job done.
	 */
	public function test_increment_past_total_marks_done(): void {
		$this->progress->set( 2, 3 );

		$this->progress->increment_by( 5 );

		$state = $this->progress->get();
		$this->assertSame( 7, $state['done'] );
		$this->assertSame( Job_Status::DONE, $state['status'] );
	}

	/**
	 * With total=0 the done-transition guard never fires, so the status stays
	 * running no matter how much it is incremented.
	 */
	public function test_increment_with_zero_total_never_completes(): void {
		$this->progress->set( 0, 0 );

		$this->progress->increment_by( 3 );

		$state = $this->progress->get();
		$this->assertSame( 3, $state['done'] );
		$this->assertSame( Job_Status::RUNNING, $state['status'] );
	}

	/**
	 * reset() clears the transient and returns get() to the idle defaults.
	 */
	public function test_reset_returns_to_idle_defaults(): void {
		$this->progress->set( 4, 8 );

		$this->progress->reset();

		$state = $this->progress->get();
		$this->assertSame( 0, $state['done'] );
		$this->assertSame( 0, $state['total'] );
		$this->assertSame( Job_Status::IDLE, $state['status'] );
	}

	/**
	 * mark_stalled() forces the status token while preserving the counts.
	 */
	public function test_mark_stalled_sets_status_and_keeps_counts(): void {
		$this->progress->set( 3, 10 );

		$this->progress->mark_stalled();

		$state = $this->progress->get();
		$this->assertSame( 3, $state['done'] );
		$this->assertSame( 10, $state['total'] );
		$this->assertSame( Job_Status::STALLED, $state['status'] );
		$this->assertTrue( Job_Status::is_active( $state['status'] ) );
	}

	/**
	 * The envelope persists across distinct Index_Progress instances because
	 * it lives in the shared transient store, not in object state.
	 */
	public function test_state_persists_across_instances(): void {
		$this->progress->set( 5, 20 );

		$fresh = new Index_Progress();
		$state = $fresh->get();

		$this->assertSame( 5, $state['done'] );
		$this->assertSame( 20, $state['total'] );
		$this->assertSame( Job_Status::RUNNING, $state['status'] );
	}
}
