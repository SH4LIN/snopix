<?php
/**
 * Tests for Index_Progress transient-backed state machine.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Indexing\Index_Progress;

/**
 * Index_Progress unit tests.
 */
class Pixel_Scout_Index_Progress_Test extends Pixel_Scout_TestCase {

	private Index_Progress $progress;

	/**
	 * Reset transient state before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->progress = new Index_Progress();
		$this->progress->reset();
	}

	/**
	 * Clear residual state after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->progress->reset();
		parent::tearDown();
	}

	/**
	 * A freshly-reset progress reports idle with zero counts.
	 *
	 * @return void
	 */
	public function test_get_returns_idle_envelope_when_no_state(): void {
		$state = $this->progress->get();
		$this->assertSame( 'idle', $state['status'] );
		$this->assertSame( 0, $state['done'] );
		$this->assertSame( 0, $state['total'] );
	}

	/**
	 * `set` initialises counts and transitions to running.
	 *
	 * @return void
	 */
	public function test_set_writes_initial_state_running(): void {
		$this->progress->set( 0, 100 );
		$state = $this->progress->get();
		$this->assertSame( 'running', $state['status'] );
		$this->assertSame( 0, $state['done'] );
		$this->assertSame( 100, $state['total'] );
	}

	/**
	 * `increment` advances done by 1 and stays running until total is reached.
	 *
	 * @return void
	 */
	public function test_increment_advances_done(): void {
		$this->progress->set( 0, 3 );
		$this->progress->increment();
		$this->assertSame( 1, $this->progress->get()['done'] );
		$this->assertSame( 'running', $this->progress->get()['status'] );
	}

	/**
	 * Reaching `done === total` flips status to done.
	 *
	 * @return void
	 */
	public function test_increment_transitions_to_done_when_total_reached(): void {
		$this->progress->set( 0, 2 );
		$this->progress->increment();
		$this->progress->increment();
		$this->assertSame( 'done', $this->progress->get()['status'] );
	}

	/**
	 * `increment_by` adds the supplied count in one transient write.
	 *
	 * @return void
	 */
	public function test_increment_by_advances_in_bulk(): void {
		$this->progress->set( 0, 10 );
		$this->progress->increment_by( 5 );
		$this->assertSame( 5, $this->progress->get()['done'] );
	}

	/**
	 * `increment_by` with a non-positive count is a no-op.
	 *
	 * @return void
	 */
	public function test_increment_by_zero_is_noop(): void {
		$this->progress->set( 2, 10 );
		$this->progress->increment_by( 0 );
		$this->assertSame( 2, $this->progress->get()['done'] );
		$this->progress->increment_by( -3 );
		$this->assertSame( 2, $this->progress->get()['done'] );
	}

	/**
	 * `mark_stalled` flips status without losing counts.
	 *
	 * @return void
	 */
	public function test_mark_stalled_keeps_counts(): void {
		$this->progress->set( 4, 10 );
		$this->progress->mark_stalled();
		$state = $this->progress->get();
		$this->assertSame( 'stalled', $state['status'] );
		$this->assertSame( 4, $state['done'] );
		$this->assertSame( 10, $state['total'] );
	}

	/**
	 * `reset` clears everything back to idle.
	 *
	 * @return void
	 */
	public function test_reset_returns_to_idle(): void {
		$this->progress->set( 1, 1 );
		$this->progress->reset();
		$this->assertSame( 'idle', $this->progress->get()['status'] );
		$this->assertSame( 0, $this->progress->get()['done'] );
	}

	/**
	 * Total = 0 must not flip to done after an increment (legitimate zero-job).
	 *
	 * @return void
	 */
	public function test_total_zero_stays_running_after_increment(): void {
		$this->progress->set( 0, 0 );
		$this->progress->increment();
		$this->assertNotSame( 'done', $this->progress->get()['status'] );
	}

	/**
	 * Two Index_Progress instances with different keys must keep isolated state
	 * so a regen run cannot clobber a bulk-index run mid-flight.
	 *
	 * @return void
	 */
	public function test_isolated_state_per_transient_key(): void {
		$a = new \PixelScout\Indexing\Index_Progress( 'ps_test_progress_a' );
		$b = new \PixelScout\Indexing\Index_Progress( 'ps_test_progress_b' );

		$a->set( 0, 10 );
		$b->set( 0, 100 );
		$a->increment_by( 5 );

		$this->assertSame( 5, $a->get()['done'] );
		$this->assertSame( 10, $a->get()['total'] );
		$this->assertSame( 0, $b->get()['done'] );
		$this->assertSame( 100, $b->get()['total'] );

		$a->reset();
		$b->reset();
	}

	/**
	 * Calling the constructor with no argument must preserve the legacy key
	 * so existing Bulk_Indexer behavior is unchanged.
	 *
	 * @return void
	 */
	public function test_default_key_is_backwards_compatible(): void {
		( new \PixelScout\Indexing\Index_Progress() )->set( 7, 14 );

		$state = get_transient( 'ps_bulk_progress_state' );
		$this->assertIsArray( $state );
		$this->assertSame( 7, $state['done'] );

		( new \PixelScout\Indexing\Index_Progress() )->reset();
	}
}
