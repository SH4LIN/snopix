<?php
/**
 * Image index repository implementation.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Repository;

use PixelScout\Infrastructure\Attachment_Query;
use PixelScout\Infrastructure\Query;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides data access for the plugin image index.
 */
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
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 */
	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * Upsert index row.
	 *
	 * @param int                  $attachment_id Attachment ID.
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
			array(
				'attachment_id' => $attachment_id,
				'indexed_at'    => current_time( 'mysql' ),
			),
			$fingerprint
		);

		$update_columns = array_keys( $insert );
		$result         = Query::create()
			->from( self::TABLE )
			->upsert( $insert, $update_columns );

		if ( $result ) {
			$this->flush_cache();
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
			->select( array( 'attachment_id', 'phash', 'color_vector', 'edge_vector', 'indexed_at' ) )
			->order_by( 'indexed_at', 'DESC' )
			->get( ARRAY_A );

		$rows = is_array( $rows ) ? $rows : array();
		wp_cache_set( self::CACHE_ALL, $rows, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );

		return $rows;
	}

	/**
	 * Get paginated index rows using keyset cursor.
	 *
	 * @param int    $after_id Return rows with attachment_id less than this value. 0 = first page.
	 * @param int    $per_page Rows per page.
	 * @param string $search   Search term.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_paginated( int $after_id, int $per_page, string $search ): array {
		$query = Query::create()
			->from( self::TABLE )
			->select( array( 'attachment_id', 'phash', 'mime_type', 'file_size', 'width', 'height', 'indexed_at' ) )
			->order_by( 'attachment_id', 'DESC' )
			->limit( max( 1, $per_page ) );

		if ( $after_id > 0 ) {
			$query->where( 'attachment_id', $after_id, '<', '%d' );
		}

		if ( '' !== $search ) {
			$ids = Attachment_Query::search_ids( $search );
			if ( empty( $ids ) ) {
				return array();
			}
			$query->where_in( 'attachment_id', $ids );
		}

		$rows = $query->get( ARRAY_A );
		return is_array( $rows ) ? $rows : array();
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

		$total = Attachment_Query::count();

		return array(
			'total'   => $total,
			'indexed' => $indexed,
			'pending' => max( 0, $total - $indexed ),
		);
	}

	/**
	 * Get IDs that are not yet indexed.
	 *
	 * @return array<int>
	 */
	public function get_unindexed_ids(): array {
		$all_ids = Attachment_Query::get_all_ids();

		if ( empty( $all_ids ) ) {
			return array();
		}

		$indexed_ids = Query::create()
			->from( self::TABLE )
			->select( 'attachment_id' )
			->get_col();

		$indexed_ids = is_array( $indexed_ids ) ? array_map( 'absint', $indexed_ids ) : array();

		return array_values( array_diff( $all_ids, $indexed_ids ) );
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

		$this->flush_cache();
		return true;
	}

	/**
	 * Delete every row in the index table.
	 *
	 * @return int Rows deleted.
	 */
	public function clear_all(): int {
		$table = $this->wpdb->prefix . self::TABLE;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->query( "DELETE FROM {$table}" );

		$this->flush_cache();
		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Delete index rows whose attachment no longer exists in wp_posts.
	 *
	 * @return int Rows deleted.
	 */
	public function delete_orphans(): int {
		$table = $this->wpdb->prefix . self::TABLE;
		$posts = $this->wpdb->posts;
		$sql   = "DELETE i FROM {$table} i "
			. "LEFT JOIN {$posts} p ON i.attachment_id = p.ID AND p.post_type = 'attachment' "
			. 'WHERE p.ID IS NULL';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->query( $sql );

		$this->flush_cache();
		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Count rows whose attachment no longer exists in wp_posts.
	 *
	 * @return int
	 */
	public function get_orphan_count(): int {
		$table = $this->wpdb->prefix . self::TABLE;
		$posts = $this->wpdb->posts;
		$sql   = "SELECT COUNT(*) FROM {$table} i "
			. "LEFT JOIN {$posts} p ON i.attachment_id = p.ID AND p.post_type = 'attachment' "
			. 'WHERE p.ID IS NULL';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Clear repository caches.
	 *
	 * @return void
	 */
	public function flush_cache(): void {
		wp_cache_delete( self::CACHE_ALL, self::CACHE_GROUP );
	}

}
