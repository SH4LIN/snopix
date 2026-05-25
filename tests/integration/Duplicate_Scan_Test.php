<?php
/**
 * Integration tests for the duplicate scan pipeline against real fixtures.
 *
 * Exercises the full PHP path: index real images → Duplicate_Scanner::run →
 * verify the resulting groups exactly cover the seeded duplicates.
 *
 * @package Snopix
 */

require_once __DIR__ . '/class-fixture-helper.php';

use Snopix\Duplicates\Duplicate_Finder;
use Snopix\Duplicates\Duplicate_Progress;
use Snopix\Duplicates\Duplicate_Scanner;
use Snopix\Imaging\Color_Processor;
use Snopix\Imaging\Edge_Processor;
use Snopix\Imaging\GD_Loader;
use Snopix\Imaging\PHash_Processor;
use Snopix\Imaging\Similarity;
use Snopix\Indexing\Image_Indexer;
use Snopix\Indexing\Mime_Validator;
use Snopix\Infrastructure\Action_Scheduler;
use Snopix\Repository\Index_Repository;
use Snopix\Repository\Schema;
use Snopix\Search\Fingerprint_Factory;

/**
 * Full-pipeline duplicate scan tests.
 */
class Snopix_Duplicate_Scan_Test extends Snopix_Integration_TestCase {

	private Index_Repository $repo;
	private Image_Indexer $indexer;
	private Duplicate_Scanner $scanner;

	/**
	 * Boot the indexer + scanner against the test DB.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Schema() )->install();
		$this->repo = new Index_Repository( $wpdb );

		$factory       = new Fingerprint_Factory(
			new GD_Loader(),
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);
		$this->indexer = new Image_Indexer( new Mime_Validator(), $factory, $this->repo );

		$progress       = new Duplicate_Progress();
		$progress->reset();
		$this->scanner = new Duplicate_Scanner(
			$this->repo,
			new Duplicate_Finder( new Similarity() ),
			$progress,
			new Action_Scheduler()
		);

		delete_option( 'snopix_duplicate_results' );
		delete_option( 'snopix_duplicate_last_scanned' );
		delete_transient( 'snopix_duplicate_scan_state' );
	}

	/**
	 * Clean persisted scan state.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( 'snopix_duplicate_results' );
		delete_option( 'snopix_duplicate_last_scanned' );
		delete_transient( 'snopix_duplicate_scan_state' );
		( new Duplicate_Progress() )->reset();
		parent::tearDown();
	}

	/**
	 * Find a group containing the given attachment IDs.
	 *
	 * @param array<int, array{match_type: string, ids: array<int>}> $groups Scanner results.
	 * @param int                                                    ...$ids Required IDs.
	 *
	 * @return array{match_type: string, ids: array<int>}|null Matching group or null.
	 */
	private function find_group_containing( array $groups, int ...$ids ): ?array {
		foreach ( $groups as $g ) {
			$found = true;
			foreach ( $ids as $id ) {
				if ( ! in_array( $id, $g['ids'], true ) ) {
					$found = false;
					break;
				}
			}
			if ( $found ) {
				return $g;
			}
		}
		return null;
	}

	/**
	 * Two byte-identical copies of one fixture must produce an exact group.
	 *
	 * @return void
	 */
	public function test_byte_identical_copies_produce_exact_group(): void {
		list( $a, $b ) = $this->attach_fixture_twice( 1 );
		$this->assertTrue( $this->indexer->index_single( $a ) );
		$this->assertTrue( $this->indexer->index_single( $b ) );

		$this->scanner->run();
		$groups = $this->scanner->get_results();

		$g = $this->find_group_containing( $groups, $a, $b );
		$this->assertNotNull( $g, 'No group containing both copies' );
		$this->assertSame( 'exact', $g['match_type'] );
	}

