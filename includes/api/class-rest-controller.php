<?php
/**
 * REST API controller for Pixel Scout endpoints.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Api;

use PixelScout\Search\{Search_Pipeline, Query_Image};
use PixelScout\Repository\Index_Repository;
use PixelScout\Indexing\{Bulk_Indexer, Index_Progress};
use PixelScout\Hooks\Settings;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles all ps/v1 REST API routes.
 */
class REST_Controller {

	/**
	 * REST API namespace.
	 */
	private const REST_NAMESPACE = 'ps/v1';

	/**
	 * Constructor.
	 *
	 * @param Search_Pipeline  $pipeline     Search pipeline.
	 * @param Query_Image      $query_image  Query image handler.
	 * @param Index_Repository $repository   Index repository.
	 * @param Bulk_Indexer     $bulk_indexer Bulk indexer.
	 * @param Index_Progress   $progress     Progress tracker.
	 * @param Rate_Limiter     $rate_limiter Rate limiter.
	 * @param Settings         $settings   Plugin settings.
	 */
	public function __construct(
		private Search_Pipeline $pipeline,
		private Query_Image $query_image,
		private Index_Repository $repository,
		private Bulk_Indexer $bulk_indexer,
		private Index_Progress $progress,
		private Rate_Limiter $rate_limiter,
		private Settings $settings
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
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_search' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_status' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/images',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_images' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'args'                => array(
					'page'     => array(
						'sanitize_callback' => 'absint',
						'default'           => 1,
					),
					'per_page' => array(
						'sanitize_callback' => 'absint',
						'default'           => 25,
					),
					'search'   => array(
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/reindex',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_reindex' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/progress',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_progress' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/index/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'handle_delete_index' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => 'is_numeric',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/tools/reindex-all',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_reindex_all' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/tools/clear-index',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_clear_index' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/tools/orphans',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_orphan_count' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/tools/delete-orphans',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_delete_orphans' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/tools/clear-cache',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_clear_cache' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Handle POST /search — reverse image search.
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_search( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

		if ( ! $this->rate_limiter->is_allowed( $ip ) ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Too many requests.', 'pixel-scout' ),
				array( 'status' => 429 )
			);
		}

		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new \WP_Error(
				'no_file',
				__( 'No file provided.', 'pixel-scout' ),
				array( 'status' => 400 )
			);
		}

		$attachment_id = $this->query_image->from_upload( $files['file'] );

		if ( false === $attachment_id ) {
			return new \WP_Error(
				'upload_failed',
				__( 'File upload failed or unsupported type.', 'pixel-scout' ),
				array( 'status' => 422 )
			);
		}

		try {
			$results = $this->pipeline->search( $attachment_id );
		} finally {
			$this->query_image->cleanup( $attachment_id );
		}

		$results = array_values(
			array_filter( $results, static fn( $r ) => $r->attachment_id !== $attachment_id )
		);

		return new \WP_REST_Response(
			array_map(
				static fn( $r ) => array(
					'id'             => $r->attachment_id,
					'url'            => $r->url,
					'thumbnail'      => $r->thumbnail,
					'title'          => $r->title,
					'score'          => round( $r->score, 4 ),
					'attachment_url' => get_attachment_link( $r->attachment_id ),
				),
				$results
			),
			200
		);
	}

	/**
	 * Handle GET /status — index counts.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_status(): \WP_REST_Response {
		$counts = $this->repository->get_counts();
		return new \WP_REST_Response( $counts, 200 );
	}

	/**
	 * Handle GET /images — paginated image list.
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_images( \WP_REST_Request $request ): \WP_REST_Response {
		$page     = absint( $request->get_param( 'page' ) ?? 1 );
		$per_page = absint( $request->get_param( 'per_page' ) ?? 25 );
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?? '' );

		$rows = $this->repository->get_paginated( $page, $per_page, $search );

		$rows = array_map(
			static function ( array $row ): array {
				$id                   = (int) $row['attachment_id'];
				$row['title']         = get_the_title( $id );
				$file                 = get_attached_file( $id );
				$row['filename']      = $file ? basename( $file ) : '';
				$thumb                = wp_get_attachment_image_src( $id, 'thumbnail' );
				$row['thumbnail_url'] = $thumb ? $thumb[0] : '';
				$full                 = wp_get_attachment_image_src( $id, 'full' );
				$row['full_url']      = $full ? $full[0] : '';
				return $row;
			},
			$rows
		);

		return new \WP_REST_Response( $rows, 200 );
	}

	/**
	 * Handle POST /reindex — schedule bulk reindex.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_reindex(): \WP_REST_Response {
		$this->bulk_indexer->schedule();
		return new \WP_REST_Response( array( 'scheduled' => true ), 200 );
	}

	/**
	 * Handle GET /progress — bulk index progress.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_progress(): \WP_REST_Response {
		return new \WP_REST_Response( $this->progress->get(), 200 );
	}

	/**
	 * Handle DELETE /index/{id} — remove a single index entry.
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_delete_index( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id      = absint( $request->get_param( 'id' ) );
		$deleted = $this->repository->delete( $id );

		if ( ! $deleted ) {
			return new \WP_Error(
				'not_found',
				__( 'Not found.', 'pixel-scout' ),
				array( 'status' => 404 )
			);
		}

		return new \WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Handle POST /tools/reindex-all — wipe + reindex every attachment.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_reindex_all(): \WP_REST_Response {
		$this->bulk_indexer->schedule_all();
		return new \WP_REST_Response( array( 'scheduled' => true ), 200 );
	}

	/**
	 * Handle POST /tools/clear-index — delete every index row.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_clear_index(): \WP_REST_Response {
		$deleted = $this->repository->clear_all();
		$this->progress->reset();
		return new \WP_REST_Response( array( 'deleted' => $deleted ), 200 );
	}

	/**
	 * Handle GET /tools/orphans — count of stale index rows.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_orphan_count(): \WP_REST_Response {
		return new \WP_REST_Response( array( 'orphans' => $this->repository->get_orphan_count() ), 200 );
	}

	/**
	 * Handle POST /tools/delete-orphans — drop rows whose attachment is gone.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_delete_orphans(): \WP_REST_Response {
		$deleted = $this->repository->delete_orphans();
		return new \WP_REST_Response( array( 'deleted' => $deleted ), 200 );
	}

	/**
	 * Handle POST /tools/clear-cache — flush plugin transients and object cache.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_clear_cache(): \WP_REST_Response {
		$this->repository->flush_cache();
		$this->progress->reset();
		return new \WP_REST_Response( array( 'cleared' => true ), 200 );
	}
}
