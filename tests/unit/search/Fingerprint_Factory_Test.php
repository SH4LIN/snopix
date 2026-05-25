<?php
/**
 * Tests for Fingerprint_Factory composition.
 *
 * @package Snopix
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use Snopix\Imaging\GD_Loader;
use Snopix\Imaging\Processor_Interface;
use Snopix\Search\Fingerprint_Factory;

/**
 * Fingerprint_Factory unit tests.
 */
class Snopix_Fingerprint_Factory_Test extends Snopix_TestCase {

	/**
	 * Build a small in-memory GD image to feed the factory.
	 *
	 * @param int $w Width.
	 * @param int $h Height.
	 *
	 * @return \GdImage
	 */
	private function make_gd_image( int $w = 64, int $h = 64 ) {
		$img = imagecreatetruecolor( $w, $h );
		imagefill( $img, 0, 0, imagecolorallocate( $img, 100, 150, 200 ) );
		return $img;
	}

	/**
	 * Stub loader that hands back a fixed GD resource.
	 *
	 * @param \GdImage $gd GD image to return.
	 *
	 * @return GD_Loader
	 */
	private function loader_returning( $gd ): GD_Loader {
		$loader = $this->createMock( GD_Loader::class );
		$loader->method( 'load' )->willReturn( $gd );
		return $loader;
	}

	/**
	 * Stub processor producing a single named key.
	 *
	 * @param string $key   Output key.
	 * @param mixed  $value Output value.
	 *
	 * @return Processor_Interface
	 */
	private function processor_returning( string $key, $value ): Processor_Interface {
		$proc = $this->createMock( Processor_Interface::class );
		$proc->method( 'process' )->willReturn( array( $key => $value ) );
		return $proc;
	}

	/**
	 * Failed image load must result in an empty fingerprint.
	 *
	 * @return void
	 */
	public function test_returns_empty_when_loader_fails(): void {
		$loader = $this->createMock( GD_Loader::class );
		$loader->method( 'load' )->willReturn( false );

		$factory = new Fingerprint_Factory( $loader );
		$this->assertSame( array(), $factory->generate( 1 ) );
	}

	/**
	 * A single processor's output is merged into the fingerprint.
	 *
	 * @return void
	 */
	public function test_merges_single_processor_output(): void {
		$gd      = $this->make_gd_image();
		$loader  = $this->loader_returning( $gd );
		$factory = new Fingerprint_Factory(
			$loader,
			$this->processor_returning( 'phash', 'abc' )
		);

		$result = $factory->generate( 1 );
		$this->assertSame( array( 'phash' => 'abc' ), $result );
	}

	/**
	 * Multiple processors' outputs are merged into a single fingerprint.
	 *
	 * @return void
	 */
	public function test_merges_multiple_processors(): void {
		$gd     = $this->make_gd_image();
		$loader = $this->loader_returning( $gd );

		$factory = new Fingerprint_Factory(
			$loader,
			$this->processor_returning( 'phash', 'abc' ),
			$this->processor_returning( 'color_vector', array( 0.1, 0.2 ) ),
			$this->processor_returning( 'edge_vector', array( 0.5 ) )
		);

		$result = $factory->generate( 1 );

		$this->assertArrayHasKey( 'phash', $result );
		$this->assertArrayHasKey( 'color_vector', $result );
		$this->assertArrayHasKey( 'edge_vector', $result );
		$this->assertSame( 'abc', $result['phash'] );
	}

	/**
	 * Later processors override earlier ones on key conflicts (array_merge).
	 *
	 * @return void
	 */
	public function test_later_processor_wins_on_key_conflict(): void {
		$gd      = $this->make_gd_image();
		$loader  = $this->loader_returning( $gd );
		$factory = new Fingerprint_Factory(
			$loader,
			$this->processor_returning( 'phash', 'first' ),
			$this->processor_returning( 'phash', 'second' )
		);

		$this->assertSame( 'second', $factory->generate( 1 )['phash'] );
	}

	/**
	 * Images larger than the 512-pixel working dimension must be pre-scaled
	 * before processors see them. The factory still produces a fingerprint.
	 *
	 * @return void
	 */
	public function test_oversized_image_is_processed_after_downscale(): void {
		$oversized = $this->make_gd_image( 1024, 1024 );
		$loader    = $this->loader_returning( $oversized );

		$captured_dim = 0;
		$proc         = $this->createMock( Processor_Interface::class );
		$proc->method( 'process' )
			->willReturnCallback(
				function ( $gd ) use ( &$captured_dim ) {
					$captured_dim = max( imagesx( $gd ), imagesy( $gd ) );
					return array( 'phash' => 'ok' );
				}
			);

		$factory = new Fingerprint_Factory( $loader, $proc );
		$result  = $factory->generate( 1 );

		$this->assertNotEmpty( $result );
		$this->assertLessThanOrEqual( 512, $captured_dim );
	}

	/**
	 * Loader's `destroy` is invoked on the working GD resource to free memory.
	 *
	 * @return void
	 */
	public function test_loader_destroy_is_called(): void {
		$gd     = $this->make_gd_image();
		$loader = $this->createMock( GD_Loader::class );
		$loader->method( 'load' )->willReturn( $gd );
		$loader->expects( $this->once() )->method( 'destroy' );

		$factory = new Fingerprint_Factory(
			$loader,
			$this->processor_returning( 'phash', 'x' )
		);
		$factory->generate( 1 );
	}
}
