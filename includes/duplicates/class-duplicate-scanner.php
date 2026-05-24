<?php
/**
 * Duplicate scan orchestrator.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Duplicates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PixelScout\Repository\Index_Repository;
use PixelScout\Infrastructure\Action_Scheduler;

/**
 * Schedules and executes duplicate detection as a background WP-Cron job.
 *
 * The pairwise comparison is intentionally cooperative: each cron tick processes
 * as many outer-loop rows as it can within `BATCH_BUDGET_SECONDS`, persists its
 * union-find state to a transient, and reschedules itself until every row has
 * been processed. This keeps any single PHP request well clear of WP-Cron / fastcgi
 * timeouts on libraries with thousands of images.
 */
class Duplicate_Scanner {

	/**
	 * Cron hook for the scan job.
	 */
	public const CRON_HOOK = 'ps_duplicate_scan';

	/**
	 * Cron hook for the daily trigger.
	 */
	public const DAILY_HOOK = 'ps_duplicate_daily';

	/**
	 * Option key for stored duplicate groups.
	 */
	private const RESULTS_OPTION = 'ps_duplicate_results';

	/**
	 * Option key for last scan timestamp.
	 */
	private const LAST_SCANNED_OPTION = 'ps_duplicate_last_scanned';

	/**
	 * Transient that holds the cross-batch union-find state.
	 */
	private const STATE_TRANSIENT = 'ps_duplicate_scan_state';

	/**
	 * Soft per-tick wall-clock budget. Once this is exceeded the scanner
	 * persists state and reschedules itself rather than continuing.
	 */
	private const BATCH_BUDGET_SECONDS = 20.0;

	/**
	 * Constructor.
	 *
	 * @param Index_Repository   $repository Index repository.
	 * @param Duplicate_Finder   $finder     Duplicate finder.
	 * @param Duplicate_Progress $progress   Progress tracker.
	 * @param Action_Scheduler   $scheduler  Action scheduler.
	 */
	public function __construct(
		private Index_Repository $repository,
		private Duplicate_Finder $finder,
		private Duplicate_Progress $progress,
		private Action_Scheduler $scheduler
	) {}

	/**
	 * Schedule a fresh duplicate scan. Discards any in-flight state so the
	 * next run starts from a clean cursor.
	 *
	 * @return void
	 */
	public function schedule(): void {
		$this->scheduler->cancel_all( self::CRON_HOOK );
		delete_transient( self::STATE_TRANSIENT );
		$this->progress->reset();
		$this->progress->set( 0, 1 );
		$this->scheduler->schedule( self::CRON_HOOK, array(), 0 );
	}

	/**
	 * Cancel any in-flight scan: clear the cron chain, drop the cross-batch
	 * state transient, and reset the progress envelope to idle.
	 *
	 * @return void
	 */
	public function abort(): void {
		$this->scheduler->cancel_all( self::CRON_HOOK );
		delete_transient( self::STATE_TRANSIENT );
		$this->progress->reset();
	}

	/**
	 * Execute one batch of the duplicate scan and either reschedule the next
	 * batch or finalise the results.
	 *
	 * The full row list is loaded once on the first tick and snapshotted into
	 * the state transient so subsequent ticks reuse it. This avoids re-running
	 * an O(N) DB read + N-row materialisation on every tick of a long scan,
	 * which at 10k+ rows would otherwise dominate runtime and memory.
	 *
	 * @return void
	 */
	public function run(): void {
		$state = $this->load_state();

		$rows = $state['rows'];
		$n    = count( $rows );

		if ( $n < 2 ) {
			$this->finalise( array() );
			return;
		}

		$cursor  = (int) $state['cursor'];
		$parents = $state['parents'];

		$start = microtime( true );

		while ( $cursor < $n ) {
			$row_a   = $rows[ $cursor ];
			$phash_a = (string) ( $row_a['phash'] ?? '' );

			if ( '' !== $phash_a ) {
				for ( $j = $cursor + 1; $j < $n; $j++ ) {
					$row_b   = $rows[ $j ];
					$phash_b = (string) ( $row_b['phash'] ?? '' );

					if ( '' === $phash_b ) {
						continue;
					}

					$dist = $this->finder->hamming_distance_for_scanner( $phash_a, $phash_b );

					if ( $dist <= Duplicate_Finder::scanner_phash_threshold() ) {
						$this->union(
							$parents,
							(int) $row_a['attachment_id'],
							(int) $row_b['attachment_id']
						);
					}
				}
			}

			++$cursor;

			if ( ( microtime( true ) - $start ) >= self::BATCH_BUDGET_SECONDS ) {
				break;
			}
		}

		// Update progress once per tick rather than per row — collapses N
		// three-transient writes into a single envelope write.
		$this->progress->set( $cursor, $n );

		if ( $cursor >= $n ) {
			$groups = $this->collect_groups( $rows, $parents );
			$this->finalise( $groups );
			return;
		}

		set_transient(
			self::STATE_TRANSIENT,
			array(
				'cursor'  => $cursor,
				'parents' => $parents,
				'rows'    => $rows,
			),
			DAY_IN_SECONDS
		);
		$this->scheduler->schedule( self::CRON_HOOK, array(), 1 );
	}

