<?php
/**
 * Bulk indexing progress tracker.
 *
 * @package Pixel_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages progress state for bulk indexing operations using transients.
 */
class Pixel_Scout_Index_Progress {
	/**
	 * Transient key for items completed.
	 */
	private const KEY_DONE = 'ps_bulk_progress';

	/**
	 * Transient key for total items.
	 */
	private const KEY_TOTAL = 'ps_bulk_total';

	/**
	 * Get current progress state.
	 *
	 * @return array<string, int>
	 */
	public function get(): array {
		return [
			'done'  => (int) get_transient( self::KEY_DONE ),
			'total' => (int) get_transient( self::KEY_TOTAL ),
		];
	}

	/**
	 * Set progress state.
	 *
	 * @param int $done Number of items completed.
	 * @param int $total Total number of items.
	 *
	 * @return void
	 */
	public function set( int $done, int $total ): void {
		set_transient( self::KEY_DONE, $done, DAY_IN_SECONDS );
		set_transient( self::KEY_TOTAL, $total, DAY_IN_SECONDS );
	}

	/**
	 * Increment completed count by 1.
	 *
	 * @return void
	 */
	public function increment(): void {
		$current = (int) get_transient( self::KEY_DONE );
		set_transient( self::KEY_DONE, $current + 1, DAY_IN_SECONDS );
	}

	/**
	 * Reset all progress state.
	 *
	 * @return void
	 */
	public function reset(): void {
		delete_transient( self::KEY_DONE );
		delete_transient( self::KEY_TOTAL );
	}
}
