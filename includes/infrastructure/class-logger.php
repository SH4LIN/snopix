<?php
/**
 * Plugin-wide debug logger.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around error_log() that no-ops unless WP_DEBUG is on.
 *
 * Centralises the constant check + prefix so call sites stop scattering
 * `if ( defined( 'WP_DEBUG' ) && WP_DEBUG )` guards and the
 * `phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log`
 * suppression across the codebase.
 */
final class Logger {

	/**
	 * Prefix prepended to every line so log greps can isolate plugin output.
	 */
	private const PREFIX = '[Pixel Scout] ';

	/**
	 * Emit a debug line to the PHP error log if WP_DEBUG is enabled. No-op
	 * otherwise — call sites do not need to gate the call themselves.
	 *
	 * @param string $message Free-form message; do not include the plugin prefix.
	 *
	 * @return void
	 */
	public static function debug( string $message ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( self::PREFIX . $message );
	}

	/**
	 * Log an exception with class, message, and source location. Convenience
	 * wrapper for the common pattern in catch blocks.
	 *
	 * @param \Throwable $e       The caught exception.
	 * @param string     $context Free-form description of what was being attempted.
	 *
	 * @return void
	 */
	public static function exception( \Throwable $e, string $context ): void {
		self::debug(
			sprintf(
				'%s — %s: %s at %s:%d',
				$context,
				get_class( $e ),
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			)
		);
	}
}
