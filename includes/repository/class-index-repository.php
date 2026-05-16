<?php
/**
 * Image index repository implementation.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Repository;

use PixelScout\Infrastructure\Query;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Index_Repository implements Index_Repository_Interface {
	/**
	 * Cache group.
	 */
	private const CACHE_GROUP = 'pixel-scout';

	/**
	 * Table slug for Query builder.
	 */
	private const TABLE = 'ps_index';

	/**
	 * Cache key for full index.
	 */
	private const CACHE_ALL = 'ps_all_indexed';

	/**
	 * @param \wpdb $wpdb WordPress database object.
	 */
	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * Upsert index row.
	 *
	 * @param int                 $attachment_id Attachment ID.
	 * @param array<string, mixed> $fingerprint Fingerprint payload.
	 *
	 * @return bool
	 */
	public function upsert( int $attachment_id, array $fingerprint ): bool {
		if ( isset( $fingerprint['color_vector'] ) && is_array( $fingerprint['color_vector'] ) ) {
			$fingerprint['color_vector'] = wp_json_encode( $fingerprint['color_vector'] );
		}
		if ( isset( $fingerprint['edge_vector'] ) && is_array( $fingerprint['edge_vector'] ) ) {
			$fingerprint['edge_vector'] = wp_json_encode( $fingerprint['edge_vector'] );
		}

		$insert = array_merge(
			[
				'attachment_id' => $attachment_id,
				'indexed_at'    => current_time( 'mysql' ),
			],
			$fingerprint
		);

		$update_columns = array_keys( $insert );
		$result         = Query::create()
			->from( self::TABLE )
			->upsert( $insert, $update_columns );

		if ( $result ) {
			$this->clear_cache();
		}

		return $result;
	}

	/**
	 * Get all indexed rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_indexed(): array {
		$cached = wp_cache_get( self::CACHE_ALL, self::CACHE_GROUP );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$rows = Query::create()
			->from( self::TABLE )
			->select( [ 'attachment_id', 'phash', 'color_vector', 'edge_vector', 'indexed_at' ] )
			->order_by( 'indexed_at', 'DESC' )
			->get( ARRAY_A );

		$rows = is_array( $rows ) ? $rows : [];
		wp_cache_set( self::CACHE_ALL, $rows, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );

		return $rows;
	}

	/**
	 * Get paginated index rows.
	 *
	 * @param int    $page Current page.
	 * @param int    $per_page Rows per page.
	 * @param string $search Search term.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_paginated( int $page, int $per_page, string $search ): array {
		$query = Query::create()
			->from( self::TABLE )
			->select( [ 'attachment_id', 'phash', 'mime_type', 'file_size', 'width', 'height', 'indexed_at' ] )
			->order_by( 'indexed_at', 'DESC' )
			->paginate( max( 1, $page ), max( 1, $per_page ) );

		if ( '' !== $search ) {
			$like = '%' . $this->escape_like( $search ) . '%';
			$query->where_raw(
				'attachment_id IN ( SELECT ID FROM ' . $this->wpdb->posts . ' WHERE post_title LIKE %s )',
				[ $like ]
			);
		}

		$rows = $query->get( ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Get index counts.
	 *
	 * @return array<string, int>
	 */
	public function get_counts(): array {
		$indexed = (int) Query::create()
			->from( self::TABLE )
			->select( 'COUNT(*)' )
			->get_var();

		$total = (int) Query::create()
			->from( $this->wpdb->posts )
			->select( 'COUNT(*)' )
			->where( 'post_type', 'attachment', '=', '%s' )
			->where_raw( 'post_mime_type LIKE %s', [ 'image/%' ] )
			->get_var();

		return [
			'total'   => $total,
			'indexed' => $indexed,
			'pending' => max( 0, $total - $indexed ),
		];
	}

	/**
	 * Get IDs that are not yet indexed.
	 *
	 * @return array<int>
	 */
	public function get_unindexed_ids(): array {
		$rows = Query::create()
			->from( $this->wpdb->posts, 'p' )
			->select( 'p.ID' )
			->left_join( self::TABLE, 'idx.attachment_id = p.ID', 'idx' )
			->where( 'p.post_type', 'attachment', '=', '%s' )
			->where_raw( 'p.post_mime_type LIKE %s', [ 'image/%' ] )
			->where_raw( 'idx.attachment_id IS NULL' )
			->get_col();

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( 'absint', $rows );
	}

	/**
	 * Delete index row for attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool
	 */
	public function delete( int $attachment_id ): bool {
		$result = Query::create()
			->from( self::TABLE )
			->where( 'attachment_id', $attachment_id, '=', '%d' )
			->delete();

		if ( false === $result ) {
			return false;
		}

		$this->clear_cache();
		return true;
	}

	/**
	 * Clear repository caches.
	 *
	 * @return void
	 */
	private function clear_cache(): void {
		wp_cache_delete( self::CACHE_ALL, self::CACHE_GROUP );
	}

	/**
	 * Escape LIKE values.
	 *
	 * @param string $value Input value.
	 *
	 * @return string
	 */
	private function escape_like( string $value ): string {
		return $this->wpdb->esc_like( $value );
	}
}
