<?php
/**
 * Unit tests for Snopix\Search\Search_Pipeline.
 *
 * The pipeline is driven with a fake repository (canned candidate rows) and a
 * fake fingerprint factory (canned query fingerprint), against the real
 * Score_Calculator. The few WordPress functions the result-hydration tail
 * touches are stubbed below so the scoring / ordering / threshold / limit
 * logic can run with no WordPress loaded.
 *
 * @package Snopix
 */

use Snopix\Imaging\Similarity;
use Snopix\Repository\Index_Repository;
use Snopix\Search\Fingerprint_Factory;
use Snopix\Search\Score_Calculator;
use Snopix\Search\Search_Pipeline;

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stub: force Settings to fall back to its hard-coded defaults (match
	 * threshold 0.85) by always returning an empty option payload.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 */
	function get_option( $name, $default = false ) {
		return array();
	}
}

if ( ! function_exists( '_prime_post_caches' ) ) {
	/**
	 * Stub: cache priming is a no-op outside WordPress.
	 *
	 * @param array<int> $ids        Attachment IDs.
	 * @param bool       $update_meta Unused.
	 * @param bool       $update_term Unused.
	 *
	 * @return void
	 */
	function _prime_post_caches( $ids, $update_meta = true, $update_term = true ) {}
}

if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
	/**
	 * Stub: synthesise a predictable image src tuple.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $size Size name.
	 *
	 * @return array{0: string, 1: int, 2: int}
	 */
	function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) {
		return array( "http://example.test/{$id}-{$size}.jpg", 100, 100 );
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * Stub: synthesise a predictable title.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return string
	 */
	function get_the_title( $id = 0 ) {
		return "Image {$id}";
	}
}

/**
 * Repository stub returning canned candidate rows. The parent constructor
 * (which requires a \wpdb) is intentionally bypassed; only the hamming
 * candidate lookup is exercised.
 */
final class Fake_Search_Repo extends Index_Repository {

	/**
	 * @var array<int, array<string, mixed>>
	 */
	public array $candidates = array();

	public function __construct() {} // phpcs:ignore -- skip parent wpdb dependency.

	/**
	 * @param string $query_phash  Unused; candidates are pre-canned.
	 * @param int    $max_distance Unused.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_candidates_for_hamming( string $query_phash, int $max_distance ): array {
		return $this->candidates;
	}
}

/**
 * Fingerprint factory stub returning a canned query fingerprint.
 */
final class Fake_Search_Factory extends Fingerprint_Factory {

	/**
	 * @var array<string, mixed>
	 */
	public array $fp = array();

	public function __construct() {} // phpcs:ignore -- skip parent loader dependency.

	/**
	 * @param int $attachment_id Unused; the canned fingerprint is returned.
	 *
	 * @return array<string, mixed>
	 */
	public function generate( int $attachment_id ): array {
		return $this->fp;
	}
}

/**
 * @covers \Snopix\Search\Search_Pipeline
 */
final class Search_Pipeline_Test extends Snopix_Unit_TestCase {

	/**
	 * A fingerprint that scores 1.0 against itself (see Score_Calculator_Test).
	 *
	 * @return array<string, mixed>
	 */
	private function base_fingerprint(): array {
		return array(
			'phash'        => 'a1b2c3d4e5f60718',
			'color_vector' => array( 0.6, 0.4, 0.7, 0.3, 0.2, 0.8 ),
			'edge_vector'  => array( 1.0, 2.0, 3.0, 4.0 ),
		);
	}

	private function make_pipeline( Fake_Search_Repo $repo, Fake_Search_Factory $factory ): Search_Pipeline {
		return new Search_Pipeline( $repo, $factory, new Score_Calculator( new Similarity() ) );
	}

	/**
	 * Build a candidate row: a base fingerprint tagged with an attachment id.
	 *
	 * @param int                  $id      Attachment id.
	 * @param array<string, mixed> $overrides Fingerprint key overrides.
	 *
	 * @return array<string, mixed>
	 */
	private function row( int $id, array $overrides = array() ): array {
		return array_merge( $this->base_fingerprint(), array( 'attachment_id' => $id ), $overrides );
	}

	public function test_throws_when_query_is_unfingerprintable(): void {
		$factory     = new Fake_Search_Factory();
		$factory->fp = array(); // Empty fingerprint.

		$this->expectException( \RuntimeException::class );
		$this->make_pipeline( new Fake_Search_Repo(), $factory )->search( 1 );
	}

	public function test_returns_empty_when_no_candidates(): void {
		$factory     = new Fake_Search_Factory();
		$factory->fp = $this->base_fingerprint();

		$repo             = new Fake_Search_Repo();
		$repo->candidates = array();

		$this->assertSame( array(), $this->make_pipeline( $repo, $factory )->search( 1 ) );
	}

	public function test_orders_results_by_score_descending_and_drops_sub_threshold(): void {
		$factory     = new Fake_Search_Factory();
		$factory->fp = $this->base_fingerprint();

		$repo             = new Fake_Search_Repo();
		$repo->candidates = array(
			// Identical → score 1.0.
			$this->row( 10 ),
			// One-bit pHash difference, identical colour/edge → just under 1.0.
			$this->row( 20, array( 'phash' => 'a1b2c3d4e5f60719' ) ),
			// Inverted pHash + disjoint colour + opposite edge → below 0.85, dropped.
			$this->row(
				30,
				array(
					'phash'        => '5e4d3c2b1a09f8e7',
					'color_vector' => array( 0.0, 1.0, 0.0, 1.0, 1.0, 0.0 ),
					'edge_vector'  => array( -1.0, -2.0, -3.0, -4.0 ),
				)
			),
		);

		$results = $this->make_pipeline( $repo, $factory )->search( 1 );

		$this->assertCount( 2, $results );
		$this->assertSame( 10, $results[0]->attachment_id );
		$this->assertSame( 20, $results[1]->attachment_id );
		$this->assertGreaterThan( $results[1]->score, $results[0]->score );
	}

	public function test_respects_limit(): void {
		$factory     = new Fake_Search_Factory();
		$factory->fp = $this->base_fingerprint();

		$repo             = new Fake_Search_Repo();
		$repo->candidates = array(
			$this->row( 10 ),
			$this->row( 20, array( 'phash' => 'a1b2c3d4e5f60719' ) ),
		);

		$results = $this->make_pipeline( $repo, $factory )->search( 1, 1 );

		$this->assertCount( 1, $results );
		$this->assertSame( 10, $results[0]->attachment_id );
	}
}
