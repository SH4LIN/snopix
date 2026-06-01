<?php
/**
 * Unit tests for Snopix\Infrastructure\Job_Status.
 *
 * @package Snopix
 */

use Snopix\Infrastructure\Job_Status;

/**
 * @covers \Snopix\Infrastructure\Job_Status
 */
final class Job_Status_Test extends Snopix_Unit_TestCase {

	/**
	 * The string values are part of the REST contract (the admin app's
	 * TypeScript unions match these exact tokens), so they must not drift.
	 */
	public function test_status_tokens_match_rest_contract(): void {
		$this->assertSame( 'idle', Job_Status::IDLE );
		$this->assertSame( 'running', Job_Status::RUNNING );
		$this->assertSame( 'stalled', Job_Status::STALLED );
		$this->assertSame( 'done', Job_Status::DONE );
	}

	/**
	 * @dataProvider provide_active_cases
	 *
	 * @param string $status   Status token.
	 * @param bool   $expected Whether the status blocks scheduling a new run.
	 */
	public function test_is_active( string $status, bool $expected ): void {
		$this->assertSame( $expected, Job_Status::is_active( $status ) );
	}

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function provide_active_cases(): array {
		return array(
			'running is active'      => array( Job_Status::RUNNING, true ),
			'stalled is active'      => array( Job_Status::STALLED, true ),
			'idle is not active'     => array( Job_Status::IDLE, false ),
			'done is not active'     => array( Job_Status::DONE, false ),
			'unknown is not active'  => array( 'bogus', false ),
		);
	}
}
