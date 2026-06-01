<?php
/**
 * Unit tests for Snopix\Imaging\Similarity.
 *
 * @package Snopix
 */

use Snopix\Imaging\Similarity;

/**
 * @covers \Snopix\Imaging\Similarity
 */
final class Similarity_Test extends Snopix_Unit_TestCase {

	private Similarity $similarity;

	protected function setUp(): void {
		parent::setUp();
		$this->similarity = new Similarity();
	}

	public function test_hamming_distance_identical_hashes_is_zero(): void {
		$this->assertSame( 0, $this->similarity->hamming_distance( 'ffffffffffffffff', 'ffffffffffffffff' ) );
		$this->assertSame( 0, $this->similarity->hamming_distance( '0000000000000000', '0000000000000000' ) );
	}

	public function test_hamming_distance_all_bits_differ_is_64(): void {
		$this->assertSame( 64, $this->similarity->hamming_distance( '0000000000000000', 'ffffffffffffffff' ) );
	}

	public function test_hamming_distance_counts_single_differing_bit(): void {
		// 0x0 = 0000, 0x1 = 0001 → one differing bit.
		$this->assertSame( 1, $this->similarity->hamming_distance( '0000000000000000', '0000000000000001' ) );
		// 0x0 vs 0xf in last nibble = 4 differing bits.
		$this->assertSame( 4, $this->similarity->hamming_distance( '0000000000000000', '000000000000000f' ) );
	}

	public function test_hamming_distance_returns_64_on_length_mismatch(): void {
		$this->assertSame( 64, $this->similarity->hamming_distance( 'ffff', 'ffffffffffffffff' ) );
		$this->assertSame( 64, $this->similarity->hamming_distance( '', 'f' ) );
	}

	public function test_cosine_similarity_identical_vectors_is_one(): void {
		$v = array( 1.0, 2.0, 3.0, 4.0 );
		$this->assertEqualsWithDelta( 1.0, $this->similarity->cosine_similarity( $v, $v ), 1e-9 );
	}

	public function test_cosine_similarity_orthogonal_vectors_is_zero(): void {
		$this->assertEqualsWithDelta( 0.0, $this->similarity->cosine_similarity( array( 1.0, 0.0 ), array( 0.0, 1.0 ) ), 1e-9 );
	}

	public function test_cosine_similarity_opposite_vectors_clamps_to_zero(): void {
		// Raw cosine would be -1.0; implementation clamps to [0,1].
		$this->assertSame( 0.0, $this->similarity->cosine_similarity( array( 1.0, 1.0 ), array( -1.0, -1.0 ) ) );
	}

	public function test_cosine_similarity_zero_magnitude_returns_zero(): void {
		$this->assertSame( 0.0, $this->similarity->cosine_similarity( array( 0.0, 0.0 ), array( 1.0, 2.0 ) ) );
		$this->assertSame( 0.0, $this->similarity->cosine_similarity( array( 1.0, 2.0 ), array( 0.0, 0.0 ) ) );
	}

	public function test_cosine_similarity_uses_shorter_vector_length(): void {
		// Extra trailing element on $b is ignored (min count = 2).
		$this->assertEqualsWithDelta(
			1.0,
			$this->similarity->cosine_similarity( array( 1.0, 1.0 ), array( 1.0, 1.0, 99.0 ) ),
			1e-9
		);
	}

	public function test_bhattacharyya_identical_distributions_is_one(): void {
		$hist = array( 0.25, 0.25, 0.25, 0.25 );
		$this->assertEqualsWithDelta( 1.0, $this->similarity->bhattacharyya_similarity( $hist, $hist, 1 ), 1e-9 );
	}

	public function test_bhattacharyya_disjoint_distributions_is_zero(): void {
		$a = array( 1.0, 0.0, 0.0, 0.0 );
		$b = array( 0.0, 0.0, 0.0, 1.0 );
		$this->assertEqualsWithDelta( 0.0, $this->similarity->bhattacharyya_similarity( $a, $b, 1 ), 1e-9 );
	}

	public function test_bhattacharyya_averages_across_channels(): void {
		// Two identical channels, each summing to 1.0 → coefficient 2.0 / 2 channels = 1.0.
		$a = array( 0.5, 0.5, 0.5, 0.5 );
		$b = array( 0.5, 0.5, 0.5, 0.5 );
		$this->assertEqualsWithDelta( 1.0, $this->similarity->bhattacharyya_similarity( $a, $b, 2 ), 1e-9 );
	}

	public function test_bhattacharyya_guards_empty_and_bad_channels(): void {
		$this->assertSame( 0.0, $this->similarity->bhattacharyya_similarity( array(), array(), 1 ) );
		$this->assertSame( 0.0, $this->similarity->bhattacharyya_similarity( array( 0.5, 0.5 ), array( 0.5, 0.5 ), 0 ) );
	}
}
