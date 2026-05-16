<?php
/**
 * REST API controller for Pixel Scout endpoints.
 *
 * @package Pixel_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles all ps/v1 REST API routes.
 */
class Pixel_Scout_REST_Controller {

	/**
	 * REST API namespace.
	 */
	private const REST_NAMESPACE = 'ps/v1';

	/**
	 * Constructor.
	 *
	 * @param Pixel_Scout_Search_Pipeline  $pipeline     Search pipeline.
	 * @param Pixel_Scout_Query_Image      $query_image  Query image handler.
	 * @param Pixel_Scout_Index_Repository $repository   Index repository.
	 * @param Pixel_Scout_Bulk_Indexer     $bulk_indexer Bulk indexer.
	 * @param Pixel_Scout_Index_Progress   $progress     Progress tracker.
	 * @param Pixel_Scout_Rate_Limiter     $rate_limiter Rate limiter.
	 * @param Pixel_Scout_Settings         $settings     Plugin settings.
	 */
	public function __construct(
		private Pixel_Scout_Search_Pipeline $pipeline,
		private Pixel_Scout_Query_Image $query_image,
		private Pixel_Scout_Index_Repository $repository,
		private Pixel_Scout_Bulk_Indexer $bulk_indexer,
		private Pixel_Scout_Index_Progress $progress,
		private Pixel_Scout_Rate_Limiter $rate_limiter,
		private Pixel_Scout_Settings $settings
	) {}

	/**
	 * Register all REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/search',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_search' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'file' => [ 'required' => true ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/status',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_status' ],
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/images',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_images' ],
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'args'                => [
					'page'     => [
						'sanitize_callback' => 'absint',
						'default'           => 1,
					],
					'per_page' => [
						'sanitize_callback' => 'absint',
						'default'           => 25,
					],
					'search'   => [
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/reindex',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_reindex' ],
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/progress',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_progress' ],
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/index/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'handle_delete_index' ],
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'args'                => [
					'id' => [
						'validate_callback' => 'is_numeric',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Handle POST /search — reverse image search.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_search( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

		if ( ! $this->rate_limiter->is_allowed( $ip ) ) {
			return new WP_Error(
				'rate_limited',
				__( 'Too many requests.', 'pixel-scout' ),
				[ 'status' => 429 ]
			);
		}

		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error(
				'no_file',
				__( 'No file provided.', 'pixel-scout' ),
				[ 'status' => 400 ]
			);
		}

		$attachment_id = $this->query_image->from_upload( $files['file'] );

		if ( false === $attachment_id ) {
			return new WP_Error(
				'upload_failed',
				__( 'File upload failed or unsupported type.', 'pixel-scout' ),
				[ 'status' => 422 ]
			);
		}

		try {
			$results = $this->pipeline->search( $attachment_id );
		} finally {
			$this->query_image->cleanup( $attachment_id );
		}

		return new WP_REST_Response(
			array_map(
				static fn( $r ) => [
					'id'        => $r->attachment_id,
					'url'       => $r->url,
					'thumbnail' => $r->thumbnail,
					'title'     => $r->title,
					'score'     => round( $r->score, 4 ),
				],
				$results
			),
			200
		);
	}

	/**
	 * Handle GET /status — index counts.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_status( WP_REST_Request $request ): WP_REST_Response {
		$counts = $this->repository->get_counts();
		return new WP_REST_Response( $counts, 200 );
	}

	/**
	 * Handle GET /images — paginated image list.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_images( WP_REST_Request $request ): WP_REST_Response {
		$page     = absint( $request->get_param( 'page' ) ?: 1 );
		$per_page = absint( $request->get_param( 'per_page' ) ?: 25 );
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?: '' );

		$rows = $this->repository->get_paginated( $page, $per_page, $search );
		return new WP_REST_Response( $rows, 200 );
	}

	/**
	 * Handle POST /reindex — schedule bulk reindex.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_reindex( WP_REST_Request $request ): WP_REST_Response {
		$this->bulk_indexer->schedule();
		return new WP_REST_Response( [ 'scheduled' => true ], 200 );
	}

	/**
	 * Handle GET /progress — bulk index progress.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_progress( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->progress->get(), 200 );
	}

	/**
	 * Handle DELETE /index/{id} — remove a single index entry.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_index( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id      = absint( $request->get_param( 'id' ) );
		$deleted = $this->repository->delete( $id );

		if ( ! $deleted ) {
			return new WP_Error(
				'not_found',
				__( 'Not found.', 'pixel-scout' ),
				[ 'status' => 404 ]
			);
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}
}