	/**
	 * A blurred copy (different bytes, same perceptual content) produces a
	 * perceptual group.
	 *
	 * @return void
	 */
	public function test_blurred_copy_produces_perceptual_group(): void {
		$orig = $this->attach_fixture( 2 );
		$blur = $this->attach_variant( 2, 'blur' );

		$this->assertTrue( $this->indexer->index_single( $orig ) );
		$this->assertTrue( $this->indexer->index_single( $blur ) );

		$this->scanner->run();
		$groups = $this->scanner->get_results();

		$g = $this->find_group_containing( $groups, $orig, $blur );
		$this->assertNotNull( $g, 'Blurred variant not grouped with original' );
		$this->assertSame( 'perceptual', $g['match_type'] );
	}

	/**
	 * Unrelated indexed images must not be grouped.
	 *
	 * @return void
	 */
	public function test_unrelated_images_are_not_grouped(): void {
		$a = $this->attach_fixture( 1 );
		$b = $this->attach_fixture( 10 );
		$c = $this->attach_fixture( 20 );

		foreach ( array( $a, $b, $c ) as $id ) {
			$this->assertTrue( $this->indexer->index_single( $id ) );
		}

		$this->scanner->run();
		$groups = $this->scanner->get_results();

		// Each pair could in theory share a perceptual root if the algo is too
		// loose. We assert none of these three appear together.
		$this->assertNull( $this->find_group_containing( $groups, $a, $b ) );
		$this->assertNull( $this->find_group_containing( $groups, $a, $c ) );
		$this->assertNull( $this->find_group_containing( $groups, $b, $c ) );
	}

	/**
	 * A mix of exact + perceptual variants must produce two distinct groups.
	 *
	 * @return void
	 */
	public function test_mixed_group_types(): void {
		list( $exact_a, $exact_b ) = $this->attach_fixture_twice( 3 );
		$perc_orig                 = $this->attach_fixture( 4 );
		$perc_blur                 = $this->attach_variant( 4, 'blur' );

		foreach ( array( $exact_a, $exact_b, $perc_orig, $perc_blur ) as $id ) {
			$this->assertTrue( $this->indexer->index_single( $id ) );
		}

		$this->scanner->run();
		$groups = $this->scanner->get_results();

		$exact_group = $this->find_group_containing( $groups, $exact_a, $exact_b );
		$this->assertNotNull( $exact_group );
		$this->assertSame( 'exact', $exact_group['match_type'] );

		$perc_group = $this->find_group_containing( $groups, $perc_orig, $perc_blur );
		$this->assertNotNull( $perc_group );
		$this->assertSame( 'perceptual', $perc_group['match_type'] );
	}

	/**
	 * Three byte-identical copies must collapse into a single 3-member group.
	 *
	 * @return void
	 */
	public function test_three_copies_collapse_to_one_group(): void {
		$a = $this->attach_fixture( 5, 'a' );
		$b = $this->attach_fixture( 5, 'b' );
		$c = $this->attach_fixture( 5, 'c' );

		foreach ( array( $a, $b, $c ) as $id ) {
			$this->assertTrue( $this->indexer->index_single( $id ) );
		}

		$this->scanner->run();
		$groups = $this->scanner->get_results();

		$g = $this->find_group_containing( $groups, $a, $b, $c );
		$this->assertNotNull( $g );
		$this->assertSame( 'exact', $g['match_type'] );
		$this->assertCount( 3, $g['ids'] );
	}

	/**
	 * After a successful scan, `get_last_scanned` is populated and progress
	 * reaches `done`.
	 *
	 * @return void
	 */
	public function test_scanner_marks_done_after_run(): void {
		list( $a, $b ) = $this->attach_fixture_twice( 6 );
		$this->indexer->index_single( $a );
		$this->indexer->index_single( $b );

		$this->scanner->run();

		$this->assertNotEmpty( $this->scanner->get_last_scanned() );
		$this->assertSame( 'done', ( new Duplicate_Progress() )->get()['status'] );
	}
}
