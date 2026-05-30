<?php
/**
 * Search result value object.
 *
 * @package Snopix
 */

namespace Snopix\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Immutable value object representing a single image search result.
 */
class Search_Result {

	/**
	 * Constructor.
	 *
	 * @param int    $attachment_id WordPress attachment ID.
	 * @param string $url          Full-size image URL.
	 * @param string $thumbnail  Thumbnail image URL.
	 * @param string $title      Attachment title.
	 * @param float  $score      Composite similarity score 0.0–1.0.
	 */
	public function __construct(
		public int $attachment_id,
		public string $url,
		public string $thumbnail,
		public string $title,
		public float $score
	) {}
}
