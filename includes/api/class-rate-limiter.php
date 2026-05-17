<?php
/**
 * Rate limiter for REST API endpoints.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Enforces per-IP request limits using WordPress transients.
 */
class Rate_Limiter {

	/**
	 * Maximum requests allowed per window.
	 */
	private const MAX_REQUESTS = 10;

	/**
	 * Time window in seconds.
	 */
	private const WINDOW = 60;

	/**
	 * Check whether the given IP is allowed to make a request.
	 *
	 * Uses a fixed-window strategy: the window expiry is set on the first request
	 * and never extended, preventing sliding-window bypass.
	 *
	 * @param string $ip Client IP address.
	 *
	 * @return bool True if allowed, false if rate-limited.
	 */
	public function is_allowed( string $ip ): bool {
		$key  = 'ps_rl_' . md5( $ip );
		$data = get_transient( $key );

		if ( false === $data ) {
			set_transient(
				$key,
				array(
					'count'   => 1,
					'expires' => time() + self::WINDOW,
				),
				self::WINDOW
			);
			return true;
		}

		if ( $data['count'] >= self::MAX_REQUESTS ) {
			return false;
		}

		$remaining_ttl = max( 1, $data['expires'] - time() );
		set_transient(
			$key,
			array(
				'count'   => $data['count'] + 1,
				'expires' => $data['expires'],
			),
			$remaining_ttl
		);

		return true;
	}
}
