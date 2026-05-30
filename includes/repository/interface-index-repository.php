<?php
/**
 * Index repository contract.
 *
 * @package Snopix
 */

namespace Snopix\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
interface Index_Repository_Interface {
	/**
	 * Insert or update fingerprint payload.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param array<string, mixed> $fingerprint Fingerprint payload.
	 *
	 * @return bool
	 */
	public function upsert( int $attachment_id, array $fingerprint ): bool;

	/**
	 * Record an unindexable attachment so the dashboard can surface a
	 * "failed" count and the bulk indexer skips it next time.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $error_code    Short machine-readable failure reason.
	 *
	 * @return bool
	 */
	public function mark_failed( int $attachment_id, string $error_code ): bool;

	/**
	 * Fetch indexed rows within `$max_distance` Hamming bits of the query
	 * pHash. Used as a SQL-side pre-filter in the search pipeline so the
	 * entire snopix_index table never lands in PHP.
	 *
	 * @param string $query_phash  16-char lowercase hex query hash.
	 * @param int    $max_distance Maximum Hamming distance to return.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_candidates_for_hamming( string $query_phash, int $max_distance ): array;

	/**
	 * Get all indexed rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_indexed(): array;

	/**
	 * Get paginated rows using keyset cursor.
	 *
	 * @param int    $after_id Return rows with attachment_id less than this value. 0 = first page.
	 * @param int    $per_page Page size.
	 * @param string $search   Search term.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_paginated( int $after_id, int $per_page, string $search ): array;

	/**
	 * Get total/indexed/pending counts.
	 *
	 * @return array<string, int>
	 */
	public function get_counts(): array;

	/**
	 * Get unindexed attachment IDs.
	 *
	 * @param array<string> $allowed_mime Optional MIME allowlist to restrict results.
	 *
	 * @return array<int>
	 */
	public function get_unindexed_ids( array $allowed_mime = array() ): array;

	/**
	 * Delete one indexed row by attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return int Rows deleted (0 if the row did not exist or on failure).
	 */
	public function delete( int $attachment_id ): int;

	/**
	 * Delete every row in the index table.
	 *
	 * @return int Rows deleted, or 0 on no-op / failure.
	 */
	public function clear_all(): int;

	/**
	 * Delete rows whose attachment no longer exists in wp_posts.
	 *
	 * @return int Rows deleted.
	 */
	public function delete_orphans(): int;

	/**
	 * Count rows whose attachment no longer exists in wp_posts.
	 *
	 * @return int
	 */
	public function get_orphan_count(): int;

	/**
	 * Get rows needed for duplicate detection (attachment_id, phash, file_hash).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_with_hash(): array;

	/**
	 * Flush internal caches.
	 *
	 * @return void
	 */
	public function flush_cache(): void;
}
