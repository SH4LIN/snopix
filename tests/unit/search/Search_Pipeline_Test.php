<?php
/**
 * Tests for Search_Pipeline orchestration.
 *
 * @package Snopix
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use Snopix\Repository\Index_Repository;
use Snopix\Search\Fingerprint_Factory;
use Snopix\Search\Score_Calculator;
use Snopix\Search\Search_Pipeline;
use Snopix\Search\Search_Result;

/**
 * Search_Pipeline unit tests — mocked repo/factory/calculator.
 */
class Snopix_Search_Pipeline_Test extends Snopix_TestCase {

	/**
	 * Build a fingerprint payload mirroring the factory output.
	 *
	 * @return array<string, mixed>
	 */
	private function valid_fingerprint(): array {
		return array(
			'phash'        => 'abcdef1234567890',
			'color_vector' => array_fill( 0, 48, 0.5 ),
			'edge_vector'  => array_fill( 0, 32, 0.5 ),
		);
	}

	/**
	 * Make a real image attachment so hydration can resolve URLs/titles.
	 *
	 * @return int
	 */
	private function make_image_attachment(): int {
		return (int) self::factory()->attachment->create(
			array(
				'post_title'     => 'Search Pipeline Fixture',
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	/**
	 * A fingerprint that's missing required keys must throw "unfingerprintable".
	 *
	 * @return void
	 */
	public function test_throws_when_fingerprint_unprocessable(): void {
		$factory = $this->createMock( Fingerprint_Factory::class );
		$factory->method( 'generate' )->willReturn( array() );

		$pipeline = new Search_Pipeline(
			$this->createMock( Index_Repository::class ),
			$factory,
			new Score_Calculator( new \Snopix\Imaging\Similarity() )
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'unfingerprintable' );
		$pipeline->search( 1 );
	}

	/**
	 * No candidate rows must produce an empty result set.
	 *
	 * @return void
	 */
	public function test_returns_empty_when_no_candidates(): void {
		$factory = $this->createMock( Fingerprint_Factory::class );
		$factory->method( 'generate' )->willReturn( $this->valid_fingerprint() );

		$repo = $this->createMock( Index_Repository::class );
		$repo->method( 'get_candidates_for_hamming' )->willReturn( array() );

		$pipeline = new Search_Pipeline(
			$repo,
			$factory,
			new Score_Calculator( new \Snopix\Imaging\Similarity() )
		);

		$this->assertSame( array(), $pipeline->search( 1 ) );
	}

	/**
	 * Rows scoring below the threshold must be dropped.
	 *
	 * @return void
	 */
	public function test_filters_results_below_score_threshold(): void {
		$attachment_id = $this->make_image_attachment();

		$factory = $this->createMock( Fingerprint_Factory::class );
		$factory->method( 'generate' )->willReturn( $this->valid_fingerprint() );

		$repo = $this->createMock( Index_Repository::class );
		$repo->method( 'get_candidates_for_hamming' )->willReturn(
			array(
				array(
					'attachment_id' => $attachment_id,
					'phash'         => 'abcdef1234567890',
					'color_vector'  => wp_json_encode( array_fill( 0, 48, 0.5 ) ),
					'edge_vector'   => wp_json_encode( array_fill( 0, 32, 0.5 ) ),
				),
			)
		);

		$calculator = $this->createMock( Score_Calculator::class );
		$calculator->method( 'calculate' )->willReturn( 0.5 ); // Below 0.85 threshold.

		$pipeline = new Search_Pipeline( $repo, $factory, $calculator );

		$this->assertSame( array(), $pipeline->search( $attachment_id ) );
	}

	/**
	 * Rows scoring above the threshold must be returned as Search_Result objects.
	 *
	 * @return void
	 */
	public function test_returns_results_above_threshold(): void {
		$attachment_id = $this->make_image_attachment();

		$factory = $this->createMock( Fingerprint_Factory::class );
		$factory->method( 'generate' )->willReturn( $this->valid_fingerprint() );

		$repo = $this->createMock( Index_Repository::class );
		$repo->method( 'get_candidates_for_hamming' )->willReturn(
			array(
				array(
					'attachment_id' => $attachment_id,
					'phash'         => 'abcdef1234567890',
					'color_vector'  => wp_json_encode( array_fill( 0, 48, 0.5 ) ),
					'edge_vector'   => wp_json_encode( array_fill( 0, 32, 0.5 ) ),
				),
			)
		);

		$calculator = $this->createMock( Score_Calculator::class );
		$calculator->method( 'calculate' )->willReturn( 0.95 );

		$pipeline = new Search_Pipeline( $repo, $factory, $calculator );
		$results  = $pipeline->search( $attachment_id );

		$this->assertCount( 1, $results );
		$this->assertInstanceOf( Search_Result::class, $results[0] );
		$this->assertSame( $attachment_id, $results[0]->attachment_id );
		$this->assertEqualsWithDelta( 0.95, $results[0]->score, 1e-6 );
	}

	/**
	 * Results are returned sorted by score descending.
	 *
	 * @return void
	 */
	public function test_results_sorted_by_score_descending(): void {
		$ids = array(
			$this->make_image_attachment(),
			$this->make_image_attachment(),
			$this->make_image_attachment(),
		);

		$rows = array_map(
			static fn( $id ) => array(
				'attachment_id' => $id,
				'phash'         => 'abcdef1234567890',
				'color_vector'  => wp_json_encode( array_fill( 0, 48, 0.5 ) ),
				'edge_vector'   => wp_json_encode( array_fill( 0, 32, 0.5 ) ),
			),
			$ids
		);

		$factory = $this->createMock( Fingerprint_Factory::class );
		$factory->method( 'generate' )->willReturn( $this->valid_fingerprint() );

		$repo = $this->createMock( Index_Repository::class );
		$repo->method( 'get_candidates_for_hamming' )->willReturn( $rows );

		$scores     = array( $ids[0] => 0.90, $ids[1] => 0.99, $ids[2] => 0.95 );
		$calculator = $this->createMock( Score_Calculator::class );
		$calculator->method( 'calculate' )
			->willReturnCallback(
				static fn( $query, $row ) => $scores[ (int) $row['attachment_id'] ]
			);

		$pipeline = new Search_Pipeline( $repo, $factory, $calculator );
		$results  = $pipeline->search( $ids[0] );

		$this->assertCount( 3, $results );
		$this->assertGreaterThanOrEqual( $results[1]->score, $results[0]->score );
		$this->assertGreaterThanOrEqual( $results[2]->score, $results[1]->score );
	}

	/**
	 * The `limit` argument caps the result set after sorting.
	 *
	 * @return void
	 */
	public function test_limit_caps_results(): void {
		$ids = array(
			$this->make_image_attachment(),
			$this->make_image_attachment(),
			$this->make_image_attachment(),
		);

		$rows = array_map(
			static fn( $id ) => array(
				'attachment_id' => $id,
				'phash'         => 'abcdef1234567890',
				'color_vector'  => wp_json_encode( array_fill( 0, 48, 0.5 ) ),
				'edge_vector'   => wp_json_encode( array_fill( 0, 32, 0.5 ) ),
			),
			$ids
		);

		$factory = $this->createMock( Fingerprint_Factory::class );
		$factory->method( 'generate' )->willReturn( $this->valid_fingerprint() );

		$repo = $this->createMock( Index_Repository::class );
		$repo->method( 'get_candidates_for_hamming' )->willReturn( $rows );

		$calculator = $this->createMock( Score_Calculator::class );
		$calculator->method( 'calculate' )->willReturn( 0.95 );

		$pipeline = new Search_Pipeline( $repo, $factory, $calculator );
		$results  = $pipeline->search( $ids[0], 2 );

		$this->assertCount( 2, $results );
	}

	/**
	 * Candidate rows without a `phash` key must be skipped (defensive).
	 *
	 * @return void
	 */
	public function test_candidates_without_phash_are_skipped(): void {
		$id = $this->make_image_attachment();

		$factory = $this->createMock( Fingerprint_Factory::class );
		$factory->method( 'generate' )->willReturn( $this->valid_fingerprint() );

		$repo = $this->createMock( Index_Repository::class );
		$repo->method( 'get_candidates_for_hamming' )->willReturn(
			array(
				array( 'attachment_id' => $id ), // No phash key.
			)
		);

		$pipeline = new Search_Pipeline(
			$repo,
			$factory,
			new Score_Calculator( new \Snopix\Imaging\Similarity() )
		);

		$this->assertSame( array(), $pipeline->search( $id ) );
	}
}
