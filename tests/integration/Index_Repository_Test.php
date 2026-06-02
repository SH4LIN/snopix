<?php
/**
 * Integration tests for Snopix\Repository\Index_Repository.
 *
 * @package Snopix
 */

use Snopix\Repository\Index_Repository;

/**
 * @covers \Snopix\Repository\Index_Repository
 */
final class Index_Repository_Test extends Snopix_Integration_TestCase {

	private Index_Repository $repo;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$this->repo = new Index_Repository( $wpdb );
	}

	public function test_index_table_exists(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'snopix_index';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertSame( $table, $found );
	}

	public function test_upsert_then_get_all_indexed_round_trip(): void {
		$ok = $this->repo->upsert(
			4242,
			array(
				'phash'        => 'a1b2c3d4e5f60718',
				'color_vector' => array( 0.6, 0.4 ),
				'edge_vector'  => array( 1.0, 2.0 ),
				'file_hash'    => str_repeat( 'a', 32 ),
			)
		);
		$this->assertTrue( $ok );

		$rows = $this->repo->get_all_indexed();
		$this->assertCount( 1, $rows );
		$this->assertSame( 4242, (int) $rows[0]['attachment_id'] );
		$this->assertSame( 'a1b2c3d4e5f60718', $rows[0]['phash'] );
	}
}
