<?php
/**
 * Fingerprint factory — orchestrates GD loading and processor pipeline.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Search;

use PixelScout\Imaging\{GD_Loader, Processor_Interface};
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a complete image fingerprint by running all registered processors.
 */
class Fingerprint_Factory {

	private const MAX_WORKING_DIM = 512;

	/**
	 * Registered image processors.
	 *
	 * @var Processor_Interface[]
	 */
	private array $processors;

	/**
	 * Constructor.
	 *
	 * @param GD_Loader           $loader        GD loader.
	 * @param Processor_Interface ...$processors Processors to run.
	 */
	public function __construct(
		private GD_Loader $loader,
		Processor_Interface ...$processors // phpcs:ignore WordPress.Commenting.FunctionComment.InvalidTypeHint
	) {
		$this->processors = $processors;
	}

	/**
	 * Generate a combined fingerprint array for the given attachment.
	 *
	 * Returns an empty array if the image cannot be loaded.
	 *
	 * @param int $attachment_id WordPress attachment ID.
	 *
	 * @return array<string, mixed> Merged fingerprint from all processors.
	 */
	public function generate( int $attachment_id ): array {
		$gd = $this->loader->load( $attachment_id );

		if ( false === $gd ) {
			return array();
		}

		$w   = imagesx( $gd );
		$h   = imagesy( $gd );
		$max = max( $w, $h );

		if ( $max > self::MAX_WORKING_DIM ) {
			$scale   = self::MAX_WORKING_DIM / $max;
			$resized = imagescale( $gd, (int) round( $w * $scale ), (int) round( $h * $scale ) );

			if ( false !== $resized ) {
				imagedestroy( $gd ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
				$gd = $resized;
			}
		}

		$fingerprint = array();

		foreach ( $this->processors as $processor ) {
			$fingerprint = array_merge( $fingerprint, $processor->process( $gd, $attachment_id ) );
		}

		$this->loader->destroy( $gd );

		return $fingerprint;
	}
}
