<?php
/**
 * Tests for REST_Controller route registration + permission handling.
 *
 * @package Snopix
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use Snopix\Api\Rate_Limiter;
use Snopix\Api\REST_Controller;
use Snopix\Indexing\Bulk_Indexer;
use Snopix\Indexing\Index_Progress;
use Snopix\Repository\Index_Repository;
use Snopix\Repository\Schema;
use Snopix\Search\Query_Image;
use Snopix\Search\Search_Pipeline;

/**
 * REST_Controller integration tests via the WP REST server.
 */
class Snopix_REST_Controller_Test extends Snopix_TestCase {

	private \WP_REST_Server $server;

	/**
	 * Boot the REST server and register routes before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		global $wpdb, $wp_rest_server;
		( new Schema() )->install();

		$repo         = new Index_Repository( $wpdb );
		$pipeline     = $this->createMock( Search_Pipeline::class );
		$query_image  = new Query_Image();
		$bulk_indexer = $this->createMock( Bulk_Indexer::class );
		$progress     = new Index_Progress();
		$rate_limiter = new Rate_Limiter();

		$controller = new REST_Controller(
			$pipeline,
			$query_image,
			$repo,
			$bulk_indexer,
			$progress,
			$rate_limiter
		);
		$controller->register_routes();

		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
		$controller->register_routes();
	}

	/**
	 * Restore the REST server after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tearDown();
	}

	/**
	 * Switch identity to an admin user so manage_options checks pass.
	 *
	 * @return void
	 */
	private function login_as_admin(): void {
		$uid = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $uid );
	}

	/**
	 * Every documented route must be registered with the REST server.
	 *
	 * @return void
	 */
	public function test_routes_are_registered(): void {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/snopix/v1/search', $routes );
		$this->assertArrayHasKey( '/snopix/v1/status', $routes );
		$this->assertArrayHasKey( '/snopix/v1/images', $routes );
		$this->assertArrayHasKey( '/snopix/v1/reindex', $routes );
		$this->assertArrayHasKey( '/snopix/v1/progress', $routes );
		$this->assertArrayHasKey( '/snopix/v1/tools/reindex-all', $routes );
		$this->assertArrayHasKey( '/snopix/v1/tools/clear-index', $routes );
		$this->assertArrayHasKey( '/snopix/v1/tools/orphans', $routes );
		$this->assertArrayHasKey( '/snopix/v1/tools/delete-orphans', $routes );
		$this->assertArrayHasKey( '/snopix/v1/tools/clear-cache', $routes );
		$this->assertArrayHasKey( '/snopix/v1/settings', $routes );
	}

	/**
	 * `/status` is admin-only — anonymous request returns 401.
	 *
	 * @return void
	 */
	public function test_status_requires_manage_options(): void {
		wp_set_current_user( 0 );
		$response = $this->server->dispatch( new \WP_REST_Request( 'GET', '/snopix/v1/status' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * `/status` for an admin returns the index counts envelope.
	 *
	 * @return void
	 */
	public function test_status_returns_counts_for_admin(): void {
		$this->login_as_admin();
		$response = $this->server->dispatch( new \WP_REST_Request( 'GET', '/snopix/v1/status' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'indexed', $data );
		$this->assertArrayHasKey( 'pending', $data );
	}

	/**
	 * `/search` with no file returns 400.
	 *
	 * @return void
	 */
	public function test_search_without_file_returns_400(): void {
		$response = $this->server->dispatch( new \WP_REST_Request( 'POST', '/snopix/v1/search' ) );
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * `/progress` returns the progress envelope for admins.
	 *
	 * @return void
	 */
	public function test_progress_returns_envelope(): void {
		$this->login_as_admin();
		$response = $this->server->dispatch( new \WP_REST_Request( 'GET', '/snopix/v1/progress' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertArrayHasKey( 'done', $data );
		$this->assertArrayHasKey( 'total', $data );
	}

	/**
	 * `/settings` GET returns the configured visibility for admins.
	 *
	 * @return void
	 */
	public function test_get_settings_returns_visibility(): void {
		$this->login_as_admin();
		$response = $this->server->dispatch( new \WP_REST_Request( 'GET', '/snopix/v1/settings' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'search_visibility', $data );
		$this->assertContains( $data['search_visibility'], array( 'anyone', 'logged_in' ) );
	}

	/**
	 * `/settings` POST sanitises and persists the visibility value.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_value(): void {
		$this->login_as_admin();
		$req = new \WP_REST_Request( 'POST', '/snopix/v1/settings' );
		$req->set_param( 'search_visibility', 'logged_in' );

		$response = $this->server->dispatch( $req );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'logged_in', $response->get_data()['search_visibility'] );

		$stored = get_option( 'snopix_settings' );
		$this->assertSame( 'logged_in', $stored['search_visibility'] );
	}

	/**
	 * `/reindex` triggers `Bulk_Indexer::schedule` for admins.
	 *
	 * @return void
	 */
	public function test_reindex_schedules_for_admin(): void {
		global $wpdb, $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();

		$bulk = $this->createMock( Bulk_Indexer::class );
		$bulk->expects( $this->once() )->method( 'schedule' );

		$controller = new REST_Controller(
			$this->createMock( Search_Pipeline::class ),
			new Query_Image(),
			new Index_Repository( $wpdb ),
			$bulk,
			new Index_Progress(),
			new Rate_Limiter()
		);
		$controller->register_routes();

		$this->login_as_admin();
		$response = $wp_rest_server->dispatch( new \WP_REST_Request( 'POST', '/snopix/v1/reindex' ) );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * `/tools/clear-index` invokes `clear_all` and resets progress.
	 *
	 * @return void
	 */
	public function test_clear_index_calls_repository_clear_all(): void {
		$this->login_as_admin();
		$response = $this->server->dispatch( new \WP_REST_Request( 'POST', '/snopix/v1/tools/clear-index' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'deleted', $response->get_data() );
	}
}
