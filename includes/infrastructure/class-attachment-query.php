<?php
/**
 * WP-native attachment query helpers.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps WP_Query for image-attachment reads.
 *
 * Rules enforced here so callers never need to:
 *   - No posts_per_page = -1; use get_all_ids() for unbounded needs.
 *   - No offset pagination; keyset (ID cursor) only.
 */
class Attachment_Query {

	/**
	 * Rows fetched per internal batch in get_all_ids().
	 */
	private const BATCH_SIZE = 200;

	/**
	 * Hard cap for title-search results.
	 */
	private const SEARCH_LIMIT = 500;

	/**
	 * Count all image attachments via wp_count_attachments().
	 *
	 * @return int
	 */
	public static function count(): int {
		$counts = wp_count_attachments();
		$total  = 0;
		foreach ( (array) $counts as $mime => $count ) {
			if ( str_starts_with( (string) $mime, 'image/' ) ) {
				$total += (int) $count;
			}
		}
		return $total;
	}

	/**
	 * Fetch a page of image attachment IDs using keyset cursor (ID ASC).
	 *
	 * @param int $after_id Return IDs greater than this value. 0 = start from the beginning.
	 * @param int $limit    Max rows to return.
	 *
	 * @return array<int>
	 */
	public static function get_ids( int $after_id = 0, int $limit = self::BATCH_SIZE ): array {
		$filter = null;

		if ( $after_id > 0 ) {
			$after  = $after_id;
			$filter = static function ( string $where ) use ( $after ): string {
				global $wpdb;
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $after );
			};
			add_filter( 'posts_where', $filter );
		}

		$query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_mime_type'         => 'image',
				'post_status'            => 'inherit',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( $filter ) {
			remove_filter( 'posts_where', $filter );
		}

		return array_map( 'absint', (array) $query->posts );
	}

	/**
	 * Return all image attachment IDs by iterating get_ids() in batches.
	 *
	 * Never issues a single unbounded query.
	 *
	 * @return array<int>
	 */
	public static function get_all_ids(): array {
		$ids      = array();
		$after_id = 0;

		do {
			$batch       = static::get_ids( $after_id, self::BATCH_SIZE );
			$batch_count = count( $batch );
			$ids         = array_merge( $ids, $batch );
			$after_id    = empty( $batch ) ? 0 : (int) end( $batch );
		} while ( self::BATCH_SIZE === $batch_count );

		return $ids;
	}

	/**
	 * Search image attachment IDs by post title or attached filename.
	 *
	 * Capped at SEARCH_LIMIT rows — title search is user-driven and bounded.
	 *
	 * @param string $title Search term.
	 *
	 * @return array<int>
	 */
	public static function search_ids( string $title ): array {
		$title_query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				's'                      => $title,
				'search_columns'         => array( 'post_title' ),
				'posts_per_page'         => self::SEARCH_LIMIT,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$file_ids = array();

		if ( '' !== $title ) {
			$file_query = new \WP_Query(
				array(
					'post_type'              => 'attachment',
					'post_status'            => 'inherit',
					'posts_per_page'         => self::SEARCH_LIMIT,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_wp_attached_file',
							'value'   => $title,
							'compare' => 'LIKE',
						),
					),
				)
			);

			$file_ids = (array) $file_query->posts;
		}

		$ids = array_unique( array_merge( (array) $title_query->posts, $file_ids ) );
		$ids = array_slice( $ids, 0, self::SEARCH_LIMIT );

		return array_map( 'absint', $ids );
	}
}
