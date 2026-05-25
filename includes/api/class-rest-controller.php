<?php
/**
 * REST API controller for Snopix endpoints.
 *
 * @package Snopix
 */

namespace Snopix\Api;

use Snopix\Search\{Search_Pipeline, Query_Image};
use Snopix\Repository\Index_Repository;
use Snopix\Indexing\{Bulk_Indexer, Index_Progress};
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles all snopix/v1 REST API routes.
 */
class REST_Controller {

	/**
	 * REST API namespace.
	 */
	private const REST_NAMESPACE = 'snopix/v1';

	/**
	 * Constructor.
	 *
	 * @param Search_Pipeline  $pipeline     Search pipeline.
	 * @param Query_Image      $query_image  Query image handler.
	 * @param Index_Repository $repository   Index repository.
	 * @param Bulk_Indexer     $bulk_indexer Bulk indexer.
	 * @param Index_Progress   $progress     Progress tracker.
	 * @param Rate_Limiter     $rate_limiter Rate limiter.
	 */
	public function __construct(
		private Search_Pipeline $pipeline,
		private Query_Image $query_image,
		private Index_Repository $repository,
		private Bulk_Indexer $bulk_indexer,
		private Index_Progress $progress,
		private Rate_Limiter $rate_limiter
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
				'permission_callback' => static function () {
					$settings   = get_option( 'snopix_settings', array( 'search_visibility' => 'anyone' ) );
					$visibility = isset( $settings['search_visibility'] ) && 'logged_in' === $settings['search_visibility']
						? 'logged_in'
						: 'anyone';

					if ( 'logged_in' === $visibility ) {
						return is_user_logged_in();
					}
					return true;
				},
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
					'after_id' => array(
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
					'per_page' => array(
						'sanitize_callback' => static fn( $v ) => max( 1, min( 200, absint( $v ) ) ),
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
			'/reset-progress',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_reset_progress' ),
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

		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_get_settings' ),
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_update_settings' ),
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
					'args'                => array(
						'search_visibility' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => static fn( $v ) => in_array( $v, array( 'anyone', 'logged_in' ), true ),
						),
					),
				),
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
		$ip = Rate_Limiter::resolve_client_ip();

		if ( '' !== $ip && ! $this->rate_limiter->is_allowed( $ip ) ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Too many requests.', 'snopix' ),
				array( 'status' => 429 )
			);
		}

		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new \WP_Error(
				'no_file',
				__( 'No file provided.', 'snopix' ),
				array( 'status' => 400 )
			);
		}

		$attachment_id = $this->query_image->from_upload( $files['file'] );

		if ( false === $attachment_id ) {
			return new \WP_Error(
				'unprocessable_image',
				__( 'Could not process image.', 'snopix' ),
				array( 'status' => 422 )
			);
		}

		try {
			$results = $this->pipeline->search( $attachment_id );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error(
				'unprocessable_image',
				__( 'Could not process image.', 'snopix' ),
				array( 'status' => 422 )
			);
		} finally {
			$this->query_image->cleanup( $attachment_id );
		}

		$results = array_values(
			array_filter(
				$results,
				static function ( $r ) use ( $attachment_id ) {
					if ( $r->attachment_id === $attachment_id ) {
						return false;
					}
					// Never leak attachments that aren't publicly attached to a
					// published post. Attachment posts inherit their parent's
					// status, so a status other than 'inherit'+published parent
					// (or no parent) is treated as non-public for /search.
					$post = get_post( $r->attachment_id );
					if ( ! $post || 'attachment' !== $post->post_type ) {
						return false;
					}
					if ( 'inherit' === $post->post_status ) {
						$parent_id = (int) $post->post_parent;
						if ( $parent_id > 0 ) {
							$parent_status = get_post_status( $parent_id );
							return 'publish' === $parent_status;
						}
						// Orphan attachments (no parent) are admin-uploaded
						// library items — surface them.
						return true;
					}
					return 'publish' === $post->post_status;
				}
			)
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
	 * Handle GET /status — index counts plus current bulk-job progress so
	 * the admin app can hydrate its state machine on mount without an
	 * extra round-trip.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_status(): \WP_REST_Response {
		$counts             = $this->repository->get_counts();
		$counts['progress'] = $this->progress->get();
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
		$after_id = absint( $request->get_param( 'after_id' ) ?? 0 );
		$per_page = max( 1, min( 200, absint( $request->get_param( 'per_page' ) ?? 25 ) ) );
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?? '' );

		$rows = $this->repository->get_paginated( $after_id, $per_page, $search );

		if ( ! empty( $rows ) ) {
			$ids = array_map( static fn( $r ) => (int) $r['attachment_id'], $rows );
			// Prime the post + postmeta object cache so the hydration loop only
			// hits the cache instead of N+1 SQL per row.
			_prime_post_caches( $ids, true, true );
		}

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
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_reindex(): \WP_REST_Response|\WP_Error {
		if ( ! $this->bulk_indexer->schedule() ) {
			return new \WP_Error(
				'indexing_running',
				__( 'A bulk indexing job is already in progress.', 'snopix' ),
				array( 'status' => 409 )
			);
		}
		return new \WP_REST_Response( array( 'scheduled' => true ), 200 );
	}

	/**
	 * Handle POST /reset-progress — abort any in-flight bulk job and clear
	 * progress state. Used by the UI to recover from a stalled chain.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_reset_progress(): \WP_REST_Response {
		$this->bulk_indexer->abort();
		return new \WP_REST_Response( array( 'reset' => true ), 200 );
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
				__( 'Not found.', 'snopix' ),
				array( 'status' => 404 )
			);
		}

		return new \WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Handle POST /tools/reindex-all — wipe + reindex every attachment.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_reindex_all(): \WP_REST_Response|\WP_Error {
		if ( ! $this->bulk_indexer->schedule_all() ) {
			return new \WP_Error(
				'indexing_running',
				__( 'A bulk indexing job is already in progress.', 'snopix' ),
				array( 'status' => 409 )
			);
		}
		return new \WP_REST_Response( array( 'scheduled' => true ), 200 );
	}

	/**
	 * Handle POST /tools/clear-index — delete every index row.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_clear_index(): \WP_REST_Response|\WP_Error {
		if ( $this->bulk_indexer->is_running() ) {
			return new \WP_Error(
				'indexing_running',
				__( 'Cannot clear the index while a bulk indexing job is in progress.', 'snopix' ),
				array( 'status' => 409 )
			);
		}
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
	 * Rejects with 409 while a bulk index job is in flight because resetting
	 * the progress transient mid-run would leave the cron chain incrementing
	 * a freshly-zeroed counter, producing wildly wrong "done" values and a
	 * premature transition to status=done.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_clear_cache(): \WP_REST_Response|\WP_Error {
		if ( $this->bulk_indexer->is_running() ) {
			return new \WP_Error(
				'indexing_running',
				__( 'Cannot clear the cache while a bulk indexing job is in progress.', 'snopix' ),
				array( 'status' => 409 )
			);
		}
		$this->repository->flush_cache();
		$this->progress->reset();
		return new \WP_REST_Response( array( 'cleared' => true ), 200 );
	}

	/**
	 * Handle GET /settings — return the current snopix_settings option.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_get_settings(): \WP_REST_Response {
		$settings = get_option( 'snopix_settings', array( 'search_visibility' => 'anyone' ) );
		if ( ! is_array( $settings ) ) {
			$settings = array( 'search_visibility' => 'anyone' );
		}

		$visibility = isset( $settings['search_visibility'] ) && 'logged_in' === $settings['search_visibility']
			? 'logged_in'
			: 'anyone';

		return new \WP_REST_Response(
			array(
				'search_visibility' => $visibility,
			),
			200
		);
	}

	/**
	 * Handle POST /settings — persist a sanitised snopix_settings payload.
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$current = get_option( 'snopix_settings', array( 'search_visibility' => 'anyone' ) );
		if ( ! is_array( $current ) ) {
			$current = array( 'search_visibility' => 'anyone' );
		}

		$visibility = $request->get_param( 'search_visibility' );
		if ( in_array( $visibility, array( 'anyone', 'logged_in' ), true ) ) {
			$current['search_visibility'] = $visibility;
		}

		update_option( 'snopix_settings', $current );

		return new \WP_REST_Response(
			array(
				'search_visibility' => $current['search_visibility'] ?? 'anyone',
			),
			200
		);
	}
}
