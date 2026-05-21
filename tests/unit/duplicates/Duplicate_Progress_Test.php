<?php
/**
 * Tests for Duplicate_Progress transient-backed state machine.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Duplicates\Duplicate_Progress;

/**
 * Duplicate_Progress unit tests.
 */
class Pixel_Scout_Duplicate_Progress_Test extends Pixel_Scout_TestCase {

	private Duplicate_Progress $progress;

	/**
	 * Reset state before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->progress = new Duplicate_Progress();
		$this->progress->reset();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->progress->reset();
		parent::tearDown();
	}

	/**
	 * Fresh state reports idle with zero counters.
	 *
	 * @return void
	 */
	public function test_get_returns_idle_when_empty(): void {
		$state = $this->progress->get();
		$this->assertSame( 'idle', $state['status'] );
		$this->assertSame( 0, $state['done'] );
		$this->assertSame( 0, $state['total'] );
	}

	/**
	 * `set` initialises counters and marks running.
	 *
	 * @return void
	 */
	public function test_set_initial_state(): void {
		$this->progress->set( 0, 50 );
		$state = $this->progress->get();
		$this->assertSame( 'running', $state['status'] );
		$this->assertSame( 50, $state['total'] );
	}

	/**
	 * `increment` advances done by 1.
	 *
	 * @return void
	 */
	public function test_increment_advances_done(): void {
		$this->progress->set( 0, 3 );
		$this->progress->increment();
		$this->assertSame( 1, $this->progress->get()['done'] );
	}

	/**
	 * Reaching `done === total` via increment flips status to done.
	 *
	 * @return void
	 */
	public function test_increment_transitions_to_done(): void {
		$this->progress->set( 0, 2 );
		$this->progress->increment();
		$this->progress->increment();
		$this->assertSame( 'done', $this->progress->get()['status'] );
	}

	/**
	 * `mark_done` short-circuits the counter and forces done status.
	 *
	 * @return void
	 */
	public function test_mark_done_forces_done_status(): void {
		$this->progress->set( 0, 100 );
		$this->progress->mark_done();
		$this->assertSame( 'done', $this->progress->get()['status'] );
	}

	/**
	 * `reset` returns the state to idle.
	 *
	 * @return void
	 */
	public function test_reset_returns_to_idle(): void {
		$this->progress->set( 5, 10 );
		$this->progress->reset();
		$this->assertSame( 'idle', $this->progress->get()['status'] );
	}
}
