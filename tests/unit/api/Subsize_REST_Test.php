<?php
/**
 * Tests for /tools/subsizes/* REST routes.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Imaging\Subsize_Watcher;
use PixelScout\Imaging\Subsize_Regenerator;

/**
 * REST capability + payload tests for subsize routes.
 */
class Pixel_Scout_Subsize_REST_Test extends Pixel_Scout_TestCase {

	public function setUp(): void {
		parent::setUp();
		delete_option( Subsize_Watcher::OPTION_KEY );
		delete_transient( Subsize_Regenerator::PENDING_KEY );
		delete_transient( 'ps_regen_progress_state' );
		do_action( 'rest_api_init' );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_diff_rejects_unauthenticated(): void {
		wp_set_current_user( 0 );
		$res = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/ps/v1/tools/subsizes/diff' ) );
		$this->assertContains( $res->get_status(), array( 401, 403 ) );
	}

	public function test_diff_rejects_subscriber(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$res = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/ps/v1/tools/subsizes/diff' ) );
		$this->assertSame( 403, $res->get_status() );
	}

	public function test_diff_returns_shape_for_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$res  = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/ps/v1/tools/subsizes/diff' ) );
		$data = $res->get_data();
		$this->assertSame( 200, $res->get_status() );
		$this->assertArrayHasKey( 'new', $data );
		$this->assertArrayHasKey( 'removed', $data );
		$this->assertArrayHasKey( 'changed', $data );
		$this->assertArrayHasKey( 'has_changes', $data );
	}

	public function test_regen_missing_admin_returns_count(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$res  = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/ps/v1/tools/subsizes/regen-missing' ) );
		$data = $res->get_data();
		$this->assertSame( 200, $res->get_status() );
		$this->assertArrayHasKey( 'scheduled', $data );
		$this->assertArrayHasKey( 'count', $data );
	}

	public function test_regen_all_rejects_subscriber(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$res = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/ps/v1/tools/subsizes/regen-all' ) );
		$this->assertSame( 403, $res->get_status() );
	}

	public function test_acknowledge_admin_returns_ok(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$res  = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/ps/v1/tools/subsizes/acknowledge' ) );
		$data = $res->get_data();
		$this->assertSame( 200, $res->get_status() );
		$this->assertArrayHasKey( 'acknowledged', $data );
	}

	public function test_acknowledge_rejects_subscriber(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$res = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/ps/v1/tools/subsizes/acknowledge' ) );
		$this->assertSame( 403, $res->get_status() );
	}
}
