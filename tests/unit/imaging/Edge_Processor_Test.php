<?php
/**
 * Unit tests for Snopix\Imaging\Edge_Processor.
 *
 * @package Snopix
 */

use Snopix\Imaging\Edge_Processor;
use Snopix\Imaging\Similarity;

/**
 * @covers \Snopix\Imaging\Edge_Processor
 */
final class Edge_Processor_Test extends Snopix_Unit_TestCase {

	private Edge_Processor $processor;

	protected function setUp(): void {
		parent::setUp();
		$this->processor = new Edge_Processor();
	}

	/**
	 * Convenience: run the processor and return the raw edge vector.
	 *
	 * @param \GdImage $gd Image resource.
	 *
	 * @return array<int, float>
	 */
	private function vector( \GdImage $gd ): array {
		$result = $this->processor->process( $gd, 1 );
		return $result['edge_vector'];
	}

	public function test_process_returns_edge_vector_key(): void {
		$gd     = self::gd_from_fixture( 1 );
		$result = $this->processor->process( $gd, 1 );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'edge_vector', $result );
		$this->assertIsArray( $result['edge_vector'] );
	}

	public function test_vector_has_length_64(): void {
		$vector = $this->vector( self::gd_from_fixture( 1 ) );
		$this->assertCount( 64, $vector );
	}

	public function test_vector_values_are_floats_in_normalised_range(): void {
		$vector = $this->vector( self::gd_from_fixture( 3 ) );

		foreach ( $vector as $v ) {
			$this->assertIsFloat( $v );
			$this->assertGreaterThanOrEqual( 0.0, $v );
			$this->assertLessThanOrEqual( 1.0, $v );
		}
	}

	public function test_vector_sums_to_one(): void {
		// normalise() divides by the total, so a non-degenerate image's
		// histogram is a distribution summing to 1.0.
		$vector = $this->vector( self::gd_from_fixture( 5 ) );
		$this->assertEqualsWithDelta( 1.0, array_sum( $vector ), 1e-9 );
	}

	public function test_process_is_deterministic_for_same_image(): void {
		// Two independent decodes of the same fixture yield identical vectors.
		$a = $this->vector( self::gd_from_fixture( 7 ) );
		$b = $this->vector( self::gd_from_fixture( 7 ) );

		$this->assertSame( $a, $b );
	}

	public function test_process_is_deterministic_on_repeated_calls(): void {
		$gd = self::gd_from_fixture( 2 );

		$a = $this->vector( $gd );
		$b = $this->vector( $gd );

		$this->assertSame( $a, $b );
	}

	public function test_downscale_variant_more_similar_to_base_than_other_fixture(): void {
		$similarity = new Similarity();

		$base      = $this->vector( self::gd_from_fixture( 1 ) );
		$downscale = $this->vector( self::gd_from_path( self::variation_path( 1, 'downscale' ) ) );
		$other     = $this->vector( self::gd_from_fixture( 10 ) );

		$near = $similarity->bhattacharyya_similarity( $base, $downscale, Edge_Processor::COMPONENT_SIZES );
		$far  = $similarity->bhattacharyya_similarity( $base, $other, Edge_Processor::COMPONENT_SIZES );

		$this->assertGreaterThan( $far, $near );
	}

	public function test_blur_variant_more_similar_to_base_than_other_fixture(): void {
		$similarity = new Similarity();

		$base  = $this->vector( self::gd_from_fixture( 1 ) );
		$blur  = $this->vector( self::gd_from_path( self::variation_path( 1, 'blur' ) ) );
		$other = $this->vector( self::gd_from_fixture( 10 ) );

		$near = $similarity->bhattacharyya_similarity( $base, $blur, Edge_Processor::COMPONENT_SIZES );
		$far  = $similarity->bhattacharyya_similarity( $base, $other, Edge_Processor::COMPONENT_SIZES );

		$this->assertGreaterThan( $far, $near );
	}
}
