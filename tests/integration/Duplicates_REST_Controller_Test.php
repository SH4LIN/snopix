<?php
/**
 * Integration tests for Snopix\Api\Duplicates_REST_Controller.
 *
 * Boots a real WordPress + DB environment (transaction-rolled-back per test),
 * wires the controller with its real collaborators, and exercises every route
 * via rest_do_request().
 *
 * Routes under test:
 *   GET  /snopix/v1/duplicates
 *   POST /snopix/v1/duplicates/scan
 *   GET  /snopix/v1/duplicates/progress
 *   POST /snopix/v1/duplicates/reset
 *
 * @package Snopix
 */

use Snopix\Api\Duplicates_REST_Controller;
use Snopix\Duplicates\Duplicate_Finder;
use Snopix\Duplicates\Duplicate_Progress;
use Snopix\Duplicates\Duplicate_Scanner;
use Snopix\Imaging\Similarity;
use Snopix\Infrastructure\Action_Scheduler;
use Snopix\Infrastructure\Job_Status;
use Snopix\Repository\Index_Repository;

/**
 * @covers \Snopix\Api\Duplicates_REST_Controller
 */
final class Duplicates_REST_Controller_Test extends Snopix_Integration_TestCase {

	/** @var Duplicates_REST_Controller */
	private Duplicates_REST_Controller $controller;

	/** @var Index_Repository */
	private Index_Repository $repo;

	/** @var Duplicate_Progress */
	private Duplicate_Progress $progress;

	/** @var Duplicate_Scanner */
	private Duplicate_Scanner $scanner;

	/** @var int Administrator user ID. */
	private int $admin_id;

