<?php
/**
 * Integration tests for Snopix\Api\REST_Controller.
 *
 * Boots a real WordPress + DB environment (transaction-rolled-back per test),
 * wires the full service graph identical to Plugin::register_rest_routes(),
 * and dispatches requests through rest_do_request() so every middleware layer
 * (permission callbacks, REST server argument sanitisation, rate limiter) runs.
 *
 * Covered:
 *   - GET  snopix/v1/status         — shape + HTTP 200
 *   - GET  snopix/v1/progress        — shape + HTTP 200
 *   - GET  snopix/v1/images          — shape + HTTP 200
 *   - GET  snopix/v1/settings        — shape + HTTP 200
 *   - POST snopix/v1/settings        — merge persists + HTTP 200
 *   - POST snopix/v1/reindex         — schedules a job, HTTP 200
 *   - POST snopix/v1/reset-progress  — resets to idle, HTTP 200
 *   - DELETE snopix/v1/index/{id}    — removes row, HTTP 200 / 404
 *   - POST snopix/v1/search          — permission gating by visibility setting
 *   - POST snopix/v1/search          — rate limiting returns 429 when exhausted
 *
 * @package Snopix
 */

use Snopix\Api\{REST_Controller, Rate_Limiter};
use Snopix\Hooks\Settings;
use Snopix\Imaging\{GD_Loader, PHash_Processor, Color_Processor, Edge_Processor, Similarity};
use Snopix\Indexing\{Bulk_Indexer, Image_Indexer, Index_Progress, Mime_Validator};
use Snopix\Infrastructure\{Action_Scheduler};
use Snopix\Repository\Index_Repository;
use Snopix\Search\{Fingerprint_Factory, Query_Image, Score_Calculator, Search_Pipeline};

/**
 * @covers \Snopix\Api\REST_Controller
 */
final class REST_Controller_Test extends Snopix_Integration_TestCase {

	// -----------------------------------------------------------------------
	// Constants
	// -----------------------------------------------------------------------

	private const NS    = 'snopix/v1';
	private const IP    = '203.0.113.42';

	// -----------------------------------------------------------------------
	// Properties
	// -----------------------------------------------------------------------

	/** @var REST_Controller */
	private REST_Controller $controller;

	/** @var Index_Repository */
	private Index_Repository $repository;

	/** @var Image_Indexer */
	private Image_Indexer $indexer;

	/** @var Index_Progress */
	private Index_Progress $progress;

	/** @var int Admin user ID. */
	private int $admin_id;

	// -----------------------------------------------------------------------
	// Lifecycle
	// -----------------------------------------------------------------------

	public function set_up(): void {
		parent::set_up();

		global $wpdb;

		// Build the same service graph as Plugin::register_rest_routes().
		$this->repository = new Index_Repository( $wpdb );
		$similarity       = new Similarity();
		$loader           = new GD_Loader();
		$factory          = new Fingerprint_Factory(
			$loader,
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);
		$calculator    = new Score_Calculator( $similarity );
		$pipeline      = new Search_Pipeline( $this->repository, $factory, $calculator );
		$validator     = new Mime_Validator();
		$this->indexer = new Image_Indexer( $validator, $factory, $this->repository );
		$this->progress = new Index_Progress();
		$bulk_indexer   = new Bulk_Indexer(
			$this->repository,
			$this->indexer,
			$this->progress,
			new Action_Scheduler()
		);

		$this->controller = new REST_Controller(
			$pipeline,
			new Query_Image(),
			$this->repository,
			$bulk_indexer,
			$this->progress,
			new Rate_Limiter()
		);

		// Register routes fresh for this test.
		do_action( 'rest_api_init' );
		$this->controller->register_routes();

		// Create and log in an administrator.
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );

		// Reset settings to defaults.
		delete_option( Settings::OPTION_NAME );

