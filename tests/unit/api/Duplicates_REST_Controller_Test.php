<?php
/**
 * Tests for Duplicates_REST_Controller routes + permission handling.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Api\Duplicates_REST_Controller;
use PixelScout\Duplicates\Duplicate_Progress;
use PixelScout\Duplicates\Duplicate_Scanner;
use PixelScout\Repository\Index_Repository;
use PixelScout\Repository\Schema;

/**
 * Duplicates_REST_Controller integration tests.
 */
class Pixel_Scout_Duplicates_REST_Controller_Test extends Pixel_Scout_TestCase {

	private \WP_REST_Server $server;
	private Duplicate_Scanner $scanner;
	private Duplicate_Progress $progress;

	/**
	 * Boot REST server with mocked scanner so we never run an actual scan.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		global $wpdb, $wp_rest_server;
		( new Schema() )->install();

		$repo           = new Index_Repository( $wpdb );
		$this->scanner  = $this->createMock( Duplicate_Scanner::class );
		$this->progress = new Duplicate_Progress();
		$this->progress->reset();

		$controller = new Duplicates_REST_Controller( $this->scanner, $this->progress, $repo );

		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		$controller->register_routes();
	}

	/**
	 * Tear down server + progress state.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		$this->progress->reset();
		parent::tearDown();
	}

	/**
	 * Switch identity to an admin user.
	 *
	 * @return void
	 */
	private function login_as_admin(): void {
		$uid = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $uid );
	}

	/**
	 * Documented routes are present.
	 *
	 * @return void
	 */
	public function test_routes_are_registered(): void {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/ps/v1/duplicates', $routes );
		$this->assertArrayHasKey( '/ps/v1/duplicates/scan', $routes );
		$this->assertArrayHasKey( '/ps/v1/duplicates/progress', $routes );
	}

	/**
	 * Anonymous request to `/duplicates` is rejected with 401.
	 *
	 * @return void
	 */
	public function test_get_duplicates_requires_admin(): void {
		wp_set_current_user( 0 );
		$response = $this->server->dispatch( new \WP_REST_Request( 'GET', '/ps/v1/duplicates' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * `/duplicates/scan` schedules a fresh scan for admins.
	 *
	 * @return void
	 */
	public function test_start_scan_invokes_scheduler(): void {
		$this->scanner->expects( $this->once() )->method( 'schedule' );

		$this->login_as_admin();
		$response = $this->server->dispatch( new \WP_REST_Request( 'POST', '/ps/v1/duplicates/scan' ) );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * `/duplicates/scan` returns 409 when a scan is already running.
	 *
	 * @return void
	 */
	public function test_start_scan_returns_409_when_already_running(): void {
		$this->progress->set( 0, 10 );
		$this->scanner->expects( $this->never() )->method( 'schedule' );

		$this->login_as_admin();
		$response = $this->server->dispatch( new \WP_REST_Request( 'POST', '/ps/v1/duplicates/scan' ) );
		$this->assertSame( 409, $response->get_status() );
	}

	/**
	 * Deleting a non-attachment ID returns 404.
	 *
	 * @return void
	 */
	public function test_delete_attachment_returns_404_when_missing(): void {
		$this->login_as_admin();
		$response = $this->server->dispatch(
			new \WP_REST_Request( 'DELETE', '/ps/v1/duplicates/attachment/999999' )
		);
		$this->assertSame( 404, $response->get_status() );
	}
}
