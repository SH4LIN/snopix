<?php
/**
 * Unit tests for Snopix\Imaging\PHash_Processor.
 *
 * @package Snopix
 */

use Snopix\Imaging\PHash_Processor;
use Snopix\Imaging\Similarity;

/**
 * @covers \Snopix\Imaging\PHash_Processor
 */
final class Phash_Processor_Test extends Snopix_Unit_TestCase {

	private PHash_Processor $processor;

	protected function setUp(): void {
		parent::setUp();
		$this->processor = new PHash_Processor();
	}

	public function test_process_returns_phash_key_with_16_char_hex(): void {
		$gd     = self::gd_from_fixture( 1 );
		$result = $this->processor->process( $gd, 42 );

		$this->assertArrayHasKey( 'phash', $result );
		$this->assertIsString( $result['phash'] );
		$this->assertSame( 16, strlen( $result['phash'] ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{16}$/', $result['phash'] );
	}

	public function test_process_is_deterministic_for_same_image(): void {
		$gd_a = self::gd_from_fixture( 3 );
		$gd_b = self::gd_from_fixture( 3 );

		$first  = $this->processor->process( $gd_a, 1 );
		$second = $this->processor->process( $gd_b, 1 );


		$this->assertSame( $first['phash'], $second['phash'] );
	}

	public function test_attachment_id_is_passthrough_does_not_affect_hash(): void {
		$gd_a = self::gd_from_fixture( 5 );
		$gd_b = self::gd_from_fixture( 5 );

		$with_low  = $this->processor->process( $gd_a, 0 );
		$with_high = $this->processor->process( $gd_b, 999999 );


		$this->assertSame( $with_low['phash'], $with_high['phash'] );
	}

	public function test_near_duplicate_is_closer_than_different_fixture(): void {
		$similarity = new Similarity();

		$base_gd = self::gd_from_fixture( 1 );
		$base    = $this->processor->process( $base_gd, 1 )['phash'];

		$dup_gd = self::gd_from_path( self::variation_path( 1, 'compressed' ) );
		$dup    = $this->processor->process( $dup_gd, 1 )['phash'];

		$other_gd = self::gd_from_fixture( 10 );
		$other    = $this->processor->process( $other_gd, 10 )['phash'];

		$dup_distance   = $similarity->hamming_distance( $base, $dup );
		$other_distance = $similarity->hamming_distance( $base, $other );

		// A re-compressed version of 001 should resemble 001 far more than 010 does.
		$this->assertLessThan( $other_distance, $dup_distance );
	}

	public function test_png_format_variant_is_near_duplicate_of_base(): void {
		$similarity = new Similarity();

		$base_gd = self::gd_from_fixture( 2 );
		$base    = $this->processor->process( $base_gd, 2 )['phash'];

		$png_gd = self::gd_from_path( self::variation_path( 2, 'png' ) );
		$png    = $this->processor->process( $png_gd, 2 )['phash'];

		$other_gd = self::gd_from_fixture( 15 );
		$other    = $this->processor->process( $other_gd, 15 )['phash'];

		$png_distance   = $similarity->hamming_distance( $base, $png );
		$other_distance = $similarity->hamming_distance( $base, $other );

		$this->assertLessThan( $other_distance, $png_distance );
	}
}
