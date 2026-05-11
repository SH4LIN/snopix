<?php
/**
 * Tests for Pixel_Scout_Index_Repository data access layer.
 *
 * @package Pixel_Scout
 */

require_once __DIR__ . '/../class-testcase.php';

/**
 * Test Repository implementation.
 */
class Pixel_Scout_Index_Repository_Test extends Pixel_Scout_TestCase {
	/**
	 * Repository instance.
	 *
	 * @var Pixel_Scout_Index_Repository
	 */
	private Pixel_Scout_Index_Repository $repo;

	/**
	 * Test attachment ID.
	 */
	private const TEST_ATTACHMENT_ID = 12345;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->repo = new Pixel_Scout_Index_Repository();
		new Pixel_Scout_Schema(); // Ensure table exists.
		( new Pixel_Scout_Schema() )->install();
	}

	/**
	 * Get test fingerprint payload.
	 *
	 * @param int $attachment_id Optional attachment ID.
	 *
	 * @return array<string, mixed>
	 */
	private function get_test_fingerprint( int $attachment_id = self::TEST_ATTACHMENT_ID ): array {
		return [
			'phash'         => 'a1b2c3d4e5f6a1b2',
			'color_vector'  => wp_json_encode( array_fill( 0, 48, 0.5 ) ),
			'edge_vector'   => wp_json_encode( array_fill( 0, 32, 0.3 ) ),
			'width'         => 1920,
			'height'        => 1080,
			'mime_type'     => 'image/jpeg',
			'file_size'     => 512000,
		];
	}

	/**
	 * Test upsert inserts new row.
	 */
	public function test_upsert_inserts_new_row(): void {
		$result = $this->repo->upsert( self::TEST_ATTACHMENT_ID, $this->get_test_fingerprint() );
		$this->assertTrue( $result );

		// Verify row exists.
		$rows = $this->repo->get_all_indexed();
		$ids  = wp_list_pluck( $rows, 'attachment_id' );
		$this->assertContains( self::TEST_ATTACHMENT_ID, $ids );
	}

	/**
	 * Test upsert updates existing row.
	 */
	public function test_upsert_updates_existing_row(): void {
		// Insert first time.
		$this->repo->upsert( self::TEST_ATTACHMENT_ID, $this->get_test_fingerprint() );

		// Update with new phash.
		$updated = $this->get_test_fingerprint();
		$updated['phash'] = 'ffffffffffffffff';

		$result = $this->repo->upsert( self::TEST_ATTACHMENT_ID, $updated );
		$this->assertTrue( $result );

		// Verify update.
		$rows = $this->repo->get_all_indexed();
		$row  = wp_list_filter( $rows, [ 'attachment_id' => self::TEST_ATTACHMENT_ID ], 'AND' );
		$row  = array_shift( $row );

		$this->assertEquals( 'ffffffffffffffff', $row['phash'] );
	}

	/**
	 * Test get_all_indexed returns all rows.
	 */
	public function test_get_all_indexed(): void {
		$fp1 = [
			'attachment_id' => 100,
			'phash'         => 'aaaaaaaaaaaaaaaa',
		];
		$fp2 = [
			'attachment_id' => 200,
			'phash'         => 'bbbbbbbbbbbbbbbb',
		];

		$this->repo->upsert( 100, $fp1 );
		$this->repo->upsert( 200, $fp2 );

		$rows = $this->repo->get_all_indexed();
		$this->assertGreaterThanOrEqual( 2, count( $rows ) );

		$ids = wp_list_pluck( $rows, 'attachment_id' );
		$this->assertContains( 100, $ids );
		$this->assertContains( 200, $ids );
	}

	/**
	 * Test get_all_indexed is cached.
	 */
	public function test_get_all_indexed_caches_result(): void {
		$this->repo->upsert( self::TEST_ATTACHMENT_ID, $this->get_test_fingerprint() );

		// First call.
		$result1 = $this->repo->get_all_indexed();

		// Check cache was set.
		$cached = wp_cache_get( 'ps_all_indexed', 'pixel-scout' );
		$this->assertNotFalse( $cached );
		$this->assertTrue( is_array( $cached ) );
	}

	/**
	 * Test get_counts returns correct counts.
	 */
	public function test_get_counts(): void {
		// Add test posts.
		$post1_id = self::factory()->attachment->create();
		$post2_id = self::factory()->attachment->create();

		// Index only first one.
		$this->repo->upsert( $post1_id, $this->get_test_fingerprint() );

		$counts = $this->repo->get_counts();

		$this->assertIsArray( $counts );
		$this->assertArrayHasKey( 'total', $counts );
		$this->assertArrayHasKey( 'indexed', $counts );
		$this->assertArrayHasKey( 'pending', $counts );

		$this->assertGreaterThanOrEqual( 2, $counts['total'] );
		$this->assertGreaterThanOrEqual( 1, $counts['indexed'] );
		$this->assertGreaterThanOrEqual( 1, $counts['pending'] );
	}

	/**
	 * Test get_unindexed_ids returns unindexed attachments.
	 */
	public function test_get_unindexed_ids(): void {
		$indexed_id   = self::factory()->attachment->create();
		$unindexed_id = self::factory()->attachment->create();

		// Index first one.
		$this->repo->upsert( $indexed_id, $this->get_test_fingerprint() );

		$unindexed = $this->repo->get_unindexed_ids();

		$this->assertIsArray( $unindexed );
		$this->assertContains( $unindexed_id, $unindexed );
		$this->assertNotContains( $indexed_id, $unindexed );
	}

	/**
	 * Test delete removes row.
	 */
	public function test_delete_removes_row(): void {
		$this->repo->upsert( self::TEST_ATTACHMENT_ID, $this->get_test_fingerprint() );

		$result = $this->repo->delete( self::TEST_ATTACHMENT_ID );
		$this->assertTrue( $result );

		// Verify removed.
		$rows = $this->repo->get_all_indexed();
		$ids  = wp_list_pluck( $rows, 'attachment_id' );
		$this->assertNotContains( self::TEST_ATTACHMENT_ID, $ids );
	}

	/**
	 * Test delete invalidates cache.
	 */
	public function test_delete_invalidates_cache(): void {
		$this->repo->upsert( self::TEST_ATTACHMENT_ID, $this->get_test_fingerprint() );
		$this->repo->get_all_indexed(); // Populate cache.

		$this->repo->delete( self::TEST_ATTACHMENT_ID );

		// Cache should be cleared.
		$cached = wp_cache_get( 'ps_all_indexed', 'pixel-scout' );
		$this->assertFalse( $cached );
	}

	/**
	 * Test get_paginated returns correct page.
	 */
	public function test_get_paginated(): void {
		// Insert several rows.
		for ( $i = 1; $i <= 5; $i++ ) {
			$fp = $this->get_test_fingerprint();
			$this->repo->upsert( $i, $fp );
		}

		// Get page 1 (2 per page).
		$page1 = $this->repo->get_paginated( 1, 2, '' );
		$this->assertLessThanOrEqual( 2, count( $page1 ) );

		// Get page 2 (2 per page).
		$page2 = $this->repo->get_paginated( 2, 2, '' );
		$this->assertLessThanOrEqual( 2, count( $page2 ) );
	}

	/**
	 * Test get_paginated with search.
	 */
	public function test_get_paginated_with_search(): void {
		// Create attachment with known title.
		$post_id = self::factory()->attachment->create(
			[
				'post_title'     => 'Test Image Alpha',
				'post_mime_type' => 'image/jpeg',
			]
		);

		$this->repo->upsert( $post_id, $this->get_test_fingerprint() );

		$results = $this->repo->get_paginated( 1, 10, 'Alpha' );
		$this->assertGreaterThanOrEqual( 1, count( $results ) );
	}

	/**
	 * Test upsert invalidates cache.
	 */
	public function test_upsert_invalidates_cache(): void {
		$this->repo->upsert( self::TEST_ATTACHMENT_ID, $this->get_test_fingerprint() );
		$this->repo->get_all_indexed(); // Populate cache.

		// Upsert again with different data.
		$this->repo->upsert( self::TEST_ATTACHMENT_ID + 1, $this->get_test_fingerprint() );

		// Cache should be cleared.
		$cached = wp_cache_get( 'ps_all_indexed', 'pixel-scout' );
		$this->assertFalse( $cached );
	}

	/**
	 * Test concurrent upserts don't conflict.
	 */
	public function test_concurrent_upserts(): void {
		for ( $i = 1; $i <= 10; $i++ ) {
			$fp = $this->get_test_fingerprint();
			$this->repo->upsert( $i, $fp );
		}

		$rows = $this->repo->get_all_indexed();
		$this->assertGreaterThanOrEqual( 10, count( $rows ) );
	}

	/**
	 * Test upsert with empty fingerprint fields.
	 */
	public function test_upsert_with_partial_fingerprint(): void {
		$partial_fp = [
			'phash'    => 'a1b2c3d4e5f6a1b2',
			'mime_type' => 'image/jpeg',
		];

		$result = $this->repo->upsert( self::TEST_ATTACHMENT_ID, $partial_fp );
		$this->assertTrue( $result );

		$rows = $this->repo->get_all_indexed();
		$row  = wp_list_filter( $rows, [ 'attachment_id' => self::TEST_ATTACHMENT_ID ] );
		$this->assertNotEmpty( $row );
	}
}

