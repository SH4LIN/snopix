<?php
/**
 * Search result value object.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Immutable value object representing a single image search result.
 */
class Search_Result {

	/**
	 * @param int    $attachment_id WordPress attachment ID.
	 * @param string $url           Full-size image URL.
	 * @param string $thumbnail     Thumbnail image URL.
	 * @param string $title         Attachment title.
	 * @param float  $score         Composite similarity score 0.0–1.0.
	 */
	public function __construct(
		public readonly int    $attachment_id,
		public readonly string $url,
		public readonly string $thumbnail,
		public readonly string $title,
		public readonly float  $score
	) {}
}
