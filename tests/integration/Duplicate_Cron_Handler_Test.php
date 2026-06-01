<?php
/**
 * Integration tests for Snopix\Duplicates\Duplicate_Cron_Handler.
 *
 * @package Snopix
 */

use Snopix\Duplicates\Duplicate_Cron_Handler;
use Snopix\Duplicates\Duplicate_Finder;
use Snopix\Duplicates\Duplicate_Progress;
use Snopix\Duplicates\Duplicate_Scanner;
use Snopix\Imaging\Similarity;
use Snopix\Infrastructure\Action_Scheduler;
use Snopix\Repository\Index_Repository;

/**
 * @covers \Snopix\Duplicates\Duplicate_Cron_Handler
 */
final class Duplicate_Cron_Handler_Test extends Snopix_Integration_TestCase {

	private Duplicate_Cron_Handler $handler;
	private Duplicate_Scanner $scanner;

	public function set_up(): void {
		parent::set_up();

		// Clear any action the live plugin registered during bootstrap so each
		// test starts from a known clean state.
		remove_all_actions( Duplicate_Scanner::CRON_HOOK );

		global $wpdb;

		$similarity = new Similarity();
		$finder     = new Duplicate_Finder( $similarity );
		$progress   = new Duplicate_Progress();
		$scheduler  = new Action_Scheduler();
		$repository = new Index_Repository( $wpdb );

		$this->scanner = new Duplicate_Scanner( $repository, $finder, $progress, $scheduler );
		$this->handler = new Duplicate_Cron_Handler( $this->scanner );
	}

	public function tear_down(): void {
		// Remove the action registered by register() to keep the global hook
		// table clean between test methods.
		remove_all_actions( Duplicate_Scanner::CRON_HOOK );

		// Clear any cron events scheduled during the test.
		wp_clear_scheduled_hook( Duplicate_Scanner::CRON_HOOK );

		parent::tear_down();
	}

	/**
	 * register() must add a callback for CRON_HOOK.
	 */
	public function test_register_adds_action_for_cron_hook(): void {
		$this->assertFalse(
			has_action( Duplicate_Scanner::CRON_HOOK ),
			'Pre-condition: no action registered before register().'
		);

		$this->handler->register();

		$this->assertNotFalse(
			has_action( Duplicate_Scanner::CRON_HOOK ),
			'register() must add an action callback for CRON_HOOK.'
		);
	}

	/**
	 * Calling register() twice must not double-register the same callback.
	 * WordPress's has_action() returns the priority (int) when exactly one
	 * matching callback exists; the count of callbacks is the authoritative
	 * indicator here.
	 */
	public function test_register_twice_does_not_duplicate_callback(): void {
		$this->handler->register();
		$this->handler->register();

		global $wp_filter;
		$hook = Duplicate_Scanner::CRON_HOOK;

		// Collect all callbacks registered for the hook at every priority.
		$all_callbacks = array();
		if ( isset( $wp_filter[ $hook ] ) ) {
			foreach ( $wp_filter[ $hook ]->callbacks as $priority_callbacks ) {
				foreach ( $priority_callbacks as $callback ) {
					$all_callbacks[] = $callback['function'];
				}
			}
		}

		// Check that the exact [handler, 'run'] pair appears exactly once.
		$count = 0;
		foreach ( $all_callbacks as $cb ) {
			if ( is_array( $cb ) && $cb[0] === $this->handler && $cb[1] === 'run' ) {
				++$count;
			}
		}

		$this->assertSame(
			1,
			$count,
			'register() called twice must not register the [handler, run] callback more than once.'
		);
	}

	/**
	 * run() delegates to the scanner. With an empty index the scanner finalises
	 * immediately (fewer than 2 rows → no pairs) and persists an empty results
	 * set. Verify run() completes without error and the results option is written.
	 */
	public function test_run_delegates_to_scanner_with_empty_index(): void {
		// Precondition: no results stored yet.
		delete_option( 'snopix_duplicate_results' );

		$this->handler->register();
		$this->handler->run();

		// Scanner#finalise writes the results option; an empty run yields [].
		$raw    = get_option( 'snopix_duplicate_results', null );
		$groups = json_decode( (string) $raw, true );

		$this->assertIsArray( $groups, 'run() must cause the scanner to write the results option.' );
		$this->assertSame( array(), $groups, 'Empty index must produce zero duplicate groups.' );
	}

	/**
	 * Firing the CRON_HOOK via do_action() after register() invokes run() and
	 * the scanner's finalisation path. Smoke-tests the full hook→handler→scanner
	 * wiring in the WordPress action system.
	 */
	public function test_do_action_on_cron_hook_runs_handler(): void {
		delete_option( 'snopix_duplicate_results' );

		$this->handler->register();

		do_action( Duplicate_Scanner::CRON_HOOK );

		$raw = get_option( 'snopix_duplicate_results', null );
		$this->assertNotNull(
			$raw,
			'Firing CRON_HOOK via do_action must cause the scanner to write results.'
		);
	}
}
