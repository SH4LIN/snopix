<?php
/**
 * Tests for Rate_Limiter fixed-window enforcement.
 *
 * @package Snopix
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use Snopix\Api\Rate_Limiter;

/**
 * Rate_Limiter unit tests.
 */
class Snopix_Rate_Limiter_Test extends Snopix_TestCase {

	private Rate_Limiter $limiter;

	/**
	 * Build a fresh limiter before each test; clear any leftover transient.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->limiter = new Rate_Limiter();
		$this->purge_transients();
	}

	/**
	 * Clear any rate-limit transients written during the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->purge_transients();
		parent::tearDown();
	}

	/**
	 * Drop every `snopix_ratelimit_*` transient.
	 *
	 * @return void
	 */
	private function purge_transients(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_snopix_ratelimit_%' OR option_name LIKE '_transient_timeout_snopix_ratelimit_%'"
		);
	}

	/**
	 * First call from a previously-unseen IP must be allowed and seed the window.
	 *
	 * @return void
	 */
	public function test_first_request_is_allowed(): void {
		$this->assertTrue( $this->limiter->is_allowed( '203.0.113.1' ) );
	}

	/**
	 * Up to the limit (10) consecutive requests in one window must be allowed.
	 *
	 * @return void
	 */
	public function test_requests_under_limit_are_allowed(): void {
		for ( $i = 0; $i < 10; $i++ ) {
			$this->assertTrue(
				$this->limiter->is_allowed( '203.0.113.2' ),
				"Request {$i} should be allowed"
			);
		}
	}

	/**
	 * The 11th call from the same IP within one window must be rate-limited.
	 *
	 * @return void
	 */
	public function test_request_beyond_limit_is_blocked(): void {
		for ( $i = 0; $i < 10; $i++ ) {
			$this->limiter->is_allowed( '203.0.113.3' );
		}
		$this->assertFalse( $this->limiter->is_allowed( '203.0.113.3' ) );
	}

	/**
	 * Different IPs maintain independent buckets — one being limited does not
	 * affect another.
	 *
	 * @return void
	 */
	public function test_buckets_are_isolated_per_ip(): void {
		for ( $i = 0; $i < 10; $i++ ) {
			$this->limiter->is_allowed( '203.0.113.4' );
		}
		$this->assertFalse( $this->limiter->is_allowed( '203.0.113.4' ) );
		$this->assertTrue( $this->limiter->is_allowed( '203.0.113.5' ) );
	}

	/**
	 * `resolve_client_ip` falls back to REMOTE_ADDR when no trusted-proxy
	 * constant is defined.
	 *
	 * @return void
	 */
	public function test_resolve_client_ip_returns_remote_addr_without_trust_list(): void {
		$_SERVER['REMOTE_ADDR']          = '198.51.100.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 198.51.100.8';

		$this->assertSame( '198.51.100.7', Rate_Limiter::resolve_client_ip() );

		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * `resolve_client_ip` returns the empty string when REMOTE_ADDR is unset.
	 *
	 * @return void
	 */
	public function test_resolve_client_ip_returns_empty_when_no_remote_addr(): void {
		unset( $_SERVER['REMOTE_ADDR'] );
		$this->assertSame( '', Rate_Limiter::resolve_client_ip() );
	}
}