	public function set_up(): void {
		parent::set_up();

		global $wpdb;

		$this->repo     = new Index_Repository( $wpdb );
		$similarity     = new Similarity();
		$finder         = new Duplicate_Finder( $similarity );
		$this->progress = new Duplicate_Progress();
		$this->scanner  = new Duplicate_Scanner(
			$this->repo,
			$finder,
			$this->progress,
			new Action_Scheduler()
		);

		$this->controller = new Duplicates_REST_Controller( $this->scanner, $this->progress );

		// Register routes for this test run.
		do_action( 'rest_api_init' );
		$this->controller->register_routes();

		// Create an administrator to authenticate privileged requests.
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	public function tear_down(): void {
		// Ensure progress transient is cleared between tests.
		$this->progress->reset();
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Permission enforcement - anonymous user must be denied on every route.
	// -------------------------------------------------------------------------

	public function test_get_duplicates_anonymous_returns_401(): void {
		wp_set_current_user( 0 );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/snopix/v1/duplicates' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_start_scan_anonymous_returns_401(): void {
		wp_set_current_user( 0 );
		$response = rest_do_request( new WP_REST_Request( 'POST', '/snopix/v1/duplicates/scan' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_progress_anonymous_returns_401(): void {
		wp_set_current_user( 0 );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/snopix/v1/duplicates/progress' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_reset_anonymous_returns_401(): void {
		wp_set_current_user( 0 );
		$response = rest_do_request( new WP_REST_Request( 'POST', '/snopix/v1/duplicates/reset' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// GET /snopix/v1/duplicates - empty state.
	// -------------------------------------------------------------------------

	public function test_get_duplicates_returns_200_with_expected_shape(): void {
		wp_set_current_user( $this->admin_id );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/snopix/v1/duplicates' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'groups', $data );
		$this->assertArrayHasKey( 'last_scanned', $data );
		$this->assertArrayHasKey( 'group_count', $data );
		$this->assertIsArray( $data['groups'] );
		$this->assertSame( 0, $data['group_count'] );
	}

	// -------------------------------------------------------------------------
	// GET /snopix/v1/duplicates - with pre-seeded results.
	// -------------------------------------------------------------------------

	public function test_get_duplicates_returns_persisted_exact_group(): void {
		wp_set_current_user( $this->admin_id );

		// Create two real attachments so enrich_group can resolve them.
		$id_a = $this->attach_fixture( 1 );
		$id_b = $this->attach_fixture( 1 ); // identical bytes → exact duplicate.

		// Seed the results option directly, as the scanner would after a run.
		$groups = array(
			array(
				'match_type' => 'exact',
				'ids'        => array( $id_a, $id_b ),
			),
		);
		update_option( 'snopix_duplicate_results', wp_json_encode( $groups ), false );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/snopix/v1/duplicates' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $data['group_count'] );

		$group = $data['groups'][0];
		$this->assertSame( 'exact', $group['match_type'] );
		$this->assertSame( 1.0, $group['similarity'] );
		$this->assertCount( 2, $group['images'] );

		$returned_ids = array_column( $group['images'], 'id' );
		sort( $returned_ids );
		$this->assertSame( array( $id_a, $id_b ), $returned_ids );

		// Each image entry must carry the expected keys.
		$image = $group['images'][0];
		foreach ( array( 'id', 'title', 'filename', 'file_size', 'width', 'height', 'mime_type', 'thumbnail_url', 'full_url' ) as $key ) {
			$this->assertArrayHasKey( $key, $image, "Missing key '{$key}' in image entry." );
		}
	}

	public function test_get_duplicates_filters_group_with_only_one_valid_attachment(): void {
		wp_set_current_user( $this->admin_id );

		// Group references a non-existent attachment - enrich drops it, resulting
		// in fewer than 2 images, so the group must be excluded from the response.
		$groups = array(
			array(
				'match_type' => 'perceptual',
				'ids'        => array( 999999, 888888 ),
			),
		);
		update_option( 'snopix_duplicate_results', wp_json_encode( $groups ), false );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/snopix/v1/duplicates' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 0, $data['group_count'] );
		$this->assertSame( array(), $data['groups'] );
	}

	// -------------------------------------------------------------------------
	// POST /snopix/v1/duplicates/scan
	// -------------------------------------------------------------------------

	public function test_start_scan_returns_scheduled_true(): void {
		wp_set_current_user( $this->admin_id );

		$response = rest_do_request( new WP_REST_Request( 'POST', '/snopix/v1/duplicates/scan' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'scheduled', $data );
		$this->assertTrue( $data['scheduled'] );
	}

	public function test_start_scan_while_running_returns_409(): void {
		wp_set_current_user( $this->admin_id );

		// Put progress into running state to simulate an in-flight scan.
		$this->progress->set( 0, 10 );

		$response = rest_do_request( new WP_REST_Request( 'POST', '/snopix/v1/duplicates/scan' ) );

		$this->assertSame( 409, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// GET /snopix/v1/duplicates/progress
	// -------------------------------------------------------------------------

	public function test_progress_idle_returns_idle_status(): void {
		wp_set_current_user( $this->admin_id );

		// No scan triggered - progress transient absent → idle sentinel.
		$response = rest_do_request( new WP_REST_Request( 'GET', '/snopix/v1/duplicates/progress' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( Job_Status::IDLE, $data['status'] );
		$this->assertArrayHasKey( 'done', $data );
		$this->assertArrayHasKey( 'total', $data );
	}

	public function test_progress_reflects_running_state_after_scan_scheduled(): void {
		wp_set_current_user( $this->admin_id );

		// schedule() calls progress->reset() then progress->set(0,1) internally.
		$this->scanner->schedule();

		$response = rest_do_request( new WP_REST_Request( 'GET', '/snopix/v1/duplicates/progress' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Job_Status::RUNNING, $data['status'] );
	}

	// -------------------------------------------------------------------------
	// POST /snopix/v1/duplicates/reset
	// -------------------------------------------------------------------------

	public function test_reset_returns_reset_true(): void {
		wp_set_current_user( $this->admin_id );

		$response = rest_do_request( new WP_REST_Request( 'POST', '/snopix/v1/duplicates/reset' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'reset', $data );
		$this->assertTrue( $data['reset'] );
	}

	public function test_reset_transitions_running_scan_to_idle(): void {
		wp_set_current_user( $this->admin_id );

		// Simulate an in-flight scan.
		$this->progress->set( 3, 10 );
		$this->assertSame( Job_Status::RUNNING, $this->progress->get()['status'] );

		rest_do_request( new WP_REST_Request( 'POST', '/snopix/v1/duplicates/reset' ) );

		// After abort(), progress transient is deleted → get() returns idle.
		$this->assertSame( Job_Status::IDLE, $this->progress->get()['status'] );
	}
}
