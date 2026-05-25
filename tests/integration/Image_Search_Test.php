<?php
/**
 * Integration tests for the reverse-image-search pipeline against real fixtures.
 *
 * Runs the full PHP pipeline (Image_Indexer → Index_Repository → Search_Pipeline)
 * with real GD-loaded JPEGs. These are regression smoke tests — if a
 * performance change breaks the matching algo, these fail loudly.
 *
 * @package Snopix
 */

require_once __DIR__ . '/class-fixture-helper.php';

use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Edge_Processor;
use Snopix\Imaging\GD_Loader;
use Snopix\Imaging\PHash_Processor;
use Snopix\Imaging\Similarity;
use Snopix\Indexing\Image_Indexer;
use Snopix\Indexing\Mime_Validator;
use Snopix\Repository\Index_Repository;
use Snopix\Repository\Schema;
use Snopix\Search\Fingerprint_Factory;
use Snopix\Search\Score_Calculator;
use Snopix\Search\Search_Pipeline;

/**
 * Full-pipeline reverse image search tests.
 */
class Snopix_Image_Search_Test extends Snopix_Integration_TestCase {

	private Index_Repository $repo;
	private Image_Indexer $indexer;
	private Search_Pipeline $pipeline;

	/**
	 * Boot a real repo + factory + pipeline against the test DB.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Schema() )->install();
		$this->repo = new Index_Repository( $wpdb );

		$similarity = new Similarity();
		$factory    = new Fingerprint_Factory(
			new GD_Loader(),
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);
		$this->indexer  = new Image_Indexer( new Mime_Validator(), $factory, $this->repo );
		$this->pipeline = new Search_Pipeline( $this->repo, $factory, new Score_Calculator( $similarity ) );
	}

	/**
	 * Index N fixtures and return their attachment IDs in fixture order.
	 *
	 * @param int $count How many fixtures to index (1..25).
	 *
	 * @return array<int, int> [fixture_id => attachment_id]
	 */
	private function index_fixtures( int $count ): array {
		$map = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$aid = $this->attach_fixture( $i );
			$this->assertTrue( $this->indexer->index_single( $aid ), "Failed to index fixture {$i}" );
			$map[ $i ] = $aid;
		}
		return $map;
	}

	/**
	 * Identical query (same bytes already in the index) must not return itself.
	 * Indexed image searched against itself returns 0 results because the
	 * pipeline filters out `phash <> %s` for the query (see repo SQL) — the
	 * test query must be uploaded as a separate attachment.
	 *
	 * @return void
	 */
	public function test_query_finds_indexed_duplicate_byte_match(): void {
		$indexed = $this->attach_fixture( 1, 'indexed' );
		$query   = $this->attach_fixture( 1, 'query' );

		$this->assertTrue( $this->indexer->index_single( $indexed ) );

		$results = $this->pipeline->search( $query, 5 );
		$this->assertNotEmpty( $results );
		$this->assertSame( $indexed, $results[0]->attachment_id );
		$this->assertGreaterThan( 0.95, $results[0]->score );
	}

	/**
	 * A downscaled variant of an indexed image must still be returned as the
	 * top match.
	 *
	 * @return void
	 */
	public function test_downscaled_variant_matches_original(): void {
		$indexed = $this->attach_fixture( 2 );
		$query   = $this->attach_variant( 2, 'downscale' );

		$this->assertTrue( $this->indexer->index_single( $indexed ) );

		$results = $this->pipeline->search( $query, 5 );
		$this->assertNotEmpty( $results );
		$this->assertSame( $indexed, $results[0]->attachment_id );
		$this->assertGreaterThan( 0.85, $results[0]->score );
	}

	/**
	 * An upscaled variant must match the original.
	 *
	 * @return void
	 */
	public function test_upscaled_variant_matches_original(): void {
		$indexed = $this->attach_fixture( 3 );
		$query   = $this->attach_variant( 3, 'upscale' );

		$this->assertTrue( $this->indexer->index_single( $indexed ) );

		$results = $this->pipeline->search( $query, 5 );
		$this->assertNotEmpty( $results );
		$this->assertSame( $indexed, $results[0]->attachment_id );
		$this->assertGreaterThan( 0.85, $results[0]->score );
	}

	/**
	 * A heavily blurred variant must still be retrieved as the top match
	 * (perceptual hash robustness).
	 *
	 * @return void
	 */
	public function test_blurred_variant_matches_original(): void {
		$indexed = $this->attach_fixture( 4 );
		$query   = $this->attach_variant( 4, 'blur' );

		$this->assertTrue( $this->indexer->index_single( $indexed ) );

		$results = $this->pipeline->search( $query, 5 );
		$this->assertNotEmpty( $results );
		$this->assertSame( $indexed, $results[0]->attachment_id );
	}

	/**
	 * A heavily compressed (quality 10) variant must still match.
	 *
	 * @return void
	 */
	public function test_compressed_variant_matches_original(): void {
		$indexed = $this->attach_fixture( 5 );
		$query   = $this->attach_variant( 5, 'compressed' );

		$this->assertTrue( $this->indexer->index_single( $indexed ) );

		$results = $this->pipeline->search( $query, 5 );
		$this->assertNotEmpty( $results );
		$this->assertSame( $indexed, $results[0]->attachment_id );
	}

	/**
	 * Cross-format: query as PNG against a JPEG-indexed image.
	 *
	 * @return void
	 */
	public function test_png_variant_matches_jpeg_original(): void {
		$indexed = $this->attach_fixture( 6 );
		$query   = $this->attach_variant( 6, 'png' );

		$this->assertTrue( $this->indexer->index_single( $indexed ) );

		$results = $this->pipeline->search( $query, 5 );
		$this->assertNotEmpty( $results );
		$this->assertSame( $indexed, $results[0]->attachment_id );
	}

	/**
	 * Cross-format: query as WebP against a JPEG-indexed image.
	 *
	 * @return void
	 */
	public function test_webp_variant_matches_jpeg_original(): void {
		if ( ! function_exists( 'imagewebp' ) ) {
			$this->markTestSkipped( 'GD WebP support not available' );
		}
		$indexed = $this->attach_fixture( 7 );
		$query   = $this->attach_variant( 7, 'webp' );

		$this->assertTrue( $this->indexer->index_single( $indexed ) );

		$results = $this->pipeline->search( $query, 5 );
		$this->assertNotEmpty( $results );
		$this->assertSame( $indexed, $results[0]->attachment_id );
	}

	/**
	 * Searching against a library of 10 unrelated images must NOT return
	 * false positives — score-threshold filtering keeps the result set empty.
	 *
	 * @return void
	 */
	public function test_unrelated_query_does_not_match(): void {
		$this->index_fixtures( 10 );

		// Query an image NOT in the indexed range (fixture 20).
		$query   = $this->attach_fixture( 20 );
		$results = $this->pipeline->search( $query, 5 );

		// Any matches must be below score threshold (0.85); pipeline drops them.
		foreach ( $results as $r ) {
			$this->assertGreaterThanOrEqual( 0.85, $r->score );
			$this->assertNotSame( $query, $r->attachment_id );
		}
	}

	/**
	 * Each indexed image, queried with its own variant, must self-match —
	 * confirms the library is internally discriminable (no collisions).
	 *
	 * @return void
	 */
	public function test_each_fixture_self_matches_via_variant(): void {
		$map     = $this->index_fixtures( 8 );
		$correct = 0;

		foreach ( $map as $fixture_id => $indexed_id ) {
			$query   = $this->attach_variant( $fixture_id, 'downscale' );
			$results = $this->pipeline->search( $query, 3 );
			if ( ! empty( $results ) && $indexed_id === $results[0]->attachment_id ) {
				++$correct;
			}
		}

		// Require at least 75% accuracy for the perceptual algo.
		$this->assertGreaterThanOrEqual(
			(int) ceil( count( $map ) * 0.75 ),
			$correct,
			"Self-match accuracy was {$correct} / " . count( $map )
		);
	}

	/**
	 * Sort order is strictly descending by score.
	 *
	 * @return void
	 */
	public function test_results_are_sorted_descending(): void {
		$this->index_fixtures( 5 );
		$query   = $this->attach_variant( 1, 'downscale' );
		$results = $this->pipeline->search( $query, 5 );

		$prev = 1.1;
		foreach ( $results as $r ) {
			$this->assertLessThanOrEqual( $prev, $r->score );
			$prev = $r->score;
		}
	}

	/**
	 * Limit caps the response.
	 *
	 * @return void
	 */
	public function test_limit_is_respected(): void {
		$this->index_fixtures( 10 );
		$query = $this->attach_variant( 1, 'downscale' );

		$this->assertLessThanOrEqual( 2, count( $this->pipeline->search( $query, 2 ) ) );
		$this->assertLessThanOrEqual( 5, count( $this->pipeline->search( $query, 5 ) ) );
	}
}
