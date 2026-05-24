<?php
/**
 * Background regenerator for WordPress image subsizes.
 *
 * Mirrors Bulk_Indexer's chained-cron pattern but uses an OFFSET-BASED queue
 * (constant-size transient envelope) instead of materializing every attachment
 * ID into a single blob. The blob approach silently overflows the 1MB
 * Memcached slab on large media libraries (council Performance HIGH#1).
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Imaging;

use Closure;
use PixelScout\Indexing\Index_Progress;
use PixelScout\Infrastructure\Action_Scheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two entry points:
 *   - schedule_missing(): backfill only missing subsizes per attachment.
 *   - schedule_all(): full rebuild + snapshot acknowledgement.
 *
 * Queue envelope shape: { mode: string, offset: int, total: int }.
 * `process_batch()` queries the next slice via the injected `slice_provider`
 * Closure each cron tick.
 */
class Subsize_Regenerator {

	public const BATCH_SIZE  = 50;
	public const BATCH_DELAY = 60;
	public const CRON_HOOK   = 'ps_regen_batch';
	public const PENDING_KEY = 'ps_regen_pending';

	public const MODE_MISSING = 'missing';
	public const MODE_ALL     = 'all';

	/**
	 * Constructor.
	 *
	 * @param Image_Subsize_Service $service          Subsize generator.
	 * @param Subsize_Watcher       $watcher          Snapshot manager.
	 * @param Index_Progress        $progress         Progress envelope (keyed `ps_regen_progress_state`).
	 * @param Action_Scheduler      $scheduler        WP-Cron wrapper.
	 * @param Closure               $count_provider   () => int  — total image-attachment count.
	 * @param Closure               $slice_provider   (int $offset, int $size) => int[] — next slice of attachment IDs.
	 */
	public function __construct(
		private Image_Subsize_Service $service,
		private Subsize_Watcher $watcher,
		private Index_Progress $progress,
		private Action_Scheduler $scheduler,
		private Closure $count_provider,
		private Closure $slice_provider
	) {}

	/**
	 * Schedule a missing-only run.
	 *
	 * @return int Total attachments queued.
	 */
	public function schedule_missing(): int {
		return $this->schedule( self::MODE_MISSING );
	}

	/**
	 * Schedule a full rebuild + acknowledge the snapshot.
	 *
	 * @return int Total attachments queued.
	 */
	public function schedule_all(): int {
		$count = $this->schedule( self::MODE_ALL );
		if ( $count > 0 ) {
			$this->watcher->acknowledge();
		}
		return $count;
	}

	/**
	 * Cron callback: process the next slice from the envelope and chain the
	 * following batch if more work remains. Caches the registered-subsize map
	 * once at the top of the batch so per-attachment missing checks don't
	 * re-resolve the WordPress registry.
	 *
	 * @return void
	 */
	public function process_batch(): void {
		$env = get_transient( self::PENDING_KEY );
		if ( ! is_array( $env ) || ! isset( $env['mode'], $env['offset'], $env['total'] ) ) {
			return;
		}

		$mode   = (string) $env['mode'];
		$offset = (int) $env['offset'];
		$total  = (int) $env['total'];

		if ( $offset >= $total ) {
			delete_transient( self::PENDING_KEY );
			return;
		}

		$registered = wp_get_registered_image_subsizes();
		$batch_ids  = array_map( 'intval', ( $this->slice_provider )( $offset, self::BATCH_SIZE ) );

		if ( empty( $batch_ids ) ) {
			delete_transient( self::PENDING_KEY );
			return;
		}

		_prime_post_caches( $batch_ids, true, true );

		$succeeded = 0;
		foreach ( $batch_ids as $id ) {
			$ok = false;
			if ( self::MODE_ALL === $mode ) {
				$ok = $this->service->create_all( $id );
			} else {
				$missing = $this->service->missing_sizes( $id, $registered );
				$ok      = $this->service->create_subset( $id, $missing );
			}
			if ( $ok ) {
				++$succeeded;
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( '[Pixel Scout] regen failed for attachment %d (mode=%s)', $id, $mode ) );
			}
		}

		$processed     = count( $batch_ids );
		$new_offset    = $offset + $processed;
		$has_remaining = $new_offset < $total;

		$this->progress->increment_by( $processed );

		if ( $has_remaining && 0 === $succeeded ) {
			delete_transient( self::PENDING_KEY );
			$this->progress->mark_stalled();
			return;
		}

		if ( $has_remaining ) {
			set_transient(
				self::PENDING_KEY,
				array(
					'mode'   => $mode,
					'offset' => $new_offset,
					'total'  => $total,
				),
				DAY_IN_SECONDS
			);
			$this->scheduler->schedule( self::CRON_HOOK, array(), self::BATCH_DELAY );
		} else {
			delete_transient( self::PENDING_KEY );
		}
	}

	/**
	 * Common scheduler: persist {mode,offset:0,total}, kick off first batch.
	 *
	 * @param string $mode self::MODE_MISSING or self::MODE_ALL.
	 *
	 * @return int Total attachments queued.
	 */
	private function schedule( string $mode ): int {
		$total = (int) ( $this->count_provider )();
		if ( $total <= 0 ) {
			return 0;
		}

		$this->scheduler->cancel_all( self::CRON_HOOK );
		$this->progress->reset();
		$this->progress->set( 0, $total );

		set_transient(
			self::PENDING_KEY,
			array(
				'mode'   => $mode,
				'offset' => 0,
				'total'  => $total,
			),
			DAY_IN_SECONDS
		);
		$this->scheduler->schedule( self::CRON_HOOK, array(), 0 );

		return $total;
	}
}