		// Clean any leftover rate-limit transient for our test IP.
		$this->reset_rate_limit_window( self::IP );
	}

	public function tear_down(): void {
		$this->reset_rate_limit_window( self::IP );
		delete_option( Settings::OPTION_NAME );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Dispatch a REST request and return the response.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  Full REST route, e.g. '/snopix/v1/status'.
	 * @param array<string, mixed> $body   JSON body params for POST requests.
	 *
	 * @return WP_REST_Response
	 */
	private function dispatch( string $method, string $route, array $body = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		if ( ! empty( $body ) ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		$response = rest_do_request( $request );
		// Unwrap WP_Error into a WP_REST_Response so callers can use get_status().
		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response( $response->get_error_data(), (int) ( $response->get_error_data()['status'] ?? 500 ) );
		}
		return rest_get_server()->response_to_data( $response, false ) ? $response : $response;
	}

	/**
	 * Dispatch a rate-limited search request from the test IP.
	 *
	 * Injects REMOTE_ADDR so Rate_Limiter::resolve_client_ip() returns self::IP,
	 * then fires an empty file upload (which will fail at the 'no_file' check —
	 * but the rate-limit check fires first).
	 *
	 * @return WP_REST_Response
	 */
	private function dispatch_search_for_rate_limit(): WP_REST_Response {
		$_SERVER['REMOTE_ADDR'] = self::IP;
		$request                = new WP_REST_Request( 'POST', '/' . self::NS . '/search' );
		$response               = rest_do_request( $request );
		return $response instanceof WP_REST_Response ? $response : new WP_REST_Response( array(), 500 );
	}

	/**
	 * Delete the transient that backs the rate-limit window for a given IP.
	 *
	 * @param string $ip Client IP.
	 *
	 * @return void
	 */
	private static function reset_rate_limit_window( string $ip ): void {
		delete_transient( 'snopix_ratelimit_' . hash( 'sha256', $ip ) );
	}

	/**
	 * Index a fixture attachment and return its ID.
	 *
	 * @param int $fixture_index 1-based fixture index.
	 *
	 * @return int Attachment ID.
	 */
	private function index_fixture( int $fixture_index ): int {
		$id = $this->attach_fixture( $fixture_index );
		$this->assertTrue(
			$this->indexer->index_single( $id ),
			"Fixture {$fixture_index} must index successfully."
		);
		return $id;
	}

	// -----------------------------------------------------------------------
	// GET /status
	// -----------------------------------------------------------------------

	/**
	 * GET /status returns HTTP 200 with the expected shape.
	 */
	public function test_status_returns_200_with_expected_shape(): void {
		$response = $this->dispatch( 'GET', '/' . self::NS . '/status' );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'indexed', $data );
		$this->assertArrayHasKey( 'failed', $data );
		$this->assertArrayHasKey( 'pending', $data );
		$this->assertArrayHasKey( 'progress', $data );
	}

	/**
	 * GET /status is forbidden for unauthenticated users.
	 */
	public function test_status_requires_admin(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/' . self::NS . '/status' );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * After indexing one fixture, /status reports at least 1 indexed image.
	 */
	public function test_status_indexed_count_increases_after_indexing(): void {
		$this->index_fixture( 1 );

		$response = $this->dispatch( 'GET', '/' . self::NS . '/status' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertGreaterThanOrEqual( 1, (int) $data['indexed'] );
	}

	// -----------------------------------------------------------------------
	// GET /progress
	// -----------------------------------------------------------------------

	/**
	 * GET /progress returns HTTP 200 with the expected shape.
	 */
	public function test_progress_returns_200_with_expected_shape(): void {
		$response = $this->dispatch( 'GET', '/' . self::NS . '/progress' );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'done', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'status', $data );
	}

	/**
	 * Fresh installation: progress reports status=idle.
	 */
	public function test_progress_idle_on_fresh_state(): void {
		$response = $this->dispatch( 'GET', '/' . self::NS . '/progress' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'idle', $data['status'] );
	}

	/**
	 * GET /progress is forbidden for unauthenticated users.
	 */
	public function test_progress_requires_admin(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/' . self::NS . '/progress' );
		$this->assertSame( 401, $response->get_status() );
	}

	// -----------------------------------------------------------------------
	// POST /reset-progress
	// -----------------------------------------------------------------------

	/**
	 * POST /reset-progress returns HTTP 200 with reset=true and clears progress.
	 */
	public function test_reset_progress_returns_200_and_resets_state(): void {
		// Put progress into a non-idle state.
		$this->progress->set( 5, 10 );

		$response = $this->dispatch( 'POST', '/' . self::NS . '/reset-progress' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'reset', $data );
		$this->assertTrue( $data['reset'] );

		// Verify the progress transient was actually cleared.
		$progress_response = $this->dispatch( 'GET', '/' . self::NS . '/progress' );
		$progress          = $progress_response->get_data();
		$this->assertSame( 'idle', $progress['status'] );
	}

	// -----------------------------------------------------------------------
	// GET /images
	// -----------------------------------------------------------------------

	/**
	 * GET /images returns HTTP 200 with an array payload.
	 */
	public function test_images_returns_200_array(): void {
		$response = $this->dispatch( 'GET', '/' . self::NS . '/images' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * After indexing a fixture, /images contains a row with the expected keys.
	 */
	public function test_images_contains_row_after_indexing(): void {
		$id = $this->index_fixture( 2 );

		$response = $this->dispatch( 'GET', '/' . self::NS . '/images' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		$row = $data[0];
		$this->assertArrayHasKey( 'attachment_id', $row );
		$this->assertArrayHasKey( 'phash', $row );
		$this->assertArrayHasKey( 'title', $row );
		$this->assertArrayHasKey( 'filename', $row );
		$this->assertArrayHasKey( 'thumbnail_url', $row );
	}

	/**
	 * GET /images is forbidden for unauthenticated users.
	 */
	public function test_images_requires_admin(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/' . self::NS . '/images' );
		$this->assertSame( 401, $response->get_status() );
	}

	// -----------------------------------------------------------------------
	// POST /reindex
	// -----------------------------------------------------------------------

	/**
	 * POST /reindex returns HTTP 200 with scheduled=true when no job is running.
	 */
	public function test_reindex_schedules_job_and_returns_200(): void {
		$response = $this->dispatch( 'POST', '/' . self::NS . '/reindex' );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'scheduled', $data );
		$this->assertTrue( $data['scheduled'] );
	}

	/**
	 * POST /reindex returns 409 when a bulk job is already running.
	 */
	public function test_reindex_returns_409_when_job_is_already_running(): void {
		// Put progress into running state.
		$this->progress->set( 0, 5 );

		$response = $this->dispatch( 'POST', '/' . self::NS . '/reindex' );
		$this->assertSame( 409, $response->get_status() );
	}

	/**
	 * POST /reindex is forbidden for unauthenticated users.
	 */
	public function test_reindex_requires_admin(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'POST', '/' . self::NS . '/reindex' );
		$this->assertSame( 401, $response->get_status() );
	}

	// -----------------------------------------------------------------------
	// DELETE /index/{id}
	// -----------------------------------------------------------------------

	/**
	 * DELETE /index/{id} removes an existing row and returns HTTP 200.
	 */
	public function test_delete_index_removes_existing_row(): void {
		$id = $this->index_fixture( 3 );

		$response = $this->dispatch( 'DELETE', '/' . self::NS . '/index/' . $id );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'deleted', $data );
		$this->assertTrue( $data['deleted'] );
	}

	/**
	 * DELETE /index/{id} returns 404 when the row does not exist.
	 */
	public function test_delete_index_returns_404_for_missing_row(): void {
		$response = $this->dispatch( 'DELETE', '/' . self::NS . '/index/999999' );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * DELETE /index/{id} is forbidden for unauthenticated users.
	 */
	public function test_delete_index_requires_admin(): void {
		$id = $this->index_fixture( 4 );
		wp_set_current_user( 0 );

		$response = $this->dispatch( 'DELETE', '/' . self::NS . '/index/' . $id );
		$this->assertSame( 401, $response->get_status() );
	}

	// -----------------------------------------------------------------------
	// GET /settings + POST /settings
	// -----------------------------------------------------------------------

	/**
	 * GET /settings returns HTTP 200 with all expected keys.
	 */
	public function test_get_settings_returns_200_with_expected_keys(): void {
		$response = $this->dispatch( 'GET', '/' . self::NS . '/settings' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		foreach ( array( 'search_visibility', 'rate_limit', 'match_threshold', 'batch_size', 'downscale_max', 'duplicate_threshold', 'drop_on_uninstall' ) as $key ) {
			$this->assertArrayHasKey( $key, $data, "Key '{$key}' missing from /settings response." );
		}
	}

	/**
	 * GET /settings is forbidden for unauthenticated users.
	 */
	public function test_get_settings_requires_admin(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/' . self::NS . '/settings' );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * POST /settings persists a changed rate_limit value and echoes it back.
	 */
	public function test_update_settings_persists_rate_limit(): void {
		$response = $this->dispatch( 'POST', '/' . self::NS . '/settings', array( 'rate_limit' => 5 ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'rate_limit', $data );
		$this->assertSame( 5, (int) $data['rate_limit'] );

		// Confirm it was persisted to the options table.
		$this->assertSame( 5, Settings::get_rate_limit() );
	}

	/**
	 * POST /settings persists search_visibility and echoes it back.
	 */
	public function test_update_settings_persists_search_visibility(): void {
		$response = $this->dispatch( 'POST', '/' . self::NS . '/settings', array( 'search_visibility' => 'logged_in' ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'logged_in', $data['search_visibility'] );

		$all = Settings::all();
		$this->assertSame( 'logged_in', $all['search_visibility'] );
	}

	// -----------------------------------------------------------------------
	// POST /search — visibility permission gating
	// -----------------------------------------------------------------------

	/**
	 * POST /search with visibility=anyone allows an unauthenticated request
	 * (it fails with 400 "no_file" — not 401 — because permission passed).
	 */
	public function test_search_visibility_anyone_allows_anonymous_request(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::defaults(), array( 'search_visibility' => 'anyone', 'rate_limit' => 60 ) )
		);

		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'POST', '/' . self::NS . '/search' );
		$response = rest_do_request( $request );

		// Permission callback passed — response must not be 401/403.
		$this->assertNotSame( 401, $response->get_status(), 'Anonymous request must not be blocked when visibility=anyone.' );
		$this->assertNotSame( 403, $response->get_status(), 'Anonymous request must not be forbidden when visibility=anyone.' );
	}

	/**
	 * POST /search with visibility=logged_in blocks an unauthenticated request.
	 */
	public function test_search_visibility_logged_in_blocks_anonymous_request(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::defaults(), array( 'search_visibility' => 'logged_in' ) )
		);

		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'POST', '/' . self::NS . '/search' );
		$response = rest_do_request( $request );

		$this->assertContains(
			$response->get_status(),
			array( 401, 403 ),
			'Anonymous request must be blocked when visibility=logged_in.'
		);
	}

	/**
	 * POST /search with visibility=logged_in allows a logged-in subscriber.
	 */
	public function test_search_visibility_logged_in_allows_authenticated_user(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::defaults(), array( 'search_visibility' => 'logged_in', 'rate_limit' => 60 ) )
		);

		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'POST', '/' . self::NS . '/search' );
		$response = rest_do_request( $request );

		// Permission passed — result is 400 (no file) or 429 (rate limited), not 401/403.
		$this->assertNotSame( 401, $response->get_status(), 'Logged-in user must not be blocked when visibility=logged_in.' );
		$this->assertNotSame( 403, $response->get_status(), 'Logged-in user must not be forbidden when visibility=logged_in.' );
	}

	// -----------------------------------------------------------------------
	// POST /search — rate limiting
	// -----------------------------------------------------------------------

	/**
	 * POST /search returns 429 once the per-window cap is exhausted.
	 *
	 * Sets rate_limit=1 so we can verify the block with a single extra request
	 * without looping many times.
	 */
	public function test_search_rate_limit_returns_429_after_cap_exhausted(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::defaults(), array( 'search_visibility' => 'anyone', 'rate_limit' => 1 ) )
		);

		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = self::IP;

		// First request — consumes the sole allowed slot (fails at 'no_file', not rate limit).
		$first = $this->dispatch_search_for_rate_limit();
		$this->assertNotSame( 429, $first->get_status(), 'First request must not be rate-limited.' );

		// Second request — must be rate-limited.
		$second = $this->dispatch_search_for_rate_limit();
		$this->assertSame( 429, $second->get_status(), 'Second request beyond the cap must return 429.' );
	}

	/**
	 * POST /search is not rate-limited before the cap is reached.
	 */
	public function test_search_rate_limit_allows_requests_within_cap(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::defaults(), array( 'search_visibility' => 'anyone', 'rate_limit' => 5 ) )
		);

		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = self::IP;

		// Three requests against a cap of 5 should all pass (400, not 429).
		for ( $i = 1; $i <= 3; $i++ ) {
			$response = $this->dispatch_search_for_rate_limit();
			$this->assertNotSame(
				429,
				$response->get_status(),
				"Request {$i} of 3 must not be rate-limited when cap is 5."
			);
		}
	}

	// -----------------------------------------------------------------------
	// POST /tour/complete
	// -----------------------------------------------------------------------

	/**
	 * POST /tour/complete records the tour status in user_meta and returns 200.
	 */
	public function test_tour_complete_records_status_and_returns_200(): void {
		$response = $this->dispatch( 'POST', '/' . self::NS . '/tour/complete', array( 'status' => 'completed' ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 'completed', $data['status'] );
		$this->assertTrue( $data['saved'] );

		$meta = get_user_meta( $this->admin_id, 'snopix_tour_completed', true );
		$this->assertSame( 'completed', $meta );
	}

	/**
	 * GET /settings returns the tour_completed value previously stored by /tour/complete.
	 */
	public function test_tour_completed_reflected_in_settings_response(): void {
		update_user_meta( $this->admin_id, 'snopix_tour_completed', 'skipped' );

		$response = $this->dispatch( 'GET', '/' . self::NS . '/settings' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'tour_completed', $data );
		$this->assertSame( 'skipped', $data['tour_completed'] );
	}

	// -----------------------------------------------------------------------
	// POST /tools/clear-index
	// -----------------------------------------------------------------------

	/**
	 * POST /tools/clear-index empties the index and returns the deleted count.
	 */
	public function test_clear_index_removes_all_rows_and_returns_200(): void {
		$this->index_fixture( 5 );
		$this->index_fixture( 6 );

		$response = $this->dispatch( 'POST', '/' . self::NS . '/tools/clear-index' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'deleted', $data );
		$this->assertGreaterThanOrEqual( 2, (int) $data['deleted'] );

		// Index should now be empty.
		$rows = $this->repository->get_all_indexed();
		$this->assertEmpty( $rows );
	}

	/**
	 * POST /tools/clear-index returns 409 when a bulk job is running.
	 */
	public function test_clear_index_returns_409_when_job_is_running(): void {
		$this->progress->set( 0, 10 );

		$response = $this->dispatch( 'POST', '/' . self::NS . '/tools/clear-index' );
		$this->assertSame( 409, $response->get_status() );
	}

	// -----------------------------------------------------------------------
	// GET /tools/orphans + POST /tools/delete-orphans
	// -----------------------------------------------------------------------

	/**
	 * GET /tools/orphans returns HTTP 200 with an orphans count.
	 */
	public function test_orphans_returns_200_with_count(): void {
		$response = $this->dispatch( 'GET', '/' . self::NS . '/tools/orphans' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'orphans', $data );
		$this->assertIsInt( (int) $data['orphans'] );
	}

	/**
	 * POST /tools/delete-orphans returns HTTP 200 with a deleted count.
	 */
	public function test_delete_orphans_returns_200_with_deleted_count(): void {
		$response = $this->dispatch( 'POST', '/' . self::NS . '/tools/delete-orphans' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'deleted', $data );
		$this->assertIsInt( (int) $data['deleted'] );
	}
}
