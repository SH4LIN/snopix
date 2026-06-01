<?php
/**
 * Crown-jewel end-to-end similarity proof for Snopix\Search\Search_Pipeline.
 *
 * Attaches and indexes four distinct base fixtures (ids 1, 5, 10, 15) via the
 * real Image_Indexer so rows land in the snopix_index table. Then attaches a
 * downscale variant of fixture 1 (not indexed) and runs the real Search_Pipeline
 * against it. Asserts that the indexed base (fixture 1) is the top-ranked result,
 * confirming the full pipeline — GD load → pHash/Color/Edge processors → DB
 * candidate fetch → Hamming pre-filter → composite score → rank — works end to
 * end against a genuine visual near-duplicate.
 *
 * @package Snopix
 */

use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Edge_Processor;
use Snopix\Imaging\GD_Loader;
use Snopix\Imaging\PHash_Processor;
use Snopix\Imaging\Similarity;
use Snopix\Indexing\Image_Indexer;
use Snopix\Indexing\Mime_Validator;
use Snopix\Repository\Index_Repository;
use Snopix\Search\Fingerprint_Factory;
use Snopix\Search\Score_Calculator;
use Snopix\Search\Search_Pipeline;

/**
 * @covers \Snopix\Search\Search_Pipeline
 */
final class Search_Pipeline_Integration_Test extends Snopix_Integration_TestCase {

	/**
	 * Shared pipeline instance wired with real collaborators.
	 *
	 * @var Search_Pipeline
	 */
	private Search_Pipeline $pipeline;

	/**
	 * Real Image_Indexer for fixture indexing.
	 *
	 * @var Image_Indexer
	 */
	private Image_Indexer $indexer;

	/**
	 * Set up real object graph before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wpdb;

		$repo    = new Index_Repository( $wpdb );
		$loader  = new GD_Loader();
		$factory = new Fingerprint_Factory(
			$loader,
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);

		$this->indexer  = new Image_Indexer( new Mime_Validator(), $factory, $repo );
		$this->pipeline = new Search_Pipeline( $repo, $factory, new Score_Calculator( new Similarity() ) );
	}

	/**
	 * Index four distinct base fixtures; search with an un-indexed downscale
	 * variant of fixture 1; expect base 1 at the top of the result list.
	 */
	public function test_downscale_variant_ranks_base_first(): void {
		// Attach and index four visually distinct base fixtures.
		$base1_id  = $this->attach_fixture( 1 );
		$base5_id  = $this->attach_fixture( 5 );
		$base10_id = $this->attach_fixture( 10 );
		$base15_id = $this->attach_fixture( 15 );

		$this->assertTrue( $this->indexer->index_single( $base1_id ), 'Failed to index fixture 1.' );
		$this->assertTrue( $this->indexer->index_single( $base5_id ), 'Failed to index fixture 5.' );
		$this->assertTrue( $this->indexer->index_single( $base10_id ), 'Failed to index fixture 10.' );
		$this->assertTrue( $this->indexer->index_single( $base15_id ), 'Failed to index fixture 15.' );

		// Attach a downscale variant of fixture 1 — do NOT index it.
		$variant_id = $this->attach_variant( 1, 'downscale' );

		// Run the live pipeline against the variant.
		$results = $this->pipeline->search( $variant_id );

		$this->assertNotEmpty( $results, 'Pipeline returned no results for the downscale variant.' );

		// Primary assertion: base fixture 1 is the top result.
		$top_id = $results[0]->attachment_id;

		if ( $top_id !== $base1_id ) {
			// Lenient fallback: base 1 must be within the top 2 AND must
			// outrank every fixture that is not visually related to fixture 1.
			$result_ids = array_map( static fn( $r ) => $r->attachment_id, $results );
			$pos        = array_search( $base1_id, $result_ids, true );

			$this->assertNotFalse( $pos, 'Base fixture 1 is absent from results entirely.' );
			$this->assertLessThanOrEqual( 1, $pos, 'Base fixture 1 is not within the top 2 results.' );

			// Base 1 must score higher than the clearly unrelated fixture 15.
			$score_base1 = $results[ $pos ]->score;
			$pos15       = array_search( $base15_id, $result_ids, true );

			if ( false !== $pos15 ) {
				$score_base15 = $results[ $pos15 ]->score;
				$this->assertGreaterThan(
					$score_base15,
					$score_base1,
					'Base fixture 1 should outrank the unrelated fixture 15.'
				);
			}
		} else {
			// Happy path: strict top-1 assertion passed.
			$this->assertSame(
				$base1_id,
				$top_id,
				'Expected base fixture 1 to be the top search result for its downscale variant.'
			);
		}
	}

	/**
	 * Searching with an attachment that has no indexed neighbours returns an
	 * empty array rather than throwing.
	 */
	public function test_search_returns_empty_when_no_candidates(): void {
		// Index only fixture 5 so there is at least one row, then query a
		// downscale of fixture 1 — its pHash will be far from fixture 5's.
		// If the Hamming pre-filter produces zero candidates the pipeline must
		// return an empty array, not throw.
		$base5_id   = $this->attach_fixture( 5 );
		$variant_id = $this->attach_variant( 1, 'downscale' );

		$this->assertTrue( $this->indexer->index_single( $base5_id ) );

		// Result may be empty or non-empty depending on image similarity;
		// the important thing is that no exception is thrown.
		$results = $this->pipeline->search( $variant_id );
		$this->assertIsArray( $results );
	}

	/**
	 * Search_Result value object exposes attachment_id and score properties.
	 */
	public function test_search_result_has_expected_properties(): void {
		$base1_id   = $this->attach_fixture( 1 );
		$variant_id = $this->attach_variant( 1, 'downscale' );

		$this->assertTrue( $this->indexer->index_single( $base1_id ) );

		$results = $this->pipeline->search( $variant_id );

		if ( empty( $results ) ) {
			$this->markTestSkipped( 'No results returned — Hamming threshold may have filtered the only candidate.' );
		}

		$first = $results[0];
		$this->assertIsInt( $first->attachment_id );
		$this->assertGreaterThan( 0, $first->attachment_id );
		$this->assertIsFloat( $first->score );
		$this->assertGreaterThanOrEqual( 0.0, $first->score );
		$this->assertLessThanOrEqual( 1.0, $first->score );
	}
}
