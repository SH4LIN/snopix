<?php
/**
 * Search pipeline — scores indexed images against a query fingerprint.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Search;

use PixelScout\Repository\Index_Repository;
use PixelScout\Imaging\Similarity;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates reverse image search: generate query fingerprint, score all indexed images,
 * filter by threshold, and hydrate results.
 */
class Search_Pipeline {

	private const HAMMING_THRESHOLD = 20;
	private const SCORE_THRESHOLD   = 0.85;

	/**
	 * Constructor.
	 *
	 * @param Index_Repository    $repository Index data access.
	 * @param Fingerprint_Factory $factory  Fingerprint generator.
	 * @param Score_Calculator    $calculator Composite score calculator.
	 * @param Similarity          $similarity Similarity metrics for pre-filtering.
	 */
	public function __construct(
		private Index_Repository $repository,
		private Fingerprint_Factory $factory,
		private Score_Calculator $calculator,
		private Similarity $similarity
	) {}

	/**
	 * Run reverse image search for the given attachment.
	 *
	 * @param int $attachment_id Query attachment ID.
	 * @param int $limit         Maximum results to return.
	 *
	 * @return Search_Result[]
	 *
	 * @throws \RuntimeException When the query image cannot be fingerprinted.
	 */
	public function search( int $attachment_id, int $limit = 20 ): array {
		$query_fp = $this->factory->generate( $attachment_id );

		if ( empty( $query_fp ) || ! isset( $query_fp['phash'], $query_fp['color_vector'], $query_fp['edge_vector'] ) ) {
			throw new \RuntimeException( 'unfingerprintable' );
		}

		$all = $this->repository->get_all_indexed();

		if ( empty( $all ) ) {
			return array();
		}

		$scored = array();

		foreach ( $all as $row ) {
			if ( ! isset( $row['phash'] ) ) {
				continue;
			}

			if ( $this->similarity->hamming_distance( $query_fp['phash'], $row['phash'] ) > self::HAMMING_THRESHOLD ) {
				continue;
			}

			$score = $this->calculator->calculate( $query_fp, $row );

			if ( $score < self::SCORE_THRESHOLD ) {
				continue;
			}

			$scored[] = array(
				'row'   => $row,
				'score' => $score,
			);
		}

		usort( $scored, static fn( $a, $b ) => $b['score'] <=> $a['score'] );
		$scored = array_slice( $scored, 0, $limit );

		$results = array();

		foreach ( $scored as $item ) {
			$row   = $item['row'];
			$score = $item['score'];
			$id    = (int) $row['attachment_id'];

			$src   = wp_get_attachment_image_src( $id, 'full' );
			$thumb = wp_get_attachment_image_src( $id, 'thumbnail' );

			$url       = $src ? $src[0] : '';
			$thumb_url = $thumb ? $thumb[0] : '';
			$title     = get_the_title( $id );

			$results[] = new Search_Result( $id, $url, $thumb_url, $title, $score );
		}

		return $results;
	}
}
