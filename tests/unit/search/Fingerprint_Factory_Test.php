<?php
/**
 * Unit tests for Snopix\Search\Fingerprint_Factory.
 *
 * Exercises the factory with a fake GD loader (so no WordPress attachment
 * lookup is needed) feeding real fixture images into the real processor
 * pipeline (pHash + colour + edge).
 *
 * @package Snopix
 */

use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Edge_Processor;
use Snopix\Imaging\GD_Loader;
use Snopix\Imaging\PHash_Processor;
use Snopix\Search\Fingerprint_Factory;

/**
 * GD loader stub: returns a caller-supplied GD resource instead of resolving a
 * WordPress attachment. `false` simulates an unloadable image.
 */
final class Fake_Fingerprint_Loader extends GD_Loader {

	/**
	 * @var \GdImage|false
	 */
	public $gd_to_return = false;

	/**
	 * @param int $attachment_id Unused; the canned resource is returned.
	 *
	 * @return \GdImage|false
	 */
	public function load( int $attachment_id ) {
		return $this->gd_to_return;
	}
}

/**
 * Processor stub that always fails to fingerprint (returns an empty fragment).
 */
final class Failing_Fingerprint_Processor implements \Snopix\Imaging\Processor_Interface {

	/**
	 * @param mixed $gd_resource   Unused.
	 * @param int   $attachment_id Unused.
	 *
	 * @return array<string, mixed>
	 */
	public function process( $gd_resource, int $attachment_id ): array {
		return array();
	}
}

/**
 * @covers \Snopix\Search\Fingerprint_Factory
 */
final class Fingerprint_Factory_Test extends Snopix_Unit_TestCase {

	private function make_factory( Fake_Fingerprint_Loader $loader ): Fingerprint_Factory {
		return new Fingerprint_Factory(
			$loader,
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);
	}

	public function test_generate_merges_all_three_processor_fragments(): void {
		$loader               = new Fake_Fingerprint_Loader();
		$loader->gd_to_return = self::gd_from_fixture( 1 );

		$fp = $this->make_factory( $loader )->generate( 1 );

		$this->assertArrayHasKey( 'phash', $fp );
		$this->assertArrayHasKey( 'color_vector', $fp );
		$this->assertArrayHasKey( 'edge_vector', $fp );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{16}$/', $fp['phash'] );
	}

	public function test_generate_returns_empty_array_when_loader_fails(): void {
		$loader               = new Fake_Fingerprint_Loader();
		$loader->gd_to_return = false;

		$this->assertSame( array(), $this->make_factory( $loader )->generate( 99 ) );
	}

	public function test_generate_returns_empty_array_when_a_processor_fails(): void {
		// A failing processor must abort the whole fingerprint so the image is
		// recorded as unprocessable rather than stored with a degenerate hash
		// that would falsely cluster with other failures.
		$loader               = new Fake_Fingerprint_Loader();
		$loader->gd_to_return = self::gd_from_fixture( 1 );

		$factory = new Fingerprint_Factory(
			$loader,
			new PHash_Processor(),
			new Failing_Fingerprint_Processor(),
			new Edge_Processor()
		);

		$this->assertSame( array(), $factory->generate( 1 ) );
	}

	public function test_generate_handles_oversized_image_via_predownscale(): void {
		// The "large" variant exceeds the 512 px working dimension, so this
		// exercises the imagescale() pre-downscale branch.
		$loader               = new Fake_Fingerprint_Loader();
		$loader->gd_to_return = self::gd_from_path( self::variation_path( 1, 'large' ) );

		$fp = $this->make_factory( $loader )->generate( 1 );

		$this->assertArrayHasKey( 'phash', $fp );
		$this->assertArrayHasKey( 'color_vector', $fp );
		$this->assertArrayHasKey( 'edge_vector', $fp );
	}
}
