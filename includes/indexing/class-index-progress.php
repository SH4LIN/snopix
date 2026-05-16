<?php
/**
 * Bulk indexing progress tracker.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Indexing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Manages progress state for bulk indexing operations using transients.
 *
 * status values:
 *   idle    — no job running
 *   running — batches in flight
 *   done    — all batches completed
 */
class Index_Progress {
	private const KEY_DONE   = 'ps_bulk_progress';
	private const KEY_TOTAL  = 'ps_bulk_total';
	private const KEY_STATUS = 'ps_bulk_status';

	/**
	 * Get current progress state.
	 *
	 * @return array{done: int, total: int, status: string}
	 */
	public function get(): array {
		return [
			'done'   => (int) get_transient( self::KEY_DONE ),
			'total'  => (int) get_transient( self::KEY_TOTAL ),
			'status' => (string) ( get_transient( self::KEY_STATUS ) ?: 'idle' ),
		];
	}

	/**
	 * Set initial progress state and mark as running.
	 *
	 * @param int $done  Number of items completed.
	 * @param int $total Total number of items.
	 *
	 * @return void
	 */
	public function set( int $done, int $total ): void {
		set_transient( self::KEY_DONE, $done, DAY_IN_SECONDS );
		set_transient( self::KEY_TOTAL, $total, DAY_IN_SECONDS );
		set_transient( self::KEY_STATUS, 'running', DAY_IN_SECONDS );
	}

	/**
	 * Increment completed count. Transitions to 'done' when all items processed.
	 *
	 * @return void
	 */
	public function increment(): void {
		$done  = (int) get_transient( self::KEY_DONE ) + 1;
		$total = (int) get_transient( self::KEY_TOTAL );

		set_transient( self::KEY_DONE, $done, DAY_IN_SECONDS );

		if ( $total > 0 && $done >= $total ) {
			set_transient( self::KEY_STATUS, 'done', DAY_IN_SECONDS );
		}
	}

	/**
	 * Reset all progress state to idle.
	 *
	 * @return void
	 */
	public function reset(): void {
		delete_transient( self::KEY_DONE );
		delete_transient( self::KEY_TOTAL );
		delete_transient( self::KEY_STATUS );
	}
}
