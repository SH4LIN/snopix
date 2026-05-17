<?php
/**
 * Index repository contract.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Repository;

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
	 * Get all indexed rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_indexed(): array;

	/**
	 * Get paginated rows.
	 *
	 * @param int    $page Current page.
	 * @param int    $per_page Page size.
	 * @param string $search Search term.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_paginated( int $page, int $per_page, string $search ): array;

	/**
	 * Get total/indexed/pending counts.
	 *
	 * @return array<string, int>
	 */
	public function get_counts(): array;

	/**
	 * Get unindexed attachment IDs.
	 *
	 * @return array<int>
	 */
	public function get_unindexed_ids(): array;

	/**
	 * Delete one indexed row by attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool
	 */
	public function delete( int $attachment_id ): bool;
}
