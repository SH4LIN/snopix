<?php
/**
 * Tests for Duplicate_Finder grouping algorithm.
 *
 * @package Snopix
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use Snopix\Duplicates\Duplicate_Finder;
use Snopix\Imaging\Similarity;

/**
 * Duplicate_Finder unit tests — pure algorithm.
 */
class Snopix_Duplicate_Finder_Test extends Snopix_TestCase {

	private Duplicate_Finder $finder;

	/**
	 * Build a fresh Duplicate_Finder backed by a real Similarity instance.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->finder = new Duplicate_Finder( new Similarity() );
	}

	/**
	 * Build a row in the shape `Index_Repository::get_all_with_hash()` returns.
	 *
	 * @param int    $id         Attachment ID.
	 * @param string $phash      pHash hex.
	 * @param string $file_hash  File hash (md5).
	 *
	 * @return array<string, mixed>
	 */
	private function row( int $id, string $phash = '', string $file_hash = '' ): array {
		return array(
			'attachment_id' => $id,
			'phash'         => $phash,
			'file_hash'     => $file_hash,
		);
	}

	/**
	 * Empty input must produce no groups.
	 *
	 * @return void
	 */
	public function test_empty_input_returns_no_groups(): void {
		$this->assertSame( array(), $this->finder->find( array() ) );
	}

	/**
	 * A single row cannot form a duplicate group.
	 *
	 * @return void
	 */
	public function test_single_row_returns_no_groups(): void {
		$rows = array( $this->row( 1, '0000000000000000', 'aaa' ) );
		$this->assertSame( array(), $this->finder->find( $rows ) );
	}

	/**
	 * Two rows sharing a file_hash form a single exact-match group.
	 *
	 * @return void
	 */
	public function test_two_rows_same_file_hash_form_exact_group(): void {
		$rows = array(
			$this->row( 1, '0000000000000000', 'same' ),
			$this->row( 2, 'ffffffffffffffff', 'same' ),
		);

		$groups = $this->finder->find( $rows );

		$this->assertCount( 1, $groups );
		$this->assertSame( 'exact', $groups[0]['match_type'] );
		sort( $groups[0]['ids'] );
		$this->assertSame( array( 1, 2 ), $groups[0]['ids'] );
	}

	/**
	 * Rows with identical pHash but different file_hash form a perceptual group.
	 *
	 * @return void
	 */
	public function test_identical_phash_different_file_hash_forms_perceptual_group(): void {
		$rows = array(
			$this->row( 10, 'abcdef1234567890', 'h1' ),
			$this->row( 11, 'abcdef1234567890', 'h2' ),
		);

		$groups = $this->finder->find( $rows );

		$this->assertCount( 1, $groups );
		$this->assertSame( 'perceptual', $groups[0]['match_type'] );
		sort( $groups[0]['ids'] );
		$this->assertSame( array( 10, 11 ), $groups[0]['ids'] );
	}

	/**
	 * A pHash differing within the configured Hamming threshold must group.
	 *
	 * @return void
	 */
	public function test_phash_within_threshold_groups_perceptually(): void {
		// 0x0000 vs 0x0007 = 3 differing bits (threshold = 4).
		$rows = array(
			$this->row( 1, '0000000000000000', 'a' ),
			$this->row( 2, '0000000000000007', 'b' ),
		);

		$groups = $this->finder->find( $rows );
		$this->assertCount( 1, $groups );
		$this->assertSame( 'perceptual', $groups[0]['match_type'] );
	}

	/**
	 * A pHash differing beyond the Hamming threshold must NOT group.
	 *
	 * @return void
	 */
	public function test_phash_beyond_threshold_does_not_group(): void {
		// 0x0000 vs 0xff = 8 differing bits (threshold = 4).
		$rows = array(
			$this->row( 1, '0000000000000000', 'a' ),
			$this->row( 2, '00000000000000ff', 'b' ),
		);

		$this->assertSame( array(), $this->finder->find( $rows ) );
	}

	/**
	 * Rows with empty pHash must never be grouped perceptually.
	 *
	 * @return void
	 */
	public function test_empty_phash_skipped_in_perceptual_pass(): void {
		$rows = array(
			$this->row( 1, '', 'a' ),
			$this->row( 2, '', 'b' ),
		);

		$this->assertSame( array(), $this->finder->find( $rows ) );
	}

