<?php
/**
 * REST API controller for feature-notification endpoints.
 *
 * @package Snopix
 */

namespace Snopix\Api;

use Snopix\Notifications\{Feature_Notification_Registry, Feature_Notification_Store};

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles snopix/v1/notices/* REST routes.
 *
 * Read endpoint returns the active (non-dismissed) notifications for the
 * requester. Dismiss endpoint validates the inbound ID against the registry
 * so arbitrary keys can't pollute per-user meta storage.
 *
 * Route paths intentionally stay under `/notices*` so existing frontend
 * callers continue to work; only the PHP layer is renamed to the
 * "notification" vocabulary used in the admin UI.
 */
class Notifications_REST_Controller {

	/**
	 * REST namespace.
	 */
	private const REST_NAMESPACE = 'snopix/v1';

	/**
	 * Constructor.
	 *
	 * @param Feature_Notification_Store $store Dismissal-state store.
	 */
	public function __construct( private Feature_Notification_Store $store ) {}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/notices',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_list' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/notices/(?P<id>[a-z0-9-]+)/dismiss',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_dismiss' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'args'                => array(
					'id' => array(
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static fn( $v ) => is_string( $v ) && '' !== $v,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/notices/dismiss-all',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_dismiss_all' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Handle GET /notices — return the wire payload for every notification
	 * the current user has not dismissed.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_list(): \WP_REST_Response {
		$user_id = get_current_user_id();
		$active  = $this->store->active_for_user( $user_id );

		$payload = array_map(
			static fn( $notification ) => $notification->to_array(),
			$active
		);

		return new \WP_REST_Response( array_values( $payload ), 200 );
	}

	/**
	 * Handle POST /notices/{id}/dismiss — record dismissal for the current user.
	 *
	 * Rejects unknown notification IDs with 404 so we never persist arbitrary
	 * keys.
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_dismiss( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = sanitize_key( (string) $request->get_param( 'id' ) );

		if ( null === Feature_Notification_Registry::find( $id ) ) {
			return new \WP_Error(
				'unknown_notification',
				__( 'Unknown notification.', 'snopix' ),
				array( 'status' => 404 )
			);
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new \WP_Error(
				'not_logged_in',
				__( 'Authentication required.', 'snopix' ),
				array( 'status' => 401 )
			);
		}

		$this->store->dismiss( $user_id, $id );

		return new \WP_REST_Response(
			array(
				'dismissed' => true,
				'id'        => $id,
			),
			200
		);
	}

	/**
	 * Handle POST /notices/dismiss-all — bulk-dismiss every currently
	 * registered notification for the current user.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_dismiss_all(): \WP_REST_Response|\WP_Error {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new \WP_Error(
				'not_logged_in',
				__( 'Authentication required.', 'snopix' ),
				array( 'status' => 401 )
			);
		}

		$added = $this->store->dismiss_all( $user_id );

		return new \WP_REST_Response(
			array(
				'dismissed' => true,
				'added'     => $added,
			),
			200
		);
	}
}
