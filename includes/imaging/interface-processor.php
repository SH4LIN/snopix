<?php
/**
 * Processor contract for image fingerprinting.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Imaging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
interface Processor_Interface {
	/**
	 * Process a GD image resource and return a fingerprint fragment.
	 *
	 * @param mixed $gd_resource  GD image resource or GdImage object.
	 * @param int   $attachment_id WordPress attachment ID.
	 *
	 * @return array<string, mixed>
	 */
	public function process( $gd_resource, int $attachment_id ): array;
}
