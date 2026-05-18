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
	 * Schedule a duplicate scan to run in the background.
	 *
	 * @return void
	 */
	public function schedule(): void {
		$this->scheduler->cancel_all( self::CRON_HOOK );
		$this->progress->reset();
		$this->progress->set( 0, 1 );
		$this->scheduler->schedule( self::CRON_HOOK, array(), 0 );
	}

	/**
	 * Execute the duplicate scan. Called by WP-Cron.
	 *
	 * @return void
	 */
	public function run(): void {
		$rows   = $this->repository->get_all_with_hash();
		$groups = $this->finder->find( $rows );

		update_option( self::RESULTS_OPTION, wp_json_encode( $groups ), false );
		update_option( self::LAST_SCANNED_OPTION, current_time( 'mysql' ), false );

		$this->progress->increment();
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
}