	/**
	 * Exact match takes precedence: rows in an exact group must not also appear
	 * in a perceptual group.
	 *
	 * @return void
	 */
	public function test_exact_match_excludes_rows_from_perceptual_pass(): void {
		$rows = array(
			$this->row( 1, 'aaaaaaaaaaaaaaaa', 'same' ),
			$this->row( 2, 'aaaaaaaaaaaaaaaa', 'same' ),
			$this->row( 3, 'aaaaaaaaaaaaaaaa', 'other' ),
		);

		$groups = $this->finder->find( $rows );

		// One exact group (1,2) and one perceptual group containing 3 with
		// nothing else is impossible — perceptual needs ≥2 members. So we
		// only see the exact group.
		$this->assertCount( 1, $groups );
		$this->assertSame( 'exact', $groups[0]['match_type'] );
		sort( $groups[0]['ids'] );
		$this->assertSame( array( 1, 2 ), $groups[0]['ids'] );
	}

	/**
	 * Union-find transitively groups A-B-C even when A and C are not directly
	 * compared within the Hamming threshold.
	 *
	 * @return void
	 */
	public function test_union_find_groups_transitively(): void {
		// All within 4 bits of their neighbour, even though edges between far
		// ends are larger than 4.
		$rows = array(
			$this->row( 1, '0000000000000000', 'a' ),
			$this->row( 2, '0000000000000003', 'b' ),
			$this->row( 3, '0000000000000007', 'c' ),
		);

		$groups = $this->finder->find( $rows );

		$this->assertCount( 1, $groups );
		$this->assertSame( 'perceptual', $groups[0]['match_type'] );
		sort( $groups[0]['ids'] );
		$this->assertSame( array( 1, 2, 3 ), $groups[0]['ids'] );
	}

	/**
	 * Distinct pHash clusters must produce distinct perceptual groups.
	 *
	 * @return void
	 */
	public function test_separate_clusters_form_separate_groups(): void {
		$rows = array(
			$this->row( 1, '0000000000000000', 'a' ),
			$this->row( 2, '0000000000000003', 'b' ),
			$this->row( 3, 'ffffffffffffffff', 'c' ),
			$this->row( 4, 'fffffffffffffffe', 'd' ),
		);

		$groups = $this->finder->find( $rows );

		$this->assertCount( 2, $groups );
		foreach ( $groups as $g ) {
			$this->assertSame( 'perceptual', $g['match_type'] );
			$this->assertCount( 2, $g['ids'] );
		}
	}

	/**
	 * `scanner_group_by_file_hash` exposes the exact-grouping helper used by
	 * Duplicate_Scanner during finalisation.
	 *
	 * @return void
	 */
	public function test_scanner_group_by_file_hash_returns_only_dupes(): void {
		$rows = array(
			$this->row( 1, 'aaaa', 'same' ),
			$this->row( 2, 'bbbb', 'same' ),
			$this->row( 3, 'cccc', 'unique' ),
			$this->row( 4, 'dddd', '' ),
		);

		$groups = $this->finder->scanner_group_by_file_hash( $rows );

		$this->assertCount( 1, $groups );
		$ids = array_map( static fn( $r ) => $r['attachment_id'], $groups[0] );
		sort( $ids );
		$this->assertSame( array( 1, 2 ), $ids );
	}

	/**
	 * Hamming wrapper exposed for the scanner must agree with the underlying
	 * Similarity service.
	 *
	 * @return void
	 */
	public function test_hamming_distance_for_scanner_matches_similarity(): void {
		$sim = new Similarity();
		$this->assertSame(
			$sim->hamming_distance( '0000000000000000', '00000000000000ff' ),
			$this->finder->hamming_distance_for_scanner( '0000000000000000', '00000000000000ff' )
		);
	}

	/**
	 * The scanner-exposed pHash threshold must match the value used by find().
	 *
	 * @return void
	 */
	public function test_scanner_phash_threshold_is_constant(): void {
		$threshold = Duplicate_Finder::scanner_phash_threshold();
		$this->assertGreaterThan( 0, $threshold );
		$this->assertLessThanOrEqual( 64, $threshold );
	}
}
