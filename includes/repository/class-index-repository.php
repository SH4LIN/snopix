<?php
/**
 * Image index repository implementation.
 *
 * @package Snopix
 */

namespace Snopix\Repository;

use Snopix\Infrastructure\Attachment_Query;
use Snopix\Infrastructure\Query;
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
	private const CACHE_GROUP = 'snopix';

	/**
	 * Table slug for Query builder.
	 */
	private const TABLE = 'snopix_index';

	/**
	 * Cache key for full index.
	 */
	private const CACHE_ALL = 'snopix_all_indexed';

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
			->where( 'error_code', '', '=', '%s' )
			->order_by( 'indexed_at', 'DESC' )
			->get( ARRAY_A );

		$rows = is_array( $rows ) ? $rows : array();
		wp_cache_set( self::CACHE_ALL, $rows, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );

		return $rows;
	}

	/**
	 * Fetch indexed rows whose pHash is within `$max_distance` Hamming bits of
	 * the query hash. Computed in MySQL via `BIT_COUNT(CONV(...) ^ CONV(...))`
	 * so the entire snopix_index table never has to land in PHP.
	 *
	 * @param string $query_phash 16-char lowercase hex query hash.
	 * @param int    $max_distance Maximum Hamming distance to return.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_candidates_for_hamming( string $query_phash, int $max_distance ): array {
		$query_phash = strtolower( $query_phash );
		if ( 16 !== strlen( $query_phash ) || ! ctype_xdigit( $query_phash ) ) {
			return array();
		}

		$index_table = esc_sql( $this->wpdb->prefix . self::TABLE );

		// CONV(hex,16,10) is limited to 18-digit unsigned, so 64-bit pHashes
		// must be split into two 32-bit halves and the popcount summed.
		$query_high = substr( $query_phash, 0, 8 );
		$query_low  = substr( $query_phash, 8, 8 );

		// $index_table is built from $wpdb->prefix only — no user input — and
		// table identifiers cannot be parameterised via $wpdb->prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare(
			'SELECT attachment_id, phash, color_vector, edge_vector, indexed_at '
			. "FROM $index_table "
			. "WHERE error_code = '' "
			. 'AND phash <> %s '
			. 'AND ('
			. 'BIT_COUNT(CONV(SUBSTRING(phash, 1, 8), 16, 10) ^ CONV(%s, 16, 10))'
			. ' + '
			. 'BIT_COUNT(CONV(SUBSTRING(phash, 9, 8), 16, 10) ^ CONV(%s, 16, 10))'
			. ') <= %d',
			'',
			$query_high,
			$query_low,
			$max_distance
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Record that an attachment could not be indexed (unsupported MIME or
	 * unfingerprintable bytes).
	 *
	 * Stores a row with empty fingerprints and a non-empty `error_code` so
	 * the dashboard can surface a "failed" count and the bulk indexer does
	 * not retry the attachment on every pass.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $error_code    Short machine-readable failure reason.
	 *
	 * @return bool
	 */
	public function mark_failed( int $attachment_id, string $error_code ): bool {
		$result = Query::create()
			->from( self::TABLE )
			->upsert(
				array(
					'attachment_id' => $attachment_id,
					'phash'         => '',
					'color_vector'  => null,
					'edge_vector'   => null,
					'file_hash'     => '',
					'error_code'    => $error_code,
					'indexed_at'    => current_time( 'mysql' ),
				),
				array( 'phash', 'color_vector', 'edge_vector', 'file_hash', 'error_code', 'indexed_at' )
			);

		if ( $result ) {
			$this->flush_cache();
		}

		return $result;
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
			->select( array( 'attachment_id', 'phash', 'mime_type', 'file_size', 'width', 'height', 'indexed_at', 'error_code' ) )
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
			->where( 'error_code', '', '=', '%s' )
			->select( 'COUNT(*)' )
			->get_var();

		$failed = (int) Query::create()
			->from( self::TABLE )
			->where( 'error_code', '', '!=', '%s' )
			->select( 'COUNT(*)' )
			->get_var();

		$total = Attachment_Query::count();

		return array(
			'total'   => $total,
			'indexed' => $indexed,
			'failed'  => $failed,
			'pending' => max( 0, $total - $indexed - $failed ),
		);
	}

	/**
	 * Get IDs that are not yet indexed.
	 *
	 * @param array<string> $allowed_mime Optional MIME allowlist; when given, only
	 *                                    attachments of these exact types are
	 *                                    returned (keeps unsupported types out of
	 *                                    the bulk queue).
	 *
	 * @return array<int>
	 */
	public function get_unindexed_ids( array $allowed_mime = array() ): array {
		$index_table = esc_sql( $this->wpdb->prefix . self::TABLE );
		$posts_table = esc_sql( $this->wpdb->posts );

		if ( empty( $allowed_mime ) ) {
			$mime_clause = "AND p.post_mime_type LIKE 'image/%' ";
		} else {
			$quoted      = array_map( static fn( $m ) => "'" . esc_sql( $m ) . "'", $allowed_mime );
			$mime_clause = 'AND p.post_mime_type IN (' . implode( ',', $quoted ) . ') ';
		}

		// Identifiers come from $wpdb; the query has no user-controlled
		// parameters, so $wpdb->prepare() is not needed.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $this->wpdb->get_col(
			"SELECT p.ID FROM $posts_table p "
			. "LEFT JOIN $index_table i ON p.ID = i.attachment_id "
			. "WHERE p.post_type = 'attachment' "
			. $mime_clause
			. 'AND i.attachment_id IS NULL '
			. 'ORDER BY p.ID ASC'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return is_array( $rows ) ? array_map( 'absint', $rows ) : array();
	}

	/**
	 * Get rows for duplicate detection: attachment_id, phash, file_hash.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_with_hash(): array {
		$rows = Query::create()
			->from( self::TABLE )
			->select( array( 'attachment_id', 'phash', 'file_hash' ) )
			->where( 'error_code', '', '=', '%s' )
			->get( ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete index row for attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return int Rows deleted (0 if the row did not exist or on failure).
	 */
	public function delete( int $attachment_id ): int {
		$result = Query::create()
			->from( self::TABLE )
			->where( 'attachment_id', $attachment_id, '=', '%d' )
			->delete();

		if ( false === $result ) {
			return 0;
		}

		$this->flush_cache();
		return $result;
	}

	/**
	 * Delete every row in the index table.
	 *
	 * @return int Rows deleted.
	 */
	public function clear_all(): int {
		$result = Query::create()->from( self::TABLE )->truncate();

		$this->flush_cache();
		return false === $result ? 0 : $result;
	}

	/**
	 * Delete index rows whose attachment no longer exists in wp_posts.
	 *
	 * @return int Rows deleted.
	 */
	public function delete_orphans(): int {
		$table = esc_sql( $this->wpdb->prefix . self::TABLE );
		$posts = esc_sql( $this->wpdb->posts );
		// Table identifiers come from $wpdb only and contain no user input;
		// $wpdb->prepare() is not needed because the query has no parameters.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->query(
			"DELETE i FROM {$table} i "
			. "LEFT JOIN {$posts} p ON i.attachment_id = p.ID AND p.post_type = 'attachment' "
			. 'WHERE p.ID IS NULL'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->flush_cache();
		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Count rows whose attachment no longer exists in wp_posts.
	 *
	 * @return int
	 */
	public function get_orphan_count(): int {
		$table = esc_sql( $this->wpdb->prefix . self::TABLE );
		$posts = esc_sql( $this->wpdb->posts );
		// Table identifiers come from $wpdb only and contain no user input;
		// $wpdb->prepare() is not needed because the query has no parameters.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} i "
			. "LEFT JOIN {$posts} p ON i.attachment_id = p.ID AND p.post_type = 'attachment' "
			. 'WHERE p.ID IS NULL'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $count;
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
