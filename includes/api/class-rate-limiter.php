<?php
/**
 * Rate limiter for REST API endpoints.
 *
 * @package Snopix
 */

namespace Snopix\Api;

use Snopix\Hooks\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Enforces per-IP request limits using WordPress transients.
 *
 * The per-window cap is read from the {@see Settings} option so admins can
 * tune it from the Settings tab. The window stays fixed at 60 seconds — short
 * enough to feel responsive, long enough to bucket burst behaviour.
 */
class Rate_Limiter {

	/**
	 * Time window in seconds.
	 */
	private const WINDOW = 60;

	/**
	 * Object-cache group for the atomic counter path.
	 */
	private const CACHE_GROUP = 'snopix_ratelimit';

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
		$cap = Settings::get_rate_limit();

		// With a persistent object cache, use an atomic counter so concurrent
		// requests can't each read the same count and overshoot the cap.
		if ( wp_using_ext_object_cache() ) {
			return $this->is_allowed_atomic( $ip, $cap );
		}

		return $this->is_allowed_transient( $ip, $cap );
	}

	/**
	 * Atomic fixed-window counter backed by the object cache. wp_cache_add seeds
	 * the window and its TTL; wp_cache_incr is atomic, so no increment is lost.
	 *
	 * @param string $ip  Client IP.
	 * @param int    $cap Per-window request cap.
	 *
	 * @return bool
	 */
	private function is_allowed_atomic( string $ip, int $cap ): bool {
		$key = self::transient_key( $ip );

		if ( wp_cache_add( $key, 1, self::CACHE_GROUP, self::WINDOW ) ) {
			return true;
		}

		$count = wp_cache_incr( $key, 1, self::CACHE_GROUP );
		if ( false === $count ) {
			// Key expired between the add and the incr — start a fresh window.
			wp_cache_add( $key, 1, self::CACHE_GROUP, self::WINDOW );
			return true;
		}

		return $count <= $cap;
	}

	/**
	 * Transient fallback used when no persistent object cache is configured.
	 * Best-effort read-modify-write: a burst can overshoot the cap slightly, but
	 * the fixed-window expiry is set on the first request and never extended.
	 *
	 * @param string $ip  Client IP.
	 * @param int    $cap Per-window request cap.
	 *
	 * @return bool
	 */
	private function is_allowed_transient( string $ip, int $cap ): bool {
		$key  = self::transient_key( $ip );
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

		if ( $data['count'] >= $cap ) {
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

	/**
	 * Resolve the effective client IP to limit on.
	 *
	 * `REMOTE_ADDR` is always the immediate peer — behind any reverse proxy
	 * that is the proxy itself, which would coalesce every visitor into one
	 * bucket. When the request is forwarded by a configured trusted proxy
	 * (constant `SNOPIX_TRUSTED_PROXIES`, a comma-separated list of IPs)
	 * we walk `X-Forwarded-For` right-to-left and return the first entry that
	 * is not in the trust list.
	 *
	 * @return string Client IP, or empty string when none can be determined.
	 */
	public static function resolve_client_ip(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] )
			? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) )
			: '';

		$trusted_raw = defined( 'SNOPIX_TRUSTED_PROXIES' ) ? (string) SNOPIX_TRUSTED_PROXIES : '';
		if ( '' === $trusted_raw || '' === $remote ) {
			return $remote;
		}

		$trusted = array_filter( array_map( 'trim', explode( ',', $trusted_raw ) ) );
		if ( ! in_array( $remote, $trusted, true ) ) {
			return $remote;
		}

		$forwarded = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
			: '';
		if ( '' === $forwarded ) {
			return $remote;
		}

		$chain = array_reverse( array_filter( array_map( 'trim', explode( ',', $forwarded ) ) ) );
		foreach ( $chain as $candidate ) {
			if ( ! in_array( $candidate, $trusted, true ) && filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}
		return $remote;
	}

	/**
	 * Build the transient key for an IP. Plugin-namespaced to avoid collisions
	 * with anything else writing to the `snopix_rl_*` space.
	 *
	 * @param string $ip Client IP.
	 *
	 * @return string
	 */
	private static function transient_key( string $ip ): string {
		return 'snopix_ratelimit_' . hash( 'sha256', $ip );
	}
}
