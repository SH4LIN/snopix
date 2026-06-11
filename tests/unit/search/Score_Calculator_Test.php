<?php
/**
 * Unit tests for Snopix\Search\Score_Calculator.
 *
 * @package Snopix
 */

use Snopix\Imaging\Similarity;
use Snopix\Search\Score_Calculator;

/**
 * @covers \Snopix\Search\Score_Calculator
 */
final class Score_Calculator_Test extends Snopix_Unit_TestCase {

	private Score_Calculator $calculator;

	protected function setUp(): void {
		parent::setUp();
		$this->calculator = new Score_Calculator( new Similarity() );
	}

	/**
	 * Build a self-consistent fingerprint that scores 1.0 against itself:
	 * - phash hex (hamming distance 0),
	 * - color_vector: 3 concatenated histograms each summing to 1.0,
	 * - edge_vector: a 64-bin distribution summing to 1.0
	 *   (bhattacharyya → 1.0 for identical input).
	 *
	 * @return array<string, mixed>
	 */
	private function identical_fingerprint(): array {
		return array(
			'phash'        => 'a1b2c3d4e5f60718',
			'color_vector' => $this->color_vector(),
			'edge_vector'  => $this->edge_vector(),
		);
	}

	/**
	 * Build a valid 16-bin hue + 8-bin saturation + 8-bin value vector.
	 *
	 * @param bool $disjoint Whether to use bins disjoint from the base vector.
	 *
	 * @return array<int, float>
	 */
	private function color_vector( bool $disjoint = false ): array {
		if ( $disjoint ) {
			return array_merge(
				array( 0.0, 0.0, 1.0 ),
				array_fill( 0, 13, 0.0 ),
				array( 0.0, 0.0, 1.0 ),
				array_fill( 0, 5, 0.0 ),
				array( 0.0, 0.0, 1.0 ),
				array_fill( 0, 5, 0.0 )
			);
		}

		return array_merge(
			array( 0.6, 0.4 ),
			array_fill( 0, 14, 0.0 ),
			array( 0.7, 0.3 ),
			array_fill( 0, 6, 0.0 ),
			array( 0.8, 0.2 ),
			array_fill( 0, 6, 0.0 )
		);
	}

	/**
	 * Build a valid 64-bin edge-orientation distribution.
	 *
	 * @param bool $disjoint Whether to use bins disjoint from the base vector.
	 *
	 * @return array<int, float>
	 */
	private function edge_vector( bool $disjoint = false ): array {
		if ( $disjoint ) {
			return array_merge(
				array_fill( 0, 4, 0.0 ),
				array( 0.5, 0.5 ),
				array_fill( 0, 58, 0.0 )
			);
		}

		return array_merge(
			array( 0.5, 0.25, 0.25 ),
			array_fill( 0, 61, 0.0 )
		);
	}

	/**
	 * @dataProvider provide_missing_key_cases
	 *
	 * @param string $missing_key Required key to drop.
	 * @param string $side        Which fingerprint to drop it from ('query' or 'stored').
	 */
	public function test_calculate_returns_zero_when_required_key_missing( string $missing_key, string $side ): void {
		$query  = $this->identical_fingerprint();
		$stored = $this->identical_fingerprint();

		if ( 'query' === $side ) {
			unset( $query[ $missing_key ] );
		} else {
			unset( $stored[ $missing_key ] );
		}

		$this->assertSame( 0.0, $this->calculator->calculate( $query, $stored ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function provide_missing_key_cases(): array {
		return array(
			'phash missing from query'         => array( 'phash', 'query' ),
			'phash missing from stored'        => array( 'phash', 'stored' ),
			'color_vector missing from query'  => array( 'color_vector', 'query' ),
			'color_vector missing from stored' => array( 'color_vector', 'stored' ),
			'edge_vector missing from query'   => array( 'edge_vector', 'query' ),
			'edge_vector missing from stored'  => array( 'edge_vector', 'stored' ),
		);
	}

	public function test_calculate_identical_fingerprints_scores_one(): void {
		$fp = $this->identical_fingerprint();

		// phash 1.0*0.40 + color 1.0*0.35 + edge 1.0*0.25 = 1.0.
		$this->assertEqualsWithDelta( 1.0, $this->calculator->calculate( $fp, $fp ), 1e-9 );
	}

	public function test_calculate_json_string_vectors_match_array_vectors(): void {
		$array_fp = $this->identical_fingerprint();

		$json_fp                 = $array_fp;
		$json_fp['color_vector'] = json_encode( $array_fp['color_vector'] );
		$json_fp['edge_vector']  = json_encode( $array_fp['edge_vector'] );

		$from_arrays = $this->calculator->calculate( $array_fp, $array_fp );
		$from_json   = $this->calculator->calculate( $json_fp, $json_fp );
		$mixed       = $this->calculator->calculate( $array_fp, $json_fp );

		$this->assertSame( $from_arrays, $from_json );
		$this->assertSame( $from_arrays, $mixed );
	}

	public function test_calculate_dissimilar_pair_scores_below_identical(): void {
		$query = $this->identical_fingerprint();

		$stored = array(
			// Inverted bits → large hamming distance → low phash score.
			'phash'        => '5e4d3c2b1a09f8e7',
			// Histograms disjoint from the query's per-channel bins → low bhattacharyya.
			'color_vector' => $this->color_vector( true ),
			// Distribution disjoint from the query's bins → bhattacharyya 0.
			'edge_vector'  => $this->edge_vector( true ),
		);

		$identical_score   = $this->calculator->calculate( $query, $query );
		$dissimilar_score  = $this->calculator->calculate( $query, $stored );

		$this->assertLessThan( $identical_score, $dissimilar_score );
		// Score must stay within the documented [0.0, 1.0] range.
		$this->assertGreaterThanOrEqual( 0.0, $dissimilar_score );
		$this->assertLessThanOrEqual( 1.0, $dissimilar_score );
	}

	public function test_calculate_is_deterministic(): void {
		$query  = $this->identical_fingerprint();
		$stored = array(
			'phash'        => 'ffffffff00000000',
			'color_vector' => $this->color_vector(),
			'edge_vector'  => $this->edge_vector( true ),
		);

		$first  = $this->calculator->calculate( $query, $stored );
		$second = $this->calculator->calculate( $query, $stored );

		$this->assertSame( $first, $second );
	}

	public function test_max_passing_hamming_matches_score_algebra(): void {
		// 0.40·(1 - d/64) + 0.35 + 0.25 >= t  →  d <= 64·(1 - (t - 0.60)/0.40).
		$this->assertSame( 24, Score_Calculator::max_passing_hamming( 0.85 ) );
		$this->assertSame( 0, Score_Calculator::max_passing_hamming( 1.0 ) );
		// At t <= 0.60 colour + edge alone can clear the gate → no pre-filter.
		$this->assertSame( 64, Score_Calculator::max_passing_hamming( 0.5 ) );
		$this->assertSame( 64, Score_Calculator::max_passing_hamming( 0.0 ) );
	}

	public function test_max_passing_hamming_bound_is_tight(): void {
		// d = 24 at t = 0.85 reaches the gate exactly with perfect colour/edge;
		// d = 25 cannot.
		$at_bound   = ( 0.40 * ( 1.0 - 24.0 / 64.0 ) ) + 0.35 + 0.25;
		$past_bound = ( 0.40 * ( 1.0 - 25.0 / 64.0 ) ) + 0.35 + 0.25;
		$this->assertGreaterThanOrEqual( 0.85, $at_bound );
		$this->assertLessThan( 0.85, $past_bound );
	}
}
