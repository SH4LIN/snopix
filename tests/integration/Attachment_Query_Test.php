<?php
/**
 * Integration tests for Snopix\Infrastructure\Attachment_Query.
 *
 * @package Snopix
 */

use Snopix\Infrastructure\Attachment_Query;

/**
 * @covers \Snopix\Infrastructure\Attachment_Query
 */
final class Attachment_Query_Test extends Snopix_Integration_TestCase {

	/**
	 * count() tallies only image attachments via wp_count_attachments().
	 */
	public function test_count_reflects_attached_image_fixtures(): void {
		// Note: wp_count_attachments() caches per request, so count() must not
		// be called before the attachments exist or it pins a stale zero.
		$this->attach_fixture( 1 );
		$this->attach_fixture( 2 );
		$this->attach_fixture( 3 );

		$this->assertSame( 3, Attachment_Query::count() );
	}

	/**
	 * count() excludes non-image attachments.
	 */
	public function test_count_excludes_non_image_attachments(): void {
		$this->attach_fixture( 1 );

		$non_image = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			)
		);
		$this->assertGreaterThan( 0, $non_image );

		$this->assertSame( 1, Attachment_Query::count() );
	}

	/**
	 * get_ids() returns only image attachment IDs, ordered by ID ASC.
	 */
	public function test_get_ids_returns_image_ids_in_ascending_order(): void {
		$a = $this->attach_fixture( 1 );
		$b = $this->attach_fixture( 2 );
		$c = $this->attach_fixture( 3 );

		self::factory()->attachment->create(
			array(
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			)
		);

		$ids = Attachment_Query::get_ids();

		$this->assertSame( array( $a, $b, $c ), $ids );
	}

	/**
	 * get_ids() honours the keyset cursor and per-page limit.
	 */
	public function test_get_ids_keyset_pagination(): void {
		$a = $this->attach_fixture( 1 );
		$b = $this->attach_fixture( 2 );
		$c = $this->attach_fixture( 3 );

		$first = Attachment_Query::get_ids( 0, 2 );
		$this->assertSame( array( $a, $b ), $first );

		$cursor = (int) end( $first );
		$next   = Attachment_Query::get_ids( $cursor, 2 );
		$this->assertSame( array( $c ), $next );

		$this->assertSame( array(), Attachment_Query::get_ids( $c, 2 ) );
	}

	/**
	 * get_all_ids() returns every image attachment ID across batches.
	 */
	public function test_get_all_ids_returns_every_image_id(): void {
		$a = $this->attach_fixture( 1 );
		$b = $this->attach_fixture( 2 );

		$this->assertSame( array( $a, $b ), Attachment_Query::get_all_ids() );
	}

	/**
	 * search_ids() matches by post title.
	 */
	public function test_search_ids_matches_by_title(): void {
		$match = self::factory()->attachment->create(
			array(
				'post_title'     => 'Unique Sunset Photo',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			)
		);
		$other = self::factory()->attachment->create(
			array(
				'post_title'     => 'Mountain Range',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			)
		);

		$ids = Attachment_Query::search_ids( 'Sunset' );

		$this->assertContains( $match, $ids );
		$this->assertNotContains( $other, $ids );
	}

	/**
	 * search_ids() matches by attached filename via _wp_attached_file.
	 */
	public function test_search_ids_matches_by_filename(): void {
		$id = $this->attach_fixture( 7 );

		$file = get_post_meta( $id, '_wp_attached_file', true );
		$this->assertNotEmpty( $file );
		$term = pathinfo( (string) $file, PATHINFO_FILENAME );

		$ids = Attachment_Query::search_ids( $term );

		$this->assertContains( $id, $ids );
	}

	/**
	 * search_ids() returns a unique ID list with no duplicates.
	 */
	public function test_search_ids_returns_unique_ids(): void {
		$id = self::factory()->attachment->create(
			array(
				'post_title'     => 'Snopix Special',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			)
		);
		update_post_meta( $id, '_wp_attached_file', '2026/06/Snopix-Special.jpg' );

		$ids = Attachment_Query::search_ids( 'Snopix' );

		$this->assertSame( array_values( array_unique( $ids ) ), $ids );
		$this->assertContains( $id, $ids );
	}
}
