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
 * State is stored as a single associative array so callers can tell the
 * difference between "no job has ever run" (transient missing) and "a job
 * legitimately completed with zero items" (status=done, total=0).
 *
 * Status values:
 *   idle    — no job running.
 *   running — batches in flight.
 *   done    — all batches completed.
 *   stalled — chain aborted because every image in a batch failed.
 */
class Index_Progress {

	/**
	 * Default transient key — preserved for back-compat so Bulk_Indexer
	 * keeps writing to the same envelope it always has.
	 */
	public const DEFAULT_KEY = 'ps_bulk_progress_state';

	/**
	 * Transient key for this progress envelope.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Constructor.
	 *
	 * @param string $transient_key Per-caller transient key. Defaults to
	 *                              `ps_bulk_progress_state` for callers that
	 *                              were written against the v1 signature.
	 */
	public function __construct( string $transient_key = self::DEFAULT_KEY ) {
		$this->key = $transient_key;
	}

	/**
	 * Get current progress state. A missing transient returns the `idle`
	 * sentinel so callers cannot confuse "no state" with "legitimate zero".
	 *
	 * @return array{done: int, total: int, status: string}
	 */
	public function get(): array {
		$state = get_transient( $this->key );
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
		$this->increment_by( 1 );
	}

	/**
	 * Bulk-increment the completed count. Cheaper than N single increments
	 * when processing a batch.
	 *
	 * @param int $count Number of items to add to `done`.
	 *
	 * @return void
	 */
	public function increment_by( int $count ): void {
		if ( $count <= 0 ) {
			return;
		}
		$state         = $this->get();
		$state['done'] = $state['done'] + $count;
		if ( $state['total'] > 0 && $state['done'] >= $state['total'] ) {
			$state['status'] = 'done';
		}
		$this->write( $state );
	}

	/**
	 * Reset all progress state to idle.
	 *
	 * @return void
	 */
	public function reset(): void {
		delete_transient( $this->key );
	}

	/**
	 * Force the status to `stalled`. Called by the bulk indexer when an
	 * entire batch fails and continuing would just burn through the queue.
	 *
	 * @return void
	 */
	public function mark_stalled(): void {
		$state           = $this->get();
		$state['status'] = 'stalled';
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
		set_transient( $this->key, $state, DAY_IN_SECONDS );
	}
}
