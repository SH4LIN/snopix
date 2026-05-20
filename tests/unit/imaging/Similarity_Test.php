<?php
/**
 * Tests for Similarity metrics.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Imaging\Similarity;

/**
 * Similarity unit tests — pure math, no images needed.
 */
class Pixel_Scout_Similarity_Test extends Pixel_Scout_TestCase {

	private Similarity $sim;

	/**
	 * Build a fresh Similarity instance before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->sim = new Similarity();
	}

	// ── Hamming distance ──────────────────────────────────────────────────

	/**
	 * Two identical hex strings must have Hamming distance 0.
	 *
	 * @return void
	 */
	public function test_hamming_identical_strings_returns_zero(): void {
		$this->assertSame( 0, $this->sim->hamming_distance( 'abcdef1234567890', 'abcdef1234567890' ) );
	}

	/**
	 * All-zero vs all-one 64-bit hashes must give the maximum distance of 64.
	 *
	 * @return void
	 */
	public function test_hamming_all_bits_flipped_returns_64(): void {
		$this->assertSame( 64, $this->sim->hamming_distance( '0000000000000000', 'ffffffffffffffff' ) );
	}

	/**
	 * 0x00 vs 0x01 differs by exactly one bit — distance must be 1.
	 *
	 * @return void
	 */
	public function test_hamming_single_bit_difference(): void {
		// 0x00 vs 0x01 → 1 bit different.
		$this->assertSame( 1, $this->sim->hamming_distance( '00000000000000' . '00', '00000000000000' . '01' ) );
	}

	/**
	 * Inputs of mismatched length must return the maximum distance 64 rather
	 * than partially comparing.
	 *
	 * @return void
	 */
	public function test_hamming_length_mismatch_returns_64(): void {
		$this->assertSame( 64, $this->sim->hamming_distance( 'abc', 'abcd' ) );
	}

	/**
	 * Distance must always sit in [0, 64] regardless of inputs.
	 *
	 * @return void
	 */
	public function test_hamming_result_within_bounds(): void {
		$d = $this->sim->hamming_distance( 'deadbeef12345678', 'cafebabe87654321' );
		$this->assertGreaterThanOrEqual( 0, $d );
		$this->assertLessThanOrEqual( 64, $d );
	}

	/**
	 * Hamming distance must be symmetric — distance(a, b) == distance(b, a).
	 *
	 * @return void
	 */
	public function test_hamming_symmetric(): void {
		$h1 = 'deadbeef12345678';
		$h2 = 'cafebabe87654321';
		$this->assertSame(
			$this->sim->hamming_distance( $h1, $h2 ),
			$this->sim->hamming_distance( $h2, $h1 )
		);
	}

	// ── Cosine similarity ─────────────────────────────────────────────────

	/**
	 * Two identical vectors must have cosine similarity 1.0.
	 *
	 * @return void
	 */
	public function test_cosine_identical_vectors_returns_one(): void {
		$v = array_fill( 0, 32, 0.5 );
		$this->assertEqualsWithDelta( 1.0, $this->sim->cosine_similarity( $v, $v ), 1e-9 );
	}

	/**
	 * Two orthogonal unit vectors must have cosine similarity 0.
	 *
	 * @return void
	 */
	public function test_cosine_orthogonal_vectors_returns_zero(): void {
		$a = [ 1.0, 0.0 ];
		$b = [ 0.0, 1.0 ];
		$this->assertEqualsWithDelta( 0.0, $this->sim->cosine_similarity( $a, $b ), 1e-9 );
	}

	/**
	 * Comparing against a zero vector must return 0 (no direction defined).
	 *
	 * @return void
	 */
	public function test_cosine_zero_vector_returns_zero(): void {
		$zero = array_fill( 0, 10, 0.0 );
		$v    = array_fill( 0, 10, 1.0 );
		$this->assertEqualsWithDelta( 0.0, $this->sim->cosine_similarity( $zero, $v ), 1e-9 );
	}

	/**
	 * Cosine similarity must clamp into the [0, 1] range used by the scorer.
	 *
	 * @return void
	 */
	public function test_cosine_result_clamped_to_0_1(): void {
		$a = [ 1.0, 2.0, 3.0 ];
		$b = [ 0.1, 0.5, 0.9 ];
		$score = $this->sim->cosine_similarity( $a, $b );
		$this->assertGreaterThanOrEqual( 0.0, $score );
		$this->assertLessThanOrEqual( 1.0, $score );
	}

	// ── Bhattacharyya similarity ──────────────────────────────────────────

	/**
	 * Two identical normalised histograms (per channel summing to 1) must give
	 * Bhattacharyya coefficient 1.0.
	 *
	 * @return void
	 */
	public function test_bhattacharyya_identical_normalized_histogram_returns_one(): void {
		// 3 channels × 16 bins each, each channel sums to 1.0.
		$v = array_merge(
			array_fill( 0, 16, 1.0 / 16.0 ),
			array_fill( 0, 16, 1.0 / 16.0 ),
			array_fill( 0, 16, 1.0 / 16.0 )
		);
		$this->assertEqualsWithDelta( 1.0, $this->sim->bhattacharyya_similarity( $v, $v, 3 ), 1e-6 );
	}

	/**
	 * Non-overlapping (orthogonal) histograms must give Bhattacharyya 0.
	 *
	 * @return void
	 */
	public function test_bhattacharyya_orthogonal_returns_zero(): void {
		// Non-overlapping histograms → coefficient = 0.
		$a = [ 1.0, 0.0, 0.0, 0.0 ];
		$b = [ 0.0, 1.0, 0.0, 0.0 ];
		$this->assertEqualsWithDelta( 0.0, $this->sim->bhattacharyya_similarity( $a, $b, 1 ), 1e-9 );
	}

	/**
	 * Bhattacharyya result must stay in [0, 1] for arbitrary normalised inputs.
	 *
	 * @return void
	 */
	public function test_bhattacharyya_result_in_0_1(): void {
		$a = [ 0.6, 0.2, 0.1, 0.1 ];
		$b = [ 0.1, 0.5, 0.3, 0.1 ];
		$score = $this->sim->bhattacharyya_similarity( $a, $b, 1 );
		$this->assertGreaterThanOrEqual( 0.0, $score );
		$this->assertLessThanOrEqual( 1.0, $score );
	}

	/**
	 * A zero or negative channel count is invalid — function must return 0.
	 *
	 * @return void
	 */
	public function test_bhattacharyya_negative_channels_returns_zero(): void {
		$v = [ 0.5, 0.5 ];
		$this->assertSame( 0.0, $this->sim->bhattacharyya_similarity( $v, $v, 0 ) );
	}

	/**
	 * Empty histogram vectors must safely return 0 instead of erroring.
	 *
	 * @return void
	 */
	public function test_bhattacharyya_empty_vectors_returns_zero(): void {
		$this->assertSame( 0.0, $this->sim->bhattacharyya_similarity( [], [], 1 ) );
	}

	/**
	 * Bhattacharyya must be symmetric: similarity(a, b) == similarity(b, a).
	 *
	 * @return void
	 */
	public function test_bhattacharyya_symmetric(): void {
		$a = [ 0.3, 0.4, 0.2, 0.1 ];
		$b = [ 0.1, 0.2, 0.5, 0.2 ];
		$this->assertEqualsWithDelta(
			$this->sim->bhattacharyya_similarity( $a, $b, 1 ),
			$this->sim->bhattacharyya_similarity( $b, $a, 1 ),
			1e-9
		);
	}
}
