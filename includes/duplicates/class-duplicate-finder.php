<?php
/**
 * Duplicate image finder — pure algorithm class.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Duplicates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PixelScout\Imaging\Similarity;

/**
 * Groups indexed images into duplicate sets using exact hash and perceptual hash.
 *
 * Returns groups of attachment IDs only; enrichment happens at the REST layer.
 */
class Duplicate_Finder {

	/**
	 * Maximum Hamming distance to consider two images perceptually identical.
	 */
	private const PHASH_THRESHOLD = 4;

	/**
	 * Constructor.
	 *
	 * @param Similarity $similarity Similarity metrics provider.
	 */
	public function __construct( private Similarity $similarity ) {}

	/**
	 * Find duplicate groups from indexed rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows with attachment_id, phash, file_hash.
	 *
	 * @return array<int, array{match_type: string, ids: array<int>}> Duplicate groups.
	 */
	public function find( array $rows ): array {
		$exact_groups      = $this->group_by_hash( $rows );
		$exact_ids         = $this->collect_ids( $exact_groups );
		$remaining         = array_values(
			array_filter( $rows, static fn( $r ) => ! isset( $exact_ids[ (int) $r['attachment_id'] ] ) )
		);
		$perceptual_groups = $this->group_by_phash( $remaining );

		$result = array();

		foreach ( $exact_groups as $group ) {
			$result[] = array(
				'match_type' => 'exact',
				'ids'        => array_values( array_map( static fn( $r ) => (int) $r['attachment_id'], $group ) ),
			);
		}

		foreach ( $perceptual_groups as $group ) {
			$result[] = array(
				'match_type' => 'perceptual',
				'ids'        => array_values( array_map( static fn( $r ) => (int) $r['attachment_id'], $group ) ),
			);
		}

		return $result;
	}

	/**
	 * Group rows by file_hash (exact byte duplicates).
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 *
	 * @return array<string, array<int, array<string, mixed>>> Groups (only those with 2+ members).
	 */
	private function group_by_hash( array $rows ): array {
		$groups = array();

		foreach ( $rows as $row ) {
			$hash = (string) ( $row['file_hash'] ?? '' );
			if ( '' === $hash ) {
				continue;
			}
			$groups[ $hash ][] = $row;
		}

		return array_values( array_filter( $groups, static fn( $g ) => count( $g ) >= 2 ) );
	}

	/**
	 * Group rows by pHash using Union-Find (perceptual duplicates).
	 *
	 * @param array<int, array<string, mixed>> $rows Rows with non-empty phash.
	 *
	 * @return array<int, array<int, array<string, mixed>>> Groups (only those with 2+ members).
	 */
	private function group_by_phash( array $rows ): array {
		// Filter out rows with empty phash to avoid meaningless comparisons.
		$rows = array_values(
			array_filter( $rows, static fn( $r ) => '' !== ( $r['phash'] ?? '' ) )
		);

		$n = count( $rows );
		if ( $n < 2 ) {
			return array();
		}

		// Union-Find initialisation: each node is its own root.
		$parent = array();
		foreach ( $rows as $row ) {
			$id            = (int) $row['attachment_id'];
			$parent[ $id ] = $id;
		}

		// O(n²) pair comparison — acceptable for typical media libraries.
		for ( $i = 0; $i < $n; $i++ ) {
			for ( $j = $i + 1; $j < $n; $j++ ) {
				$dist = $this->similarity->hamming_distance(
					(string) $rows[ $i ]['phash'],
					(string) $rows[ $j ]['phash']
				);
				if ( $dist <= self::PHASH_THRESHOLD ) {
					$this->union( $parent, (int) $rows[ $i ]['attachment_id'], (int) $rows[ $j ]['attachment_id'] );
				}
			}
		}

		// Collect groups by root.
		$groups = array();
		foreach ( $rows as $row ) {
			$root              = $this->find_root( $parent, (int) $row['attachment_id'] );
			$groups[ $root ][] = $row;
		}

		return array_values( array_filter( $groups, static fn( $g ) => count( $g ) >= 2 ) );
	}

	/**
	 * Collect all attachment IDs from a list of groups into a keyed lookup.
	 *
	 * @param array<int, array<int, array<string, mixed>>> $groups Groups.
	 *
	 * @return array<int, true>
	 */
	private function collect_ids( array $groups ): array {
		$ids = array();
		foreach ( $groups as $group ) {
			foreach ( $group as $row ) {
				$ids[ (int) $row['attachment_id'] ] = true;
			}
		}
		return $ids;
	}

	/**
	 * Find root with path compression.
	 *
	 * @param array<int, int> $parents Parent map (passed by reference).
	 * @param int             $id      Node ID.
	 *
	 * @return int Root ID.
	 */
	private function find_root( array &$parents, int $id ): int {
		while ( $parents[ $id ] !== $id ) {
			$parents[ $id ] = $parents[ $parents[ $id ] ];
			$id             = $parents[ $id ];
		}
		return $id;
	}

	/**
	 * Union two nodes.
	 *
	 * @param array<int, int> $parents Parent map (passed by reference).
	 * @param int             $a       First node.
	 * @param int             $b       Second node.
	 *
	 * @return void
	 */
	private function union( array &$parents, int $a, int $b ): void {
		$ra = $this->find_root( $parents, $a );
		$rb = $this->find_root( $parents, $b );
		if ( $ra !== $rb ) {
			$parents[ $ra ] = $rb;
		}
	}
}
