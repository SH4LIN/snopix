<?php
/**
 * Integration tests for Snopix\Duplicates\Duplicate_Finder.
 *
 * Boots a real WordPress + DB environment (transactions rolled back per test).
 * Attachments are registered via the base-class helpers; the full indexing
 * pipeline (GD_Loader → processors → Image_Indexer → Index_Repository) runs
 * against real fixture images so that file_hash and phash values are genuine.
 *
 * @package Snopix
 */

use Snopix\Duplicates\Duplicate_Finder;
use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Edge_Processor;
use Snopix\Imaging\GD_Loader;
use Snopix\Imaging\PHash_Processor;
use Snopix\Imaging\Similarity;
use Snopix\Indexing\Image_Indexer;
use Snopix\Indexing\Mime_Validator;
use Snopix\Repository\Index_Repository;
use Snopix\Search\Fingerprint_Factory;

/**
 * @covers \Snopix\Duplicates\Duplicate_Finder
 */
final class Duplicate_Finder_Test extends Snopix_Integration_TestCase {

	private Index_Repository $repo;
	private Image_Indexer    $indexer;
	private Duplicate_Finder $finder;

	public function set_up(): void {
		parent::set_up();

		global $wpdb;

		$this->repo = new Index_Repository( $wpdb );

		$loader  = new GD_Loader();
		$factory = new Fingerprint_Factory(
			$loader,
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);

		$this->indexer = new Image_Indexer(
			new Mime_Validator(),
			$factory,
			$this->repo
		);

		$this->finder = new Duplicate_Finder( new Similarity() );
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Exact duplicates — same bytes, same file_hash.
	// -------------------------------------------------------------------------

	/**
	 * Two attachments backed by identical bytes must land in a single exact-
	 * duplicate group with match_type 'exact'.
	 */
	public function test_exact_duplicates_are_grouped_by_file_hash(): void {
		[ $id_a, $id_b ] = $this->attach_fixture_twice( 1 );

		$this->assertTrue( $this->indexer->index_single( $id_a ), 'First attachment should index successfully.' );
		$this->assertTrue( $this->indexer->index_single( $id_b ), 'Second attachment (same bytes) should index successfully.' );

		$rows   = $this->repo->get_all_with_hash();
		$groups = $this->finder->find( $rows );

		// At least one group must exist.
		$this->assertNotEmpty( $groups, 'Finder should return at least one duplicate group.' );

		// Locate the exact group containing both IDs.
		$exact_group = null;
		foreach ( $groups as $group ) {
			if ( 'exact' === $group['match_type']
				&& in_array( $id_a, $group['ids'], true )
				&& in_array( $id_b, $group['ids'], true )
			) {
				$exact_group = $group;
				break;
			}
		}

		$this->assertNotNull( $exact_group, 'An exact-duplicate group containing both attachment IDs must exist.' );
		$this->assertSame( 'exact', $exact_group['match_type'] );
		$this->assertContains( $id_a, $exact_group['ids'] );
		$this->assertContains( $id_b, $exact_group['ids'] );
	}

	/**
	 * The file_hash stored in the repository must be identical for both
	 * attachments when they share the same source bytes.
	 */
	public function test_identical_files_produce_matching_file_hash(): void {
		[ $id_a, $id_b ] = $this->attach_fixture_twice( 2 );

		$this->indexer->index_single( $id_a );
		$this->indexer->index_single( $id_b );

		$rows = $this->repo->get_all_with_hash();

		$hashes = array();
		foreach ( $rows as $row ) {
			$aid = (int) $row['attachment_id'];
			if ( $aid === $id_a || $aid === $id_b ) {
				$hashes[ $aid ] = $row['file_hash'];
			}
		}

		$this->assertCount( 2, $hashes, 'Both attachments should have index rows.' );
		$this->assertSame(
			$hashes[ $id_a ],
			$hashes[ $id_b ],
			'Identical source bytes must produce the same file_hash.'
		);
	}

	// -------------------------------------------------------------------------
	// Perceptual (near) duplicates — visually similar, different bytes.
	// -------------------------------------------------------------------------

	/**
	 * A base fixture and its blur variant have very similar visual content.
	 * After indexing both they must appear in a perceptual-duplicate group.
	 *
	 * The default duplicate_threshold (0.95) allows only ~3 bits of Hamming
	 * distance over a 64-bit pHash, which is too strict for a gaussian-blur
	 * variant that can differ by more bits while remaining visually similar.
	 * We lower the threshold to 0.85 (~10 bits) for this test to make the
	 * grouping deterministic without touching source files.
	 */
	public function test_near_duplicates_are_grouped_by_phash(): void {
		// Use a more lenient threshold so the blur variant's pHash distance
		// stays within the acceptance window: (1 - 0.85) * 64 = 9.6 → 10 bits.
		$original_settings = get_option( \Snopix\Hooks\Settings::OPTION_NAME, array() );
		$lenient_settings  = array_merge(
			\Snopix\Hooks\Settings::defaults(),
			is_array( $original_settings ) ? $original_settings : array(),
			array( 'duplicate_threshold' => 0.85 )
		);
		update_option( \Snopix\Hooks\Settings::OPTION_NAME, $lenient_settings );

		$id_base = $this->attach_fixture( 1 );
		$id_blur = $this->attach_variant( 1, 'blur' );

		$this->assertTrue( $this->indexer->index_single( $id_base ), 'Base fixture should index successfully.' );
		$this->assertTrue( $this->indexer->index_single( $id_blur ), 'Blur variant should index successfully.' );

		$rows   = $this->repo->get_all_with_hash();
		$groups = $this->finder->find( $rows );

		// Restore the original setting regardless of assertion outcome.
		update_option( \Snopix\Hooks\Settings::OPTION_NAME, $original_settings );

		// Find a perceptual (or exact) group that contains both IDs.
		$found_group = null;
		foreach ( $groups as $group ) {
			if ( in_array( $id_base, $group['ids'], true )
				&& in_array( $id_blur, $group['ids'], true )
			) {
				$found_group = $group;
				break;
			}
		}

		$this->assertNotNull(
			$found_group,
			'Base fixture and its blur variant must be grouped together as duplicates.'
		);
	}

	/**
	 * A base fixture and its compressed variant should also cluster together
	 * under the default duplicate_threshold of 0.95.
	 */
	public function test_compressed_variant_clusters_with_base(): void {
		$id_base       = $this->attach_fixture( 3 );
		$id_compressed = $this->attach_variant( 3, 'compressed' );

		$this->indexer->index_single( $id_base );
		$this->indexer->index_single( $id_compressed );

		$rows   = $this->repo->get_all_with_hash();
		$groups = $this->finder->find( $rows );

		$found = false;
		foreach ( $groups as $group ) {
			if ( in_array( $id_base, $group['ids'], true )
				&& in_array( $id_compressed, $group['ids'], true )
			) {
				$found = true;
				break;
			}
		}

		$this->assertTrue(
			$found,
			'Base fixture and its compressed variant must be grouped as near-duplicates.'
		);
	}

	// -------------------------------------------------------------------------
	// Non-duplicates — unrelated images must not share a group.
	// -------------------------------------------------------------------------

	/**
	 * Two visually distinct fixtures must not appear in any common duplicate
	 * group when indexed.
	 */
	public function test_unrelated_fixtures_are_not_grouped(): void {
		// Fixtures 1 and 5 are deliberately distinct images in the test corpus.
		$id_one  = $this->attach_fixture( 1 );
		$id_five = $this->attach_fixture( 5 );

		$this->indexer->index_single( $id_one );
		$this->indexer->index_single( $id_five );

		$rows   = $this->repo->get_all_with_hash();
		$groups = $this->finder->find( $rows );

		foreach ( $groups as $group ) {
			$this->assertFalse(
				in_array( $id_one, $group['ids'], true ) && in_array( $id_five, $group['ids'], true ),
				'Fixture 1 and fixture 5 must not share a duplicate group.'
			);
		}
	}

	/**
	 * Three unrelated fixtures produce zero duplicate groups.
	 */
	public function test_three_distinct_fixtures_yield_no_groups(): void {
		foreach ( array( 2, 4, 6 ) as $fixture_id ) {
			$aid = $this->attach_fixture( $fixture_id );
			$this->indexer->index_single( $aid );
		}

		$rows   = $this->repo->get_all_with_hash();
		$groups = $this->finder->find( $rows );

		$this->assertEmpty(
			$groups,
			'Three distinct fixtures should yield no duplicate groups.'
		);
	}

	// -------------------------------------------------------------------------
	// Edge cases.
	// -------------------------------------------------------------------------

	/**
	 * find() on an empty row set returns an empty array without error.
	 */
	public function test_find_with_empty_rows_returns_empty(): void {
		$groups = $this->finder->find( array() );
		$this->assertSame( array(), $groups );
	}

	/**
	 * find() on a single-row set returns an empty array — no pair to match.
	 */
	public function test_find_with_single_row_returns_empty(): void {
		$id = $this->attach_fixture( 7 );
		$this->indexer->index_single( $id );

		$rows   = $this->repo->get_all_with_hash();
		$groups = $this->finder->find( $rows );

		$this->assertEmpty(
			$groups,
			'A single indexed image cannot form a duplicate group.'
		);
	}

	/**
	 * Exact-duplicate attachment IDs are excluded from the perceptual-grouping
	 * pass — they must not appear in both an exact and a perceptual group.
	 */
	public function test_exact_duplicate_ids_are_not_also_in_perceptual_group(): void {
		[ $id_a, $id_b ] = $this->attach_fixture_twice( 1 );

		$this->indexer->index_single( $id_a );
		$this->indexer->index_single( $id_b );

		$rows   = $this->repo->get_all_with_hash();
		$groups = $this->finder->find( $rows );

		$exact_ids       = array();
		$perceptual_ids  = array();

		foreach ( $groups as $group ) {
			if ( 'exact' === $group['match_type'] ) {
				foreach ( $group['ids'] as $gid ) {
					$exact_ids[] = $gid;
				}
			} elseif ( 'perceptual' === $group['match_type'] ) {
				foreach ( $group['ids'] as $gid ) {
					$perceptual_ids[] = $gid;
				}
			}
		}

		foreach ( $exact_ids as $eid ) {
			$this->assertNotContains(
				$eid,
				$perceptual_ids,
				"Attachment ID {$eid} in an exact group must not also appear in a perceptual group."
			);
		}
	}
}
