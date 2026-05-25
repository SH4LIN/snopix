<?php
/**
 * Tests for Duplicate_Cron_Handler scan wiring.
 *
 * @package Snopix
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use Snopix\Duplicates\Duplicate_Cron_Handler;
use Snopix\Duplicates\Duplicate_Scanner;

/**
 * Duplicate_Cron_Handler unit tests.
 */
class Snopix_Duplicate_Cron_Handler_Test extends Snopix_TestCase {

	/**
	 * `register` attaches the run handler to the scanner's CRON_HOOK.
	 *
	 * @return void
	 */
	public function test_register_attaches_action(): void {
		$handler = new Duplicate_Cron_Handler( $this->createMock( Duplicate_Scanner::class ) );
		$handler->register();
		$this->assertNotFalse( has_action( Duplicate_Scanner::CRON_HOOK, array( $handler, 'run' ) ) );
		remove_action( Duplicate_Scanner::CRON_HOOK, array( $handler, 'run' ) );
	}

	/**
	 * `run` delegates to the scanner.
	 *
	 * @return void
	 */
	public function test_run_delegates_to_scanner(): void {
		$scanner = $this->createMock( Duplicate_Scanner::class );
		$scanner->expects( $this->once() )->method( 'run' );
		( new Duplicate_Cron_Handler( $scanner ) )->run();
	}
}
