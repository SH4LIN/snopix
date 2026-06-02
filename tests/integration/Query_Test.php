<?php
/**
 * Integration tests for Snopix\Infrastructure\Query.
 *
 * Exercises the fluent builder against the real (bootstrap-created)
 * wp_snopix_index table. Row DML is rolled back per test by WP_UnitTestCase.
 *
 * @package Snopix
 */

use Snopix\Infrastructure\Query;

/**
 * @covers \Snopix\Infrastructure\Query
 */
final class Query_Test extends Snopix_Integration_TestCase {

	/**
	 * Fully-qualified plugin table name.
	 *
	 * @var string
	 */
	private string $table;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$this->table = $wpdb->prefix . 'snopix_index';
	}

	/**
	 * Insert a single index row through the builder.
	 *
	 * @param int    $attachment_id Attachment id.
	 * @param string $phash         16-char perceptual hash.
	 *
	 * @return int|false Insert id or false.
	 */
	private function insert_row( int $attachment_id, string $phash = 'a1b2c3d4e5f60718' ) {
		return Query::create()
			->from( 'snopix_index' )
			->insert(
				array(
					'attachment_id' => $attachment_id,
					'phash'         => $phash,
					'file_hash'     => str_repeat( 'a', 32 ),
				)
			);
	}

	public function test_from_prefixes_bare_table_name(): void {
		$sql = Query::create()->from( 'snopix_index' )->build_sql();

		// resolve_table_name() prepends $wpdb->prefix to the bare name.
		$this->assertStringContainsString( 'FROM ' . $this->table, $sql );
	}

	public function test_insert_then_select_round_trip(): void {
		$insert_id = $this->insert_row( 4242, 'ffffffffffffffff' );
		$this->assertIsInt( $insert_id );
		$this->assertGreaterThan( 0, $insert_id );

		$rows = Query::create()
			->from( 'snopix_index' )
			->where( 'attachment_id', 4242, '=', '%d' )
			->get();

		$this->assertIsArray( $rows );
		$this->assertCount( 1, $rows );
		$this->assertSame( 4242, (int) $rows[0]['attachment_id'] );
		$this->assertSame( 'ffffffffffffffff', $rows[0]['phash'] );
	}

	public function test_where_in_order_by_and_limit(): void {
		$this->insert_row( 10 );
		$this->insert_row( 20 );
		$this->insert_row( 30 );

		$rows = Query::create()
			->from( 'snopix_index' )
			->select( array( 'attachment_id' ) )
			->where_in( 'attachment_id', array( 10, 20, 30 ) )
			->order_by( 'attachment_id', 'DESC' )
			->limit( 2 )
			->get();

		$this->assertIsArray( $rows );
		$this->assertCount( 2, $rows );
		$this->assertSame( 30, (int) $rows[0]['attachment_id'] );
		$this->assertSame( 20, (int) $rows[1]['attachment_id'] );
	}

	public function test_get_var_returns_count(): void {
		$this->insert_row( 11 );
		$this->insert_row( 22 );

		$count = Query::create()
			->from( 'snopix_index' )
			->select( 'COUNT(*)' )
			->get_var();

		$this->assertSame( 2, (int) $count );
	}

	public function test_update_requires_where_and_modifies_matched_row(): void {
		$this->insert_row( 77, 'aaaaaaaaaaaaaaaa' );

		$affected = Query::create()
			->from( 'snopix_index' )
			->where( 'attachment_id', 77, '=', '%d' )
			->update( array( 'phash' => 'bbbbbbbbbbbbbbbb' ) );

		$this->assertSame( 1, $affected );

		$phash = Query::create()
			->from( 'snopix_index' )
			->select( 'phash' )
			->where( 'attachment_id', 77, '=', '%d' )
			->get_var();

		$this->assertSame( 'bbbbbbbbbbbbbbbb', $phash );
	}

	public function test_update_without_where_returns_false(): void {
		$this->insert_row( 88 );

		$result = Query::create()
			->from( 'snopix_index' )
			->update( array( 'phash' => 'cccccccccccccccc' ) );

		$this->assertFalse( $result );
	}

	public function test_delete_requires_where_and_removes_matched_row(): void {
		$this->insert_row( 91 );
		$this->insert_row( 92 );

		$deleted = Query::create()
			->from( 'snopix_index' )
			->where( 'attachment_id', 91, '=', '%d' )
			->delete();

		$this->assertSame( 1, $deleted );

		$remaining = Query::create()
			->from( 'snopix_index' )
			->select( 'COUNT(*)' )
			->get_var();

		$this->assertSame( 1, (int) $remaining );
	}

	public function test_delete_without_where_returns_false(): void {
		$this->insert_row( 93 );

		$result = Query::create()->from( 'snopix_index' )->delete();

		$this->assertFalse( $result );

		// The guard prevented an unbounded delete; the row survives.
		$count = Query::create()
			->from( 'snopix_index' )
			->select( 'COUNT(*)' )
			->get_var();

		$this->assertSame( 1, (int) $count );
	}

	public function test_truncate_empties_the_table(): void {
		$this->insert_row( 101 );
		$this->insert_row( 102 );

		$result = Query::create()->from( 'snopix_index' )->truncate();
		$this->assertNotFalse( $result );

		$count = Query::create()
			->from( 'snopix_index' )
			->select( 'COUNT(*)' )
			->get_var();

		$this->assertSame( 0, (int) $count );
	}

	public function test_upsert_inserts_then_updates_existing_row_on_unique_key(): void {
		// First upsert inserts a brand new row.
		$first = Query::create()
			->from( 'snopix_index' )
			->upsert(
				array(
					'attachment_id' => 555,
					'phash'         => '1111111111111111',
					'file_hash'     => str_repeat( 'd', 32 ),
				),
				array( 'phash', 'file_hash' )
			);
		$this->assertTrue( $first );

		// Second upsert collides on the unique attachment_id key and updates.
		$second = Query::create()
			->from( 'snopix_index' )
			->upsert(
				array(
					'attachment_id' => 555,
					'phash'         => '2222222222222222',
					'file_hash'     => str_repeat( 'e', 32 ),
				),
				array( 'phash', 'file_hash' )
			);
		$this->assertTrue( $second );

		// Still exactly one row for that attachment_id, with updated values.
		$count = Query::create()
			->from( 'snopix_index' )
			->select( 'COUNT(*)' )
			->where( 'attachment_id', 555, '=', '%d' )
			->get_var();
		$this->assertSame( 1, (int) $count );

		$row = Query::create()
			->from( 'snopix_index' )
			->where( 'attachment_id', 555, '=', '%d' )
			->get_row();

		$this->assertIsArray( $row );
		$this->assertSame( '2222222222222222', $row['phash'] );
		$this->assertSame( str_repeat( 'e', 32 ), $row['file_hash'] );
	}
}
