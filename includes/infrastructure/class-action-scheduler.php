<?php
/**
 * Action scheduler — wrapper around WP-Cron for background processing.
 *
 * Abstracts wp_schedule_single_event so the bulk indexer is decoupled
 * from WP-Cron specifics. A future implementation could delegate to the
 * WooCommerce ActionScheduler library instead.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Thin wrapper around wp_schedule_single_event for background actions.
 */
class Action_Scheduler {

	/**
	 * Schedule a single background action.
	 *
	 * @param string            $hook  WP action hook to fire.
	 * @param array<int, mixed> $args  Arguments passed to the hook callback.
	 * @param int               $delay Seconds from now to fire (0 = immediately next cron run).
	 *
	 * @return bool True on success.
	 */
	public function schedule( string $hook, array $args, int $delay = 0 ): bool {
		return (bool) wp_schedule_single_event( time() + $delay, $hook, $args );
	}

	/**
	 * Cancel all pending events for a hook, regardless of args.
	 *
	 * @param string $hook WP action hook.
	 *
	 * @return void
	 */
	public function cancel_all( string $hook ): void {
		wp_clear_scheduled_hook( $hook );
	}

	/**
	 * Check whether any event is pending for a hook.
	 *
	 * @param string $hook WP action hook.
	 *
	 * @return bool
	 */
	public function has_pending( string $hook ): bool {
		return (bool) wp_next_scheduled( $hook );
	}
}
