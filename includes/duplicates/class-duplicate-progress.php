<?php
/**
 * Duplicate scan progress tracker.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Duplicates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Manages progress state for duplicate scan operations using transients.
 */
class Duplicate_Progress {
	private const KEY_DONE   = 'ps_dup_progress';
	private const KEY_TOTAL  = 'ps_dup_total';
	private const KEY_STATUS = 'ps_dup_status';

	/**
	 * Get current progress state.
	 *
	 * @return array{done: int, total: int, status: string}
	 */
	public function get(): array {
		$status = get_transient( self::KEY_STATUS );
		return array(
			'done'   => (int) get_transient( self::KEY_DONE ),
			'total'  => (int) get_transient( self::KEY_TOTAL ),
			'status' => false !== $status ? (string) $status : 'idle',
		);
	}

	/**
	 * Set initial progress state and mark as running.
	 *
	 * @param int $done  Items completed.
	 * @param int $total Total items.
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
