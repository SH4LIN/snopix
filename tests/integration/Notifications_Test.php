<?php
/**
 * Integration tests for Feature_Notification_Store and Notifications_REST_Controller.
 *
 * @package Snopix
 */

use Snopix\Api\Notifications_REST_Controller;
use Snopix\Notifications\Feature_Notification;
use Snopix\Notifications\Feature_Notification_Registry;
use Snopix\Notifications\Feature_Notification_Store;

/**
 * @covers \Snopix\Notifications\Feature_Notification_Store
 * @covers \Snopix\Api\Notifications_REST_Controller
 */
final class Notifications_Test extends Snopix_Integration_TestCase {

	private Feature_Notification_Store $store;
	private int $admin_id;
	private int $subscriber_id;

	public function set_up(): void {
		parent::set_up();

		$this->store = new Feature_Notification_Store();

		// Ensure the registry cache is clean so filter hooks added in individual
		// tests do not bleed between tests.
		Feature_Notification_Registry::flush_cache();

		// Create users via factory.
		$this->admin_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		// Register REST routes with a fresh server initialisation.
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		// Flush the static cache so tests after this one start clean.
		Feature_Notification_Registry::flush_cache();

		// Remove any filter stubs registered within tests.
		remove_all_filters( 'snopix_feature_notifications' );

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a minimal Feature_Notification for use in filter stubs.
	 *
	 * @param string $id Notification ID.
	 *
	 * @return Feature_Notification
	 */
	private function make_notification( string $id ): Feature_Notification {
		return new Feature_Notification(
			id: $id,
			title: "Test notification {$id}",
			body: "Body for {$id}.",
			icon: 'info',
			severity: 'info',
			since_version: '0.0.1',
		);
	}

	/**
	 * Dispatch a REST request as the given user and return the response.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Route path (e.g. '/snopix/v1/notices').
	 * @param int    $user_id User to set as the current user (0 = unauthenticated).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	private function rest_as( string $method, string $path, int $user_id = 0 ) {
		wp_set_current_user( $user_id );
		$request = new WP_REST_Request( $method, $path );
		return rest_do_request( $request );
	}

	// -------------------------------------------------------------------------
	// Feature_Notification_Store - unit-level persistence
	// -------------------------------------------------------------------------

	public function test_store_not_dismissed_by_default(): void {
		$this->assertFalse( $this->store->is_dismissed( $this->admin_id, 'duplicates-launch' ) );
	}

	public function test_store_dismiss_persists_in_user_meta(): void {
		$this->store->dismiss( $this->admin_id, 'duplicates-launch' );

		$this->assertTrue( $this->store->is_dismissed( $this->admin_id, 'duplicates-launch' ) );

		// Confirm raw meta value so we're testing the actual DB round-trip, not
		// just in-memory state.
		$raw = get_user_meta( $this->admin_id, Feature_Notification_Store::META_KEY, true );
		$this->assertIsArray( $raw );
		$this->assertContains( 'duplicates-launch', $raw );
	}

	public function test_store_dismiss_is_idempotent(): void {
		$this->store->dismiss( $this->admin_id, 'duplicates-launch' );
		$this->store->dismiss( $this->admin_id, 'duplicates-launch' );

		$raw = get_user_meta( $this->admin_id, Feature_Notification_Store::META_KEY, true );
		$this->assertIsArray( $raw );
		$this->assertSame( 1, count( array_filter( $raw, static fn( $v ) => 'duplicates-launch' === $v ) ) );
	}

	public function test_store_dismiss_is_per_user(): void {
		$this->store->dismiss( $this->admin_id, 'duplicates-launch' );

		// Dismissal for admin must not affect subscriber.
		$this->assertFalse( $this->store->is_dismissed( $this->subscriber_id, 'duplicates-launch' ) );
	}

	public function test_store_restore_removes_dismissal(): void {
		$this->store->dismiss( $this->admin_id, 'duplicates-launch' );
		$this->store->restore( $this->admin_id, 'duplicates-launch' );

		$this->assertFalse( $this->store->is_dismissed( $this->admin_id, 'duplicates-launch' ) );
	}

	public function test_store_restore_noop_when_not_dismissed(): void {
		// Must not throw and must leave meta untouched.
		$this->store->restore( $this->admin_id, 'duplicates-launch' );
		$this->assertFalse( $this->store->is_dismissed( $this->admin_id, 'duplicates-launch' ) );
	}

	public function test_store_dismiss_all_marks_every_registered_notification(): void {
		// Override the registry with two deterministic notifications.
		add_filter(
			'snopix_feature_notifications',
			function () {
				return array(
					$this->make_notification( 'notice-alpha' ),
					$this->make_notification( 'notice-beta' ),
				);
			}
		);
		Feature_Notification_Registry::flush_cache();

		$added = $this->store->dismiss_all( $this->admin_id );

		$this->assertSame( 2, $added );
		$this->assertTrue( $this->store->is_dismissed( $this->admin_id, 'notice-alpha' ) );
		$this->assertTrue( $this->store->is_dismissed( $this->admin_id, 'notice-beta' ) );
	}

	public function test_store_dismiss_all_returns_only_newly_added_count(): void {
		add_filter(
			'snopix_feature_notifications',
			function () {
				return array(
					$this->make_notification( 'notice-alpha' ),
					$this->make_notification( 'notice-beta' ),
				);
			}
		);
		Feature_Notification_Registry::flush_cache();

		// Pre-dismiss one so dismiss_all should count only the remaining one.
		$this->store->dismiss( $this->admin_id, 'notice-alpha' );

		$added = $this->store->dismiss_all( $this->admin_id );

		$this->assertSame( 1, $added );
	}

	public function test_store_active_for_user_excludes_dismissed(): void {
		add_filter(
			'snopix_feature_notifications',
			function () {
				return array(
					$this->make_notification( 'notice-alpha' ),
					$this->make_notification( 'notice-beta' ),
				);
			}
		);
		Feature_Notification_Registry::flush_cache();

		$this->store->dismiss( $this->admin_id, 'notice-alpha' );

		$active = $this->store->active_for_user( $this->admin_id );

		$this->assertCount( 1, $active );
		$this->assertSame( 'notice-beta', $active[0]->id );
	}

	public function test_store_active_for_user_zero_id_returns_all(): void {
		add_filter(
			'snopix_feature_notifications',
			function () {
				return array(
					$this->make_notification( 'notice-alpha' ),
					$this->make_notification( 'notice-beta' ),
				);
			}
		);
		Feature_Notification_Registry::flush_cache();

		// user_id 0 means unauthenticated; no dismissal list can exist, so all
		// registered notifications come back.
		$active = $this->store->active_for_user( 0 );

		$this->assertCount( 2, $active );
	}

	public function test_store_ignores_invalid_user_id(): void {
		// Should be a no-op and must not throw.
		$this->store->dismiss( 0, 'duplicates-launch' );
		$this->store->dismiss( -1, 'duplicates-launch' );

		$this->assertFalse( $this->store->is_dismissed( 0, 'duplicates-launch' ) );
	}

	public function test_store_ignores_empty_notification_id(): void {
		$this->store->dismiss( $this->admin_id, '' );
		$this->assertFalse( $this->store->is_dismissed( $this->admin_id, '' ) );
	}

	// -------------------------------------------------------------------------
	// Feature_Notification - to_array wire shape
	// -------------------------------------------------------------------------

	public function test_notification_to_array_shape(): void {
		$notification = new Feature_Notification(
			id: 'test-notice',
			title: 'Test title',
			body: 'Test body.',
			icon: 'layers',
			severity: 'success',
			since_version: '1.2.3',
			cta_label: 'Go',
			cta_route: 'duplicates',
		);

		$arr = $notification->to_array();

		$this->assertSame( 'test-notice', $arr['id'] );
		$this->assertSame( 'Test title', $arr['title'] );
		$this->assertSame( 'Test body.', $arr['body'] );
		$this->assertSame( 'layers', $arr['icon'] );
		$this->assertSame( 'success', $arr['severity'] );
		$this->assertSame( '1.2.3', $arr['since_version'] );
		$this->assertSame( 'Go', $arr['cta_label'] );
		$this->assertSame( 'duplicates', $arr['cta_route'] );
		// cta_url must be empty when cta_route is set.
		$this->assertSame( '', $arr['cta_url'] );
	}

	public function test_notification_invalid_severity_falls_back_to_info(): void {
		$notification = new Feature_Notification(
			id: 'test-notice',
			title: 'T',
			body: 'B',
			severity: 'critical', // not in allowlist
		);

		$arr = $notification->to_array();
		$this->assertSame( 'info', $arr['severity'] );
	}

	public function test_notification_cta_url_only_when_route_empty(): void {
		$notification = new Feature_Notification(
			id: 'test-notice',
			title: 'T',
			body: 'B',
			cta_route: '',
			cta_url: 'https://example.com/feature',
		);

		$arr = $notification->to_array();
		$this->assertSame( 'https://example.com/feature', $arr['cta_url'] );
	}

	// -------------------------------------------------------------------------
	// REST - GET /snopix/v1/notices (list)
	// -------------------------------------------------------------------------

	public function test_rest_list_requires_manage_options(): void {
		$response = $this->rest_as( 'GET', '/snopix/v1/notices', $this->subscriber_id );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_rest_list_unauthenticated_returns_401_or_403(): void {
		$response = $this->rest_as( 'GET', '/snopix/v1/notices', 0 );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	public function test_rest_list_returns_active_notifications_for_admin(): void {
		add_filter(
			'snopix_feature_notifications',
			function () {
				return array(
					$this->make_notification( 'notice-alpha' ),
					$this->make_notification( 'notice-beta' ),
				);
			}
		);
		Feature_Notification_Registry::flush_cache();

		// Register routes after the registry is primed.
		$controller = new Notifications_REST_Controller( $this->store );
		$controller->register_routes();

		$response = $this->rest_as( 'GET', '/snopix/v1/notices', $this->admin_id );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertCount( 2, $data );
		$ids = array_column( $data, 'id' );
		$this->assertContains( 'notice-alpha', $ids );
		$this->assertContains( 'notice-beta', $ids );
	}

	public function test_rest_list_omits_dismissed_notifications(): void {
		add_filter(
			'snopix_feature_notifications',
			function () {
				return array(
					$this->make_notification( 'notice-alpha' ),
					$this->make_notification( 'notice-beta' ),
				);
			}
		);
		Feature_Notification_Registry::flush_cache();

		$controller = new Notifications_REST_Controller( $this->store );
		$controller->register_routes();

		// Pre-dismiss one notification directly via the store.
		$this->store->dismiss( $this->admin_id, 'notice-alpha' );

		$response = $this->rest_as( 'GET', '/snopix/v1/notices', $this->admin_id );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 'notice-beta', $data[0]['id'] );
	}

	public function test_rest_list_returns_empty_array_when_all_dismissed(): void {
		add_filter(
			'snopix_feature_notifications',
			function () {
				return array( $this->make_notification( 'notice-alpha' ) );
			}
		);
		Feature_Notification_Registry::flush_cache();

		$controller = new Notifications_REST_Controller( $this->store );
		$controller->register_routes();

		$this->store->dismiss( $this->admin_id, 'notice-alpha' );

		$response = $this->rest_as( 'GET', '/snopix/v1/notices', $this->admin_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
	}

	// -------------------------------------------------------------------------
	// REST - POST /snopix/v1/notices/{id}/dismiss (single dismiss)
	// -------------------------------------------------------------------------

	public function test_rest_dismiss_requires_manage_options(): void {
		$response = $this->rest_as( 'POST', '/snopix/v1/notices/duplicates-launch/dismiss', $this->subscriber_id );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_rest_dismiss_unknown_id_returns_404(): void {
		$controller = new Notifications_REST_Controller( $this->store );
		$controller->register_routes();

		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/snopix/v1/notices/does-not-exist/dismiss' );
		$response = rest_do_request( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_rest_dismiss_known_id_returns_200_and_persists(): void {
		// Seed a known notification that the registry will validate against.
		add_filter(
			'snopix_feature_notifications',
			function () {
				return array( $this->make_notification( 'notice-alpha' ) );
			}
		);
		Feature_Notification_Registry::flush_cache();

		$controller = new Notifications_REST_Controller( $this->store );
		$controller->register_routes();

		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'POST', '/snopix/v1/notices/notice-alpha/dismiss' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['dismissed'] );
		$this->assertSame( 'notice-alpha', $data['id'] );

		// Verify store state reflects the dismissal.
		$this->assertTrue( $this->store->is_dismissed( $this->admin_id, 'notice-alpha' ) );
	}

	public function test_rest_dismiss_is_idempotent_at_http_level(): void {
		add_filter(
			'snopix_feature_notifications',
			function () {
				return array( $this->make_notification( 'notice-alpha' ) );
			}
		);
		Feature_Notification_Registry::flush_cache();

		$controller = new Notifications_REST_Controller( $this->store );
		$controller->register_routes();

		wp_set_current_user( $this->admin_id );

		for ( $i = 0; $i < 2; $i++ ) {
			$request  = new WP_REST_Request( 'POST', '/snopix/v1/notices/notice-alpha/dismiss' );
			$response = rest_do_request( $request );
			$this->assertSame( 200, $response->get_status() );
		}

		// Meta must not contain duplicate entries.
		$raw = get_user_meta( $this->admin_id, Feature_Notification_Store::META_KEY, true );
		$this->assertSame(
			1,
			count( array_filter( (array) $raw, static fn( $v ) => 'notice-alpha' === $v ) )
		);
	}

	// -------------------------------------------------------------------------
	// REST - POST /snopix/v1/notices/dismiss-all
	// -------------------------------------------------------------------------

	public function test_rest_dismiss_all_requires_manage_options(): void {
		$response = $this->rest_as( 'POST', '/snopix/v1/notices/dismiss-all', $this->subscriber_id );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_rest_dismiss_all_marks_all_registered_and_returns_count(): void {
		add_filter(
			'snopix_feature_notifications',
			function () {
				return array(
					$this->make_notification( 'notice-alpha' ),
					$this->make_notification( 'notice-beta' ),
				);
			}
		);
		Feature_Notification_Registry::flush_cache();

		$controller = new Notifications_REST_Controller( $this->store );
		$controller->register_routes();

		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'POST', '/snopix/v1/notices/dismiss-all' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['dismissed'] );
		$this->assertSame( 2, $data['added'] );

		$this->assertTrue( $this->store->is_dismissed( $this->admin_id, 'notice-alpha' ) );
		$this->assertTrue( $this->store->is_dismissed( $this->admin_id, 'notice-beta' ) );
	}

	public function test_rest_dismiss_all_isolates_users(): void {
		add_filter(
			'snopix_feature_notifications',
			function () {
				return array( $this->make_notification( 'notice-alpha' ) );
			}
		);
		Feature_Notification_Registry::flush_cache();

		$controller = new Notifications_REST_Controller( $this->store );
		$controller->register_routes();

		// Admin dismisses all.
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/snopix/v1/notices/dismiss-all' );
		rest_do_request( $request );

		// Subscriber's state must be untouched.
		$this->assertFalse( $this->store->is_dismissed( $this->subscriber_id, 'notice-alpha' ) );
	}

	// -------------------------------------------------------------------------
	// REST - payload shape contract
	// -------------------------------------------------------------------------

	public function test_rest_list_payload_contains_expected_keys(): void {
		// Use the default registry (seeded with 'duplicates-launch').
		$controller = new Notifications_REST_Controller( $this->store );
		$controller->register_routes();

		$response = $this->rest_as( 'GET', '/snopix/v1/notices', $this->admin_id );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		if ( count( $data ) === 0 ) {
			$this->markTestSkipped( 'No notifications registered; cannot check payload shape.' );
		}

		$item     = $data[0];
		$required = array( 'id', 'title', 'body', 'icon', 'severity', 'since_version', 'cta_label', 'cta_route', 'cta_url' );
		foreach ( $required as $key ) {
			$this->assertArrayHasKey( $key, $item, "Missing key: {$key}" );
		}
	}
}
