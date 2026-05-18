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

	public function setUp(): void {
		parent::setUp();
		$this->sim = new Similarity();
	}

	// ── Hamming distance ──────────────────────────────────────────────────

	public function test_hamming_identical_strings_returns_zero(): void {
		$this->assertSame( 0, $this->sim->hamming_distance( 'abcdef1234567890', 'abcdef1234567890' ) );
	}

	public function test_hamming_all_bits_flipped_returns_64(): void {
		$this->assertSame( 64, $this->sim->hamming_distance( '0000000000000000', 'ffffffffffffffff' ) );
	}

	public function test_hamming_single_bit_difference(): void {
		// 0x00 vs 0x01 → 1 bit different.
		$this->assertSame( 1, $this->sim->hamming_distance( '00000000000000' . '00', '00000000000000' . '01' ) );
	}

	public function test_hamming_length_mismatch_returns_64(): void {
		$this->assertSame( 64, $this->sim->hamming_distance( 'abc', 'abcd' ) );
	}

	public function test_hamming_result_within_bounds(): void {
		$d = $this->sim->hamming_distance( 'deadbeef12345678', 'cafebabe87654321' );
		$this->assertGreaterThanOrEqual( 0, $d );
		$this->assertLessThanOrEqual( 64, $d );
	}

	public function test_hamming_symmetric(): void {
		$h1 = 'deadbeef12345678';
		$h2 = 'cafebabe87654321';
		$this->assertSame(
			$this->sim->hamming_distance( $h1, $h2 ),
			$this->sim->hamming_distance( $h2, $h1 )
		);
	}

	// ── Cosine similarity ─────────────────────────────────────────────────

	public function test_cosine_identical_vectors_returns_one(): void {
		$v = array_fill( 0, 32, 0.5 );
		$this->assertEqualsWithDelta( 1.0, $this->sim->cosine_similarity( $v, $v ), 1e-9 );
	}

	public function test_cosine_orthogonal_vectors_returns_zero(): void {
		$a = [ 1.0, 0.0 ];
		$b = [ 0.0, 1.0 ];
		$this->assertEqualsWithDelta( 0.0, $this->sim->cosine_similarity( $a, $b ), 1e-9 );
	}

	public function test_cosine_zero_vector_returns_zero(): void {
		$zero = array_fill( 0, 10, 0.0 );
		$v    = array_fill( 0, 10, 1.0 );
		$this->assertEqualsWithDelta( 0.0, $this->sim->cosine_similarity( $zero, $v ), 1e-9 );
	}

	public function test_cosine_result_clamped_to_0_1(): void {
		$a = [ 1.0, 2.0, 3.0 ];
		$b = [ 0.1, 0.5, 0.9 ];
		$score = $this->sim->cosine_similarity( $a, $b );
		$this->assertGreaterThanOrEqual( 0.0, $score );
		$this->assertLessThanOrEqual( 1.0, $score );
	}

	// ── Bhattacharyya similarity ──────────────────────────────────────────

	public function test_bhattacharyya_identical_normalized_histogram_returns_one(): void {
		// 3 channels × 16 bins each, each channel sums to 1.0.
		$v = array_merge(
			array_fill( 0, 16, 1.0 / 16.0 ),
			array_fill( 0, 16, 1.0 / 16.0 ),
			array_fill( 0, 16, 1.0 / 16.0 )
		);
		$this->assertEqualsWithDelta( 1.0, $this->sim->bhattacharyya_similarity( $v, $v, 3 ), 1e-6 );
	}

	public function test_bhattacharyya_orthogonal_returns_zero(): void {
		// Non-overlapping histograms → coefficient = 0.
		$a = [ 1.0, 0.0, 0.0, 0.0 ];
		$b = [ 0.0, 1.0, 0.0, 0.0 ];
		$this->assertEqualsWithDelta( 0.0, $this->sim->bhattacharyya_similarity( $a, $b, 1 ), 1e-9 );
	}

	public function test_bhattacharyya_result_in_0_1(): void {
		$a = [ 0.6, 0.2, 0.1, 0.1 ];
		$b = [ 0.1, 0.5, 0.3, 0.1 ];
		$score = $this->sim->bhattacharyya_similarity( $a, $b, 1 );
		$this->assertGreaterThanOrEqual( 0.0, $score );
		$this->assertLessThanOrEqual( 1.0, $score );
	}

	public function test_bhattacharyya_negative_channels_returns_zero(): void {
		$v = [ 0.5, 0.5 ];
		$this->assertSame( 0.0, $this->sim->bhattacharyya_similarity( $v, $v, 0 ) );
	}

	public function test_bhattacharyya_empty_vectors_returns_zero(): void {
		$this->assertSame( 0.0, $this->sim->bhattacharyya_similarity( [], [], 1 ) );
	}

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
