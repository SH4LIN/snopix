<?php
/**
 * Search pipeline - scores indexed images against a query fingerprint.
 *
 * @package Snopix
 */

namespace Snopix\Search;

use Snopix\Repository\Index_Repository;
use Snopix\Hooks\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates reverse image search: generate query fingerprint, score all indexed images,
 * filter by threshold, and hydrate results.
 *
 * The composite-score floor used by the final filter comes from
 * {@see Settings::get_match_threshold()} so the admin can tune precision /
 * recall from the Settings tab. The cheap hamming pre-filter is derived from
 * the same threshold via {@see Score_Calculator::max_passing_hamming()}, the
 * loosest cutoff that cannot exclude a row the final gate could accept.
 */
class Search_Pipeline {

	/**
	 * Constructor.
	 *
	 * @param Index_Repository    $repository Index data access.
	 * @param Fingerprint_Factory $factory  Fingerprint generator.
	 * @param Score_Calculator    $calculator Composite score calculator.
	 */
	public function __construct(
		private Index_Repository $repository,
		private Fingerprint_Factory $factory,
		private Score_Calculator $calculator
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

		$threshold        = Settings::get_match_threshold();
		$hamming_distance = Score_Calculator::max_passing_hamming( $threshold );
		$candidates       = $this->repository->get_candidates_for_hamming(
			(string) $query_fp['phash'],
			$hamming_distance
		);

		if ( empty( $candidates ) ) {
			return array();
		}

		$scored = array();

		foreach ( $candidates as $row ) {
			if ( ! isset( $row['phash'] ) ) {
				continue;
			}

			$score = $this->calculator->calculate( $query_fp, $row );

			if ( $score < $threshold ) {
				continue;
			}

			$scored[] = array(
				'row'   => $row,
				'score' => $score,
			);
		}

		usort( $scored, static fn( $a, $b ) => $b['score'] <=> $a['score'] );
		$scored = array_slice( $scored, 0, $limit );

		if ( empty( $scored ) ) {
			return array();
		}

		// Prime the post + attachment-meta object cache for the result IDs in
		// one shot so the per-result hydration below only hits the cache.
		$result_ids = array_map( static fn( $item ) => (int) $item['row']['attachment_id'], $scored );
		_prime_post_caches( $result_ids, true, true );

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
