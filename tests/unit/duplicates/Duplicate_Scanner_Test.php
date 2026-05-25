<?php
/**
 * Tests for Duplicate_Scanner cross-batch orchestration.
 *
 * @package Snopix
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use Snopix\Duplicates\Duplicate_Finder;
use Snopix\Duplicates\Duplicate_Progress;
use Snopix\Duplicates\Duplicate_Scanner;
use Snopix\Imaging\Similarity;
use Snopix\Infrastructure\Action_Scheduler;
use Snopix\Repository\Index_Repository;
use Snopix\Repository\Schema;

/**
 * Duplicate_Scanner unit tests.
 */
class Snopix_Duplicate_Scanner_Test extends Snopix_TestCase {

	private Index_Repository $repo;

	/**
	 * Boot a real repository against the test DB so `get_all_with_hash` returns
	 * realistic rows.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Schema() )->install();
		$this->repo = new Index_Repository( $wpdb );
		delete_option( 'snopix_duplicate_results' );
		delete_option( 'snopix_duplicate_last_scanned' );
		delete_transient( 'snopix_duplicate_scan_state' );
		( new Duplicate_Progress() )->reset();
	}

	/**
	 * Clean up persisted state.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( 'snopix_duplicate_results' );
		delete_option( 'snopix_duplicate_last_scanned' );
		delete_transient( 'snopix_duplicate_scan_state' );
		( new Duplicate_Progress() )->reset();
		parent::tearDown();
	}

	/**
	 * Seed a fingerprint row into the index table.
	 *
	 * @param int    $id         Attachment ID.
	 * @param string $phash      pHash hex (16 chars).
	 * @param string $file_hash  File hash.
	 *
	 * @return void
	 */
	private function seed( int $id, string $phash, string $file_hash ): void {
		$this->repo->upsert(
			$id,
			array(
				'phash'        => $phash,
				'color_vector' => wp_json_encode( array_fill( 0, 48, 0.5 ) ),
				'edge_vector'  => wp_json_encode( array_fill( 0, 32, 0.5 ) ),
				'file_hash'    => $file_hash,
				'mime_type'    => 'image/jpeg',
			)
		);
	}

	/**
	 * Build a scanner using real finder + supplied scheduler.
	 *
	 * @param Action_Scheduler|null $scheduler Optional mock scheduler.
	 *
	 * @return Duplicate_Scanner
	 */
	private function scanner( ?Action_Scheduler $scheduler = null ): Duplicate_Scanner {
		return new Duplicate_Scanner(
			$this->repo,
			new Duplicate_Finder( new Similarity() ),
			new Duplicate_Progress(),
			$scheduler ?? new Action_Scheduler()
		);
	}

	/**
	 * `schedule` clears state and queues the first batch.
	 *
	 * @return void
	 */
	public function test_schedule_queues_first_batch(): void {
		set_transient( 'snopix_duplicate_scan_state', array( 'cursor' => 5 ), HOUR_IN_SECONDS );

		$scheduler = $this->createMock( Action_Scheduler::class );
		$scheduler->expects( $this->once() )->method( 'cancel_all' )->with( Duplicate_Scanner::CRON_HOOK );
		$scheduler->expects( $this->once() )
			->method( 'schedule' )
			->with( Duplicate_Scanner::CRON_HOOK, array(), 0 );

		$this->scanner( $scheduler )->schedule();

		$this->assertFalse( get_transient( 'snopix_duplicate_scan_state' ) );
	}

	/**
	 * `run` with fewer than two rows finalises immediately with no groups.
	 *
	 * @return void
	 */
	public function test_run_with_no_rows_finalises_empty(): void {
		$this->scanner()->run();
		$this->assertSame( array(), $this->scanner()->get_results() );
		$this->assertSame( 'done', ( new Duplicate_Progress() )->get()['status'] );
	}

	/**
	 * Identical pHashes plus distinct file_hashes produce a perceptual group.
	 *
	 * @return void
	 */
	public function test_run_records_perceptual_group(): void {
		$this->seed( 1, '0000000000000000', 'h1' );
		$this->seed( 2, '0000000000000000', 'h2' );

		$this->scanner()->run();
		$results = $this->scanner()->get_results();

		$this->assertCount( 1, $results );
		$this->assertSame( 'perceptual', $results[0]['match_type'] );
		sort( $results[0]['ids'] );
		$this->assertSame( array( 1, 2 ), $results[0]['ids'] );
	}

	/**
	 * Matching file_hashes produce an exact group regardless of pHash.
	 *
	 * @return void
	 */
	public function test_run_records_exact_group(): void {
		$this->seed( 10, '0000000000000000', 'same' );
		$this->seed( 11, 'ffffffffffffffff', 'same' );

		$this->scanner()->run();
		$results = $this->scanner()->get_results();

		$this->assertCount( 1, $results );
		$this->assertSame( 'exact', $results[0]['match_type'] );
	}

	/**
	 * After a successful run, `get_last_scanned` is non-empty.
	 *
	 * @return void
	 */
	public function test_last_scanned_populated_after_run(): void {
		$this->seed( 1, 'aaaaaaaaaaaaaaaa', 'a' );
		$this->seed( 2, 'aaaaaaaaaaaaaaaa', 'b' );

		$this->scanner()->run();
		$this->assertNotEmpty( $this->scanner()->get_last_scanned() );
	}
}
