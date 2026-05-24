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
 * Manages progress state for duplicate scan operations using a single
 * transient envelope. The single-key layout mirrors Index_Progress and
 * guarantees that cron ticks always observe a consistent (done, total,
 * status) triple — there is no window where, say, status='running' but
 * done/total are stale from a previous run.
 *
 * Status values:
 *   idle    — no scan running.
 *   running — scan in flight.
 *   done    — scan completed.
 */
class Duplicate_Progress {

	/**
	 * Single envelope key.
	 */
	private const KEY = 'ps_dup_progress_state';

	/**
	 * Legacy key constants kept for cleanup on uninstall / upgrade only.
	 */
	private const LEGACY_KEY_DONE   = 'ps_dup_progress';
	private const LEGACY_KEY_TOTAL  = 'ps_dup_total';
	private const LEGACY_KEY_STATUS = 'ps_dup_status';

	/**
	 * Get current progress state. Missing transient returns the idle sentinel
	 * so callers can never confuse "no state" with "legitimate zero".
	 *
	 * @return array{done: int, total: int, status: string}
	 */
	public function get(): array {
		$state = get_transient( self::KEY );
		if ( ! is_array( $state ) ) {
			return array(
				'done'   => 0,
				'total'  => 0,
				'status' => 'idle',
			);
		}
		return array(
			'done'   => isset( $state['done'] ) ? (int) $state['done'] : 0,
			'total'  => isset( $state['total'] ) ? (int) $state['total'] : 0,
			'status' => isset( $state['status'] ) ? (string) $state['status'] : 'idle',
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
		$this->write(
			array(
				'done'   => $done,
				'total'  => $total,
				'status' => 'running',
			)
		);
	}

	/**
	 * Increment completed count by 1. Transitions to `done` when full.
	 *
	 * @return void
	 */
	public function increment(): void {
		$state         = $this->get();
		$state['done'] = $state['done'] + 1;
		if ( $state['total'] > 0 && $state['done'] >= $state['total'] ) {
			$state['status'] = 'done';
		}
		$this->write( $state );
	}

	/**
	 * Reset all progress state to idle. Also clears the legacy three-transient
	 * layout so an upgrade from 0.0.x leaves no orphaned keys.
	 *
	 * @return void
	 */
	public function reset(): void {
		delete_transient( self::KEY );
		delete_transient( self::LEGACY_KEY_DONE );
		delete_transient( self::LEGACY_KEY_TOTAL );
		delete_transient( self::LEGACY_KEY_STATUS );
	}

	/**
	 * Mark the scan as fully complete regardless of the internal counters.
	 * Called by the scanner once every batch has executed.
	 *
	 * @return void
	 */
	public function mark_done(): void {
		$state           = $this->get();
		$state['done']   = max( 1, $state['total'] );
		$state['status'] = 'done';
		$this->write( $state );
	}

	/**
	 * Persist a state envelope to the transient store.
	 *
	 * @param array{done: int, total: int, status: string} $state State payload.
	 *
	 * @return void
	 */
	private function write( array $state ): void {
		set_transient( self::KEY, $state, DAY_IN_SECONDS );
	}
}
