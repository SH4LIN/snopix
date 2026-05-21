<?php
/**
 * Tests for Attachment_Query keyset pagination + search helpers.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Infrastructure\Attachment_Query;

/**
 * Attachment_Query unit tests.
 */
class Pixel_Scout_Attachment_Query_Test extends Pixel_Scout_TestCase {

	/**
	 * Spawn an image attachment for fixture data.
	 *
	 * @param string $title Optional title.
	 * @param string $mime  MIME type.
	 *
	 * @return int Attachment ID.
	 */
	private function make_attachment( string $title = '', string $mime = 'image/jpeg' ): int {
		return (int) self::factory()->attachment->create(
			array(
				'post_title'     => $title,
				'post_mime_type' => $mime,
			)
		);
	}

	/**
	 * `count` totals only image-MIME attachments.
	 *
	 * @return void
	 */
	public function test_count_includes_image_attachments(): void {
		$before = Attachment_Query::count();
		$this->make_attachment();
		$this->make_attachment();
		$this->assertSame( $before + 2, Attachment_Query::count() );
	}

	/**
	 * `count` ignores non-image attachments.
	 *
	 * @return void
	 */
	public function test_count_excludes_non_image_attachments(): void {
		$before = Attachment_Query::count();
		$this->make_attachment( '', 'application/pdf' );
		$this->assertSame( $before, Attachment_Query::count() );
	}

	/**
	 * `get_ids` returns IDs in ascending order, bounded by limit.
	 *
	 * @return void
	 */
	public function test_get_ids_returns_sorted_limited(): void {
		$a = $this->make_attachment();
		$b = $this->make_attachment();
		$c = $this->make_attachment();

		$ids = Attachment_Query::get_ids( 0, 100 );
		$this->assertContains( $a, $ids );
		$this->assertContains( $b, $ids );
		$this->assertContains( $c, $ids );

		$limited = Attachment_Query::get_ids( 0, 2 );
		$this->assertLessThanOrEqual( 2, count( $limited ) );
	}

	/**
	 * `get_ids` with an `after_id` returns only IDs strictly greater.
	 *
	 * @return void
	 */
	public function test_get_ids_after_cursor_excludes_lower_ids(): void {
		$a   = $this->make_attachment();
		$b   = $this->make_attachment();
		$ids = Attachment_Query::get_ids( $a, 100 );
		$this->assertNotContains( $a, $ids );
		$this->assertContains( $b, $ids );
	}

	/**
	 * `get_all_ids` returns every image attachment regardless of count.
	 *
	 * @return void
	 */
	public function test_get_all_ids_returns_every_image(): void {
		$ids = array(
			$this->make_attachment(),
			$this->make_attachment(),
			$this->make_attachment(),
		);

		$all = Attachment_Query::get_all_ids();
		foreach ( $ids as $id ) {
			$this->assertContains( $id, $all );
		}
	}

	/**
	 * `search_ids` matches by post title.
	 *
	 * @return void
	 */
	public function test_search_ids_finds_by_title(): void {
		$match = $this->make_attachment( 'Sunset Beach Photo' );
		$miss  = $this->make_attachment( 'Mountain Landscape' );

		$ids = Attachment_Query::search_ids( 'Sunset' );
		$this->assertContains( $match, $ids );
		$this->assertNotContains( $miss, $ids );
	}

	/**
	 * Empty search term returns title-search results (matches the empty `s`).
	 * The function does not error on empty input.
	 *
	 * @return void
	 */
	public function test_search_ids_with_empty_string_does_not_error(): void {
		$ids = Attachment_Query::search_ids( '' );
		$this->assertIsArray( $ids );
	}
}
