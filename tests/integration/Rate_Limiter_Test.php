<?php
/**
 * Integration tests for Snopix\Api\Rate_Limiter.
 *
 * Exercises the public is_allowed() API against a real WordPress transient
 * store (no persistent object cache in the test environment), verifying the
 * fixed-window cap, block-on-exceed, reset-after-delete, and key isolation
 * between two distinct IPs (the caller-side convention for logged-in vs
 * anonymous buckets).
 *
 * @package Snopix
 */

use Snopix\Api\Rate_Limiter;
use Snopix\Hooks\Settings;

/**
 * @covers \Snopix\Api\Rate_Limiter
 */
final class Rate_Limiter_Test extends Snopix_Integration_TestCase {

	/**
	 * The IP used as the "anonymous" bucket.
	 */
	private const IP_ANON = '203.0.113.1';

	/**
	 * A distinct IP used as the "logged-in user" bucket.
	 */
	private const IP_USER = '203.0.113.2';

	/**
	 * Low cap set for every test so we don't need to loop many times.
	 */
	private const CAP = 3;

	/** @var Rate_Limiter */
	private Rate_Limiter $limiter;

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Build the transient key the Rate_Limiter uses internally for a given IP.
	 *
	 * @param string $ip Client IP.
	 *
	 * @return string
	 */
	private static function transient_key( string $ip ): string {
		return 'snopix_ratelimit_' . hash( 'sha256', $ip );
	}

	/**
	 * Delete the rate-limit transient for an IP, simulating window expiry.
	 *
	 * @param string $ip Client IP.
	 *
	 * @return void
	 */
	private static function reset_window( string $ip ): void {
		delete_transient( self::transient_key( $ip ) );
	}

	// -----------------------------------------------------------------------
	// Fixtures
	// -----------------------------------------------------------------------

	public function set_up(): void {
		parent::set_up();

		// Set a low cap so tests can reach the limit quickly.
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::defaults(), array( 'rate_limit' => self::CAP ) )
		);

		$this->limiter = new Rate_Limiter();

		// Start each test with clean buckets.
		self::reset_window( self::IP_ANON );
		self::reset_window( self::IP_USER );
	}

	public function tear_down(): void {
		self::reset_window( self::IP_ANON );
		self::reset_window( self::IP_USER );
		delete_option( Settings::OPTION_NAME );
		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// Tests
	// -----------------------------------------------------------------------

	/**
	 * Requests up to the cap (inclusive) must all be allowed.
	 */
	public function test_requests_under_cap_are_allowed(): void {
		for ( $i = 1; $i <= self::CAP; $i++ ) {
			$this->assertTrue(
				$this->limiter->is_allowed( self::IP_ANON ),
				"Request {$i} of " . self::CAP . ' should be allowed.'
			);
		}
	}

	/**
	 * The request that pushes the count above the cap must be blocked.
	 */
	public function test_request_exceeding_cap_is_blocked(): void {
		// Exhaust the cap.
		for ( $i = 0; $i < self::CAP; $i++ ) {
			$this->limiter->is_allowed( self::IP_ANON );
		}

		$this->assertFalse(
			$this->limiter->is_allowed( self::IP_ANON ),
			'The request beyond the cap must be blocked.'
		);
	}

	/**
	 * After deleting the transient (window expiry simulation) a new window
	 * opens and requests are allowed again.
	 */
	public function test_allowed_again_after_window_reset(): void {
		// Fill the bucket.
		for ( $i = 0; $i < self::CAP; $i++ ) {
			$this->limiter->is_allowed( self::IP_ANON );
		}
		$this->assertFalse(
			$this->limiter->is_allowed( self::IP_ANON ),
			'Sanity: bucket should be exhausted before reset.'
		);

		// Simulate window expiry.
		self::reset_window( self::IP_ANON );

		$this->assertTrue(
			$this->limiter->is_allowed( self::IP_ANON ),
			'First request after window reset must be allowed.'
		);
	}

	/**
	 * Two distinct IPs use independent counters: exhausting one must not
	 * affect the other.
	 */
	public function test_different_ips_use_independent_buckets(): void {
		// Exhaust the anonymous bucket entirely.
		for ( $i = 0; $i < self::CAP; $i++ ) {
			$this->limiter->is_allowed( self::IP_ANON );
		}
		$this->assertFalse(
			$this->limiter->is_allowed( self::IP_ANON ),
			'Anonymous bucket should be exhausted.'
		);

		// The user bucket must still be open.
		$this->assertTrue(
			$this->limiter->is_allowed( self::IP_USER ),
			'User bucket must not share state with the anonymous bucket.'
		);
	}

	/**
	 * Both buckets can be exhausted independently — confirms isolation is
	 * symmetric (neither direction bleeds into the other).
	 */
	public function test_both_buckets_enforce_their_own_cap(): void {
		// Exhaust both buckets.
		for ( $i = 0; $i < self::CAP; $i++ ) {
			$this->limiter->is_allowed( self::IP_ANON );
			$this->limiter->is_allowed( self::IP_USER );
		}

		$this->assertFalse(
			$this->limiter->is_allowed( self::IP_ANON ),
			'Anonymous bucket must be blocked after exhaustion.'
		);
		$this->assertFalse(
			$this->limiter->is_allowed( self::IP_USER ),
			'User bucket must be blocked after exhaustion.'
		);
	}

	/**
	 * A rate_limit of 1 means exactly one request allowed, the next blocked.
	 */
	public function test_cap_of_one_allows_exactly_one_request(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::defaults(), array( 'rate_limit' => 1 ) )
		);

		$this->assertTrue(
			$this->limiter->is_allowed( self::IP_ANON ),
			'The sole allowed request must pass.'
		);
		$this->assertFalse(
			$this->limiter->is_allowed( self::IP_ANON ),
			'The second request must be blocked when cap is 1.'
		);
	}

	/**
	 * get_rate_limit() returns the value stored in the option.
	 */
	public function test_settings_get_rate_limit_reflects_option(): void {
		$this->assertSame( self::CAP, Settings::get_rate_limit() );
	}
}
