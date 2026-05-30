<?php
/**
 * Single image indexing service.
 *
 * @package Snopix
 */

namespace Snopix\Indexing;

use Snopix\Repository\Index_Repository;
use Snopix\Search\Fingerprint_Factory;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles indexing of individual images via fingerprint generation.
 */
class Image_Indexer {
	/**
	 * Constructor.
	 *
	 * @param Mime_Validator      $validator  MIME validator.
	 * @param Fingerprint_Factory $factory  Fingerprint factory.
	 * @param Index_Repository    $repository Index repository.
	 */
	public function __construct(
		private Mime_Validator $validator,
		private Fingerprint_Factory $factory,
		private Index_Repository $repository
	) {}

	/**
	 * Index a single attachment image.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool True if indexed, false otherwise.
	 */
	public function index_single( int $attachment_id ): bool {
		$mime = get_post_mime_type( $attachment_id );

		if ( ! $this->validator->is_allowed( $mime ) ) {
			$this->repository->mark_failed( $attachment_id, 'unsupported_mime' );
			return false;
		}

		$fingerprint = $this->factory->generate( $attachment_id );

		if ( empty( $fingerprint ) ) {
			$this->repository->mark_failed( $attachment_id, 'unfingerprintable' );
			return false;
		}

		$meta        = wp_get_attachment_metadata( $attachment_id );
		$file        = get_attached_file( $attachment_id );
		$fingerprint = array_merge(
			$fingerprint,
			array(
				'mime_type'  => $mime,
				'width'      => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
				'height'     => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
				'file_size'  => ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : 0,
				'file_hash'  => ( $file && file_exists( $file ) && str_starts_with( $mime, 'image/' ) ) ? (string) md5_file( $file ) : '',
				'error_code' => '',
			)
		);

		return $this->repository->upsert( $attachment_id, $fingerprint );
	}

	/**
	 * Delete indexed entry for attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool
	 */
	public function on_delete( int $attachment_id ): bool {
		return $this->repository->delete( $attachment_id ) > 0;
	}
}