	/**
	 * Get stored duplicate groups.
	 *
	 * @return array<int, array{match_type: string, ids: array<int>}>
	 */
	public function get_results(): array {
		$json   = get_option( self::RESULTS_OPTION, '[]' );
		$groups = json_decode( (string) $json, true );
		return is_array( $groups ) ? $groups : array();
	}

	/**
	 * Get last scan timestamp.
	 *
	 * @return string MySQL datetime or empty string.
	 */
	public function get_last_scanned(): string {
		return (string) get_option( self::LAST_SCANNED_OPTION, '' );
	}

	/**
	 * Load the cross-batch state from the transient, or initialise it by
	 * snapshotting the current row set on the first tick. The snapshot is
	 * intentional: rows added after the scan started should not change the
	 * pairwise work plan mid-run.
	 *
	 * @return array{cursor: int, parents: array<int, int>, rows: array<int, array<string, mixed>>}
	 */
	private function load_state(): array {
		$state = get_transient( self::STATE_TRANSIENT );
		if ( is_array( $state ) && isset( $state['cursor'], $state['parents'], $state['rows'] ) ) {
			return $state;
		}

		$rows    = $this->repository->get_all_with_hash();
		$parents = array();
		foreach ( $rows as $row ) {
			$id             = (int) $row['attachment_id'];
			$parents[ $id ] = $id;
		}

		return array(
			'cursor'  => 0,
			'parents' => $parents,
			'rows'    => $rows,
		);
	}

	/**
	 * Build the result groups (exact + perceptual) from the union-find state.
	 *
	 * @param array<int, array<string, mixed>> $rows    Indexed rows.
	 * @param array<int, int>                  $parents Union-find parent map.
	 *
	 * @return array<int, array{match_type: string, ids: array<int>}>
	 */
	private function collect_groups( array $rows, array $parents ): array {
		$exact_groups = $this->finder->scanner_group_by_file_hash( $rows );
		$exact_ids    = array();
		foreach ( $exact_groups as $group ) {
			foreach ( $group as $row ) {
				$exact_ids[ (int) $row['attachment_id'] ] = true;
			}
		}

		$perceptual_groups = array();
		foreach ( $rows as $row ) {
			$id = (int) $row['attachment_id'];
			if ( isset( $exact_ids[ $id ] ) ) {
				continue;
			}
			if ( '' === (string) ( $row['phash'] ?? '' ) ) {
				continue;
			}
			$root                          = $this->find_root( $parents, $id );
			$perceptual_groups[ $root ][] = $row;
		}

		$result = array();
		foreach ( $exact_groups as $group ) {
			$result[] = array(
				'match_type' => 'exact',
				'ids'        => array_values( array_map( static fn( $r ) => (int) $r['attachment_id'], $group ) ),
			);
		}
		foreach ( $perceptual_groups as $group ) {
			if ( count( $group ) < 2 ) {
				continue;
			}
			$result[] = array(
				'match_type' => 'perceptual',
				'ids'        => array_values( array_map( static fn( $r ) => (int) $r['attachment_id'], $group ) ),
			);
		}
		return $result;
	}

	/**
	 * Persist the groups, mark the scan complete, and clear cross-batch state.
	 *
	 * @param array<int, array{match_type: string, ids: array<int>}> $groups Final result groups.
	 *
	 * @return void
	 */
	private function finalise( array $groups ): void {
		update_option( self::RESULTS_OPTION, wp_json_encode( $groups ), false );
		update_option( self::LAST_SCANNED_OPTION, current_time( 'mysql' ), false );
		delete_transient( self::STATE_TRANSIENT );
		$this->progress->mark_done();
	}

	/**
	 * Union-find find-with-path-compression.
	 *
	 * @param array<int, int> $parents Parent map (passed by reference).
	 * @param int             $id      Node ID.
	 *
	 * @return int Root ID.
	 */
	private function find_root( array &$parents, int $id ): int {
		if ( ! isset( $parents[ $id ] ) ) {
			$parents[ $id ] = $id;
			return $id;
		}
		while ( $parents[ $id ] !== $id ) {
			$parents[ $id ] = $parents[ $parents[ $id ] ] ?? $parents[ $id ];
			$id             = $parents[ $id ];
		}
		return $id;
	}

	/**
	 * Union-find union (size-agnostic — fine for our scale).
	 *
	 * @param array<int, int> $parents Parent map (passed by reference).
	 * @param int             $a       First node.
	 * @param int             $b       Second node.
	 *
	 * @return void
	 */
	private function union( array &$parents, int $a, int $b ): void {
		$ra = $this->find_root( $parents, $a );
		$rb = $this->find_root( $parents, $b );
		if ( $ra !== $rb ) {
			$parents[ $ra ] = $rb;
		}
	}
}
