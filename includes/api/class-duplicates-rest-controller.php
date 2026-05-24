<?php
/**
 * REST API controller for duplicate detection endpoints.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Api;

use PixelScout\Duplicates\{Duplicate_Scanner, Duplicate_Progress};
use PixelScout\Infrastructure\Job_Status;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles ps/v1/duplicates/* REST routes.
 */
class Duplicates_REST_Controller {

	/**
	 * REST namespace.
	 */
	private const REST_NAMESPACE = 'ps/v1';

	/**
	 * Constructor.
	 *
	 * @param Duplicate_Scanner  $scanner  Duplicate scanner.
	 * @param Duplicate_Progress $progress Progress tracker.
	 */
	public function __construct(
		private Duplicate_Scanner $scanner,
		private Duplicate_Progress $progress
	) {}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/duplicates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/duplicates/scan',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_start_scan' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/duplicates/progress',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_progress' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/duplicates/reset',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_reset' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Handle GET /duplicates — return stored groups, enriched with attachment data.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_get(): \WP_REST_Response {
		$groups   = $this->scanner->get_results();
		$enriched = array_values(
			array_filter(
				array_map( array( $this, 'enrich_group' ), $groups ),
				static fn( $g ) => count( $g['images'] ) >= 2
			)
		);

		return new \WP_REST_Response(
			array(
				'groups'       => $enriched,
				'last_scanned' => $this->scanner->get_last_scanned(),
				'group_count'  => count( $enriched ),
			),
			200
		);
	}

	/**
	 * Handle POST /duplicates/scan — schedule a background scan.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_start_scan(): \WP_REST_Response|\WP_Error {
		if ( Job_Status::RUNNING === $this->progress->get()['status'] ) {
			return new \WP_Error(
				'scan_running',
				__( 'A duplicate scan is already in progress.', 'pixel-scout' ),
				array( 'status' => 409 )
			);
		}

		$this->scanner->schedule();
		return new \WP_REST_Response( array( 'scheduled' => true ), 200 );
	}

	/**
	 * Handle GET /duplicates/progress — poll scan progress.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_progress(): \WP_REST_Response {
		return new \WP_REST_Response( $this->progress->get(), 200 );
	}

	/**
	 * Handle POST /duplicates/reset — abort any in-flight scan and clear
	 * progress state. Lets the UI recover from a stalled or zombie scan.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_reset(): \WP_REST_Response {
		$this->scanner->abort();
		return new \WP_REST_Response( array( 'reset' => true ), 200 );
	}

	/**
	 * Enrich a duplicate group with WP attachment metadata.
	 *
	 * @param array{match_type: string, ids: array<int>} $group Raw group.
	 *
	 * @return array{match_type: string, images: array<int, array<string, mixed>>}
	 */
	private function enrich_group( array $group ): array {
		$images = array();

		foreach ( ( $group['ids'] ?? array() ) as $id ) {
			$id   = (int) $id;
			$post = get_post( $id );

			if ( ! $post || 'attachment' !== $post->post_type ) {
				continue;
			}

			$thumb = wp_get_attachment_image_src( $id, 'thumbnail' );
			$full  = wp_get_attachment_image_src( $id, 'full' );
			$file  = get_attached_file( $id );
			$meta  = wp_get_attachment_metadata( $id );
			$meta  = is_array( $meta ) ? $meta : array();
			$mime  = get_post_mime_type( $id );

			$images[] = array(
				'id'            => $id,
				'title'         => get_the_title( $id ),
				'filename'      => $file ? basename( $file ) : '',
				'file_size'     => ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : 0,
				'width'         => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
				'height'        => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
				'mime_type'     => $mime ? $mime : '',
				'thumbnail_url' => $thumb ? $thumb[0] : '',
				'full_url'      => $full ? $full[0] : '',
			);
		}

		return array(
			'match_type' => $group['match_type'] ?? 'perceptual',
			'images'     => $images,
		);
	}
}
