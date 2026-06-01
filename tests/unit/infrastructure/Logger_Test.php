<?php
/**
 * Unit tests for Snopix\Infrastructure\Logger.
 *
 * Logger gates on the WP_DEBUG constant, which cannot be toggled twice in one
 * process. The "enabled" path therefore runs in an isolated subprocess so it
 * can define WP_DEBUG=true without poisoning the rest of the suite, while the
 * "disabled" path runs in the main process where the unit bootstrap never
 * defines WP_DEBUG.
 *
 * @package Snopix
 */

use Snopix\Infrastructure\Logger;

/**
 * @covers \Snopix\Infrastructure\Logger
 */
final class Logger_Test extends Snopix_Unit_TestCase {

	public function test_debug_is_noop_when_wp_debug_undefined(): void {
		$this->assertFalse( defined( 'WP_DEBUG' ), 'Unit bootstrap must not define WP_DEBUG.' );
		$this->assertNull( Logger::debug( 'ignored' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_debug_writes_prefixed_line_when_wp_debug_enabled(): void {
		define( 'WP_DEBUG', true );

		$log = tempnam( sys_get_temp_dir(), 'snopix-log-' );
		ini_set( 'error_log', $log );

		Logger::debug( 'hello world' );

		$contents = (string) file_get_contents( $log );
		unlink( $log );

		$this->assertStringContainsString( '[Snopix] hello world', $contents );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_exception_logs_class_message_and_location(): void {
		define( 'WP_DEBUG', true );

		$log = tempnam( sys_get_temp_dir(), 'snopix-log-' );
		ini_set( 'error_log', $log );

		Logger::exception( new \RuntimeException( 'boom' ), 'while indexing' );

		$contents = (string) file_get_contents( $log );
		unlink( $log );

		$this->assertStringContainsString( 'while indexing', $contents );
		$this->assertStringContainsString( 'RuntimeException', $contents );
		$this->assertStringContainsString( 'boom', $contents );
	}
}
