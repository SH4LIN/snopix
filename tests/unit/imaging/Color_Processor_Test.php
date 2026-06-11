<?php
/**
 * Unit tests for Snopix\Imaging\Color_Processor.
 *
 * @package Snopix
 */

use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Similarity;

/**
 * @covers \Snopix\Imaging\Color_Processor
 */
final class Color_Processor_Test extends Snopix_Unit_TestCase {

	private Color_Processor $processor;

	protected function setUp(): void {
		parent::setUp();
		$this->processor = new Color_Processor();
	}

	public function test_output_has_color_vector_of_32_floats(): void {
		$gd     = self::gd_from_fixture( 1 );
		$result = $this->processor->process( $gd, 1 );
		imagedestroy( $gd );

		$this->assertArrayHasKey( 'color_vector', $result );
		$this->assertCount( array_sum( Color_Processor::COMPONENT_SIZES ), $result['color_vector'] );
		$this->assertCount( 32, $result['color_vector'] );
		foreach ( $result['color_vector'] as $value ) {
			$this->assertIsFloat( $value );
		}
	}

	public function test_all_values_are_within_unit_range(): void {
		$gd     = self::gd_from_fixture( 1 );
		$vector = $this->processor->process( $gd, 1 )['color_vector'];
		imagedestroy( $gd );

		foreach ( $vector as $value ) {
			$this->assertGreaterThanOrEqual( 0.0, $value );
			$this->assertLessThanOrEqual( 1.0, $value );
		}
	}

	public function test_each_component_histogram_sums_to_one(): void {
		// Every pixel contributes one full vote per component: the hue vote is
		// saturation-weighted but the achromatic remainder is spread across
		// the hue bins, so each component still sums to 1.0.
		$gd     = self::gd_from_fixture( 1 );
		$vector = $this->processor->process( $gd, 1 )['color_vector'];
		imagedestroy( $gd );

		$hue        = array_sum( array_slice( $vector, 0, 16 ) );
		$saturation = array_sum( array_slice( $vector, 16, 8 ) );
		$value      = array_sum( array_slice( $vector, 24, 8 ) );

		$this->assertEqualsWithDelta( 1.0, $hue, 1e-9 );
		$this->assertEqualsWithDelta( 1.0, $saturation, 1e-9 );
		$this->assertEqualsWithDelta( 1.0, $value, 1e-9 );
	}

	public function test_solid_red_image_bins_deterministically(): void {
		// Pure red: hue 0.0 (split evenly between the adjacent wrap-around
		// bins 15 and 0), saturation 1.0 (clamped into the last bin), value
		// 1.0 (clamped into the last bin). No achromatic spread (s = 1).
		$gd = imagecreatetruecolor( 10, 10 );
		imagefilledrectangle( $gd, 0, 0, 9, 9, imagecolorallocate( $gd, 255, 0, 0 ) );
		$vector = $this->processor->process( $gd, 1 )['color_vector'];
		imagedestroy( $gd );

		$this->assertEqualsWithDelta( 0.5, $vector[0], 1e-9 );
		$this->assertEqualsWithDelta( 0.5, $vector[15], 1e-9 );
		$this->assertEqualsWithDelta( 0.0, array_sum( array_slice( $vector, 1, 14 ) ), 1e-9 );
		$this->assertEqualsWithDelta( 1.0, $vector[16 + 7], 1e-9 );
		$this->assertEqualsWithDelta( 1.0, $vector[24 + 7], 1e-9 );
	}

	public function test_grey_image_spreads_hue_uniformly(): void {
		// A grey pixel has no meaningful hue: its hue vote is spread evenly,
		// so two different greys still match on the hue component.
		$gd = imagecreatetruecolor( 10, 10 );
		imagefilledrectangle( $gd, 0, 0, 9, 9, imagecolorallocate( $gd, 128, 128, 128 ) );
		$vector = $this->processor->process( $gd, 1 )['color_vector'];
		imagedestroy( $gd );

		foreach ( array_slice( $vector, 0, 16 ) as $bin ) {
			$this->assertEqualsWithDelta( 1.0 / 16.0, $bin, 1e-9 );
		}
	}

	public function test_process_is_deterministic(): void {
		$gd1 = self::gd_from_fixture( 3 );
		$gd2 = self::gd_from_fixture( 3 );
		$a   = $this->processor->process( $gd1, 3 )['color_vector'];
		$b   = $this->processor->process( $gd2, 3 )['color_vector'];
		imagedestroy( $gd1 );
		imagedestroy( $gd2 );

		$this->assertSame( $a, $b );
	}

	public function test_png_variant_matches_base_more_than_a_different_fixture(): void {
		$similarity = new Similarity();

		$base_gd = self::gd_from_fixture( 1 );
		$png_gd  = self::gd_from_path( self::variation_path( 1, 'png' ) );
		$other_gd = self::gd_from_fixture( 5 );

		$base  = $this->processor->process( $base_gd, 1 )['color_vector'];
		$png   = $this->processor->process( $png_gd, 1 )['color_vector'];
		$other = $this->processor->process( $other_gd, 5 )['color_vector'];

		imagedestroy( $base_gd );
		imagedestroy( $png_gd );
		imagedestroy( $other_gd );

		$same_format = $similarity->bhattacharyya_similarity( $base, $png, Color_Processor::COMPONENT_SIZES );
		$diff_image  = $similarity->bhattacharyya_similarity( $base, $other, Color_Processor::COMPONENT_SIZES );

		// Same image re-encoded as PNG: histograms nearly identical.
		$this->assertGreaterThan( 0.95, $same_format );
		// A different fixture is a worse match than the format variant.
		$this->assertGreaterThan( $diff_image, $same_format );
	}
}
