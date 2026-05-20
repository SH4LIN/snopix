<?php
/**
 * Tests for Score_Calculator composite scoring.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Imaging\Similarity;
use PixelScout\Search\Score_Calculator;

/**
 * Score_Calculator unit tests — pure math, no images needed.
 */
class Pixel_Scout_Score_Calculator_Test extends Pixel_Scout_TestCase {

	private Score_Calculator $calculator;

	/**
	 * Build a fresh Score_Calculator backed by a real Similarity instance.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->calculator = new Score_Calculator( new Similarity() );
	}

	/**
	 * Build a fully populated fingerprint row in the same shape the repository
	 * returns — JSON-encoded colour and edge vectors plus a literal pHash.
	 *
	 * @param string $phash 16-char lower-case hex hash. Defaults to a fixed value.
	 *
	 * @return array<string, mixed>
	 */
	private function make_fingerprint( string $phash = 'abcdef1234567890' ): array {
		return array(
			'phash'        => $phash,
			'color_vector' => wp_json_encode( array_fill( 0, 48, 1.0 / 16.0 ) ),
			'edge_vector'  => wp_json_encode( array_fill( 0, 32, 0.5 ) ),
		);
	}

	/**
	 * Two identical fingerprints must produce the maximum composite score 1.0.
	 *
	 * @return void
	 */
	public function test_identical_fingerprints_return_one(): void {
		$fp = $this->make_fingerprint();
		$this->assertEqualsWithDelta( 1.0, $this->calculator->calculate( $fp, $fp ), 1e-6 );
	}

	/**
	 * A row missing the `phash` field must score 0.0 (incomplete fingerprint).
	 *
	 * @return void
	 */
	public function test_missing_phash_returns_zero(): void {
		$query  = $this->make_fingerprint();
		$stored = $this->make_fingerprint();
		unset( $stored['phash'] );
		$this->assertSame( 0.0, $this->calculator->calculate( $query, $stored ) );
	}

	/**
	 * A row missing the `color_vector` field must score 0.0.
	 *
	 * @return void
	 */
	public function test_missing_color_vector_returns_zero(): void {
		$query  = $this->make_fingerprint();
		$stored = $this->make_fingerprint();
		unset( $stored['color_vector'] );
		$this->assertSame( 0.0, $this->calculator->calculate( $query, $stored ) );
	}

	/**
	 * A row missing the `edge_vector` field must score 0.0.
	 *
	 * @return void
	 */
	public function test_missing_edge_vector_returns_zero(): void {
		$query  = $this->make_fingerprint();
		$stored = $this->make_fingerprint();
		unset( $stored['edge_vector'] );
		$this->assertSame( 0.0, $this->calculator->calculate( $query, $stored ) );
	}

	/**
	 * Composite score must always sit within [0, 1] regardless of pHash diff.
	 *
	 * @return void
	 */
	public function test_score_is_in_range_0_to_1(): void {
		$query  = $this->make_fingerprint( 'abcdef1234567890' );
		$stored = $this->make_fingerprint( 'fedcba0987654321' );
		$score  = $this->calculator->calculate( $query, $stored );
		$this->assertGreaterThanOrEqual( 0.0, $score );
		$this->assertLessThanOrEqual( 1.0, $score );
	}

	/**
	 * Flipping every pHash bit must lower the composite score compared to an
	 * identical-hash baseline — the pHash weight is non-zero.
	 *
	 * @return void
	 */
	public function test_completely_different_phash_lowers_score(): void {
		$identical  = $this->calculator->calculate( $this->make_fingerprint( '0000000000000000' ), $this->make_fingerprint( '0000000000000000' ) );
		$different  = $this->calculator->calculate( $this->make_fingerprint( '0000000000000000' ), $this->make_fingerprint( 'ffffffffffffffff' ) );
		$this->assertGreaterThan( $different, $identical );
	}

	/**
	 * Score_Calculator must accept already-decoded array vectors, not only
	 * JSON-encoded strings — the pipeline pre-decodes in some code paths.
	 *
	 * @return void
	 */
	public function test_accepts_pre_decoded_vectors(): void {
		$fp = array(
			'phash'        => 'abcdef1234567890',
			'color_vector' => array_fill( 0, 48, 1.0 / 16.0 ),
			'edge_vector'  => array_fill( 0, 32, 0.5 ),
		);
		$score = $this->calculator->calculate( $fp, $fp );
		$this->assertEqualsWithDelta( 1.0, $score, 1e-6 );
	}
}
