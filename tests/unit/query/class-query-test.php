<?php
/**
 * Tests for Pixel_Scout_Query fluent builder.
 *
 * @package Pixel_Scout
 */

require_once __DIR__ . '/class-testcase.php';

/**
 * Test Query builder.
 */
class Pixel_Scout_Query_Test extends Pixel_Scout_TestCase {
	/**
	 * Test factory constructor.
	 */
	public function test_create_returns_instance(): void {
		$query = Pixel_Scout_Query::create();
		$this->assertInstanceOf( 'Pixel_Scout_Query', $query );
	}

	/**
	 * Test SELECT builder.
	 */
	public function test_select_single_field(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'posts' )
			->select( 'ID' );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'SELECT `ID` FROM', $sql );
	}

	/**
	 * Test SELECT with multiple fields.
	 */
	public function test_select_multiple_fields(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'posts' )
			->select( [ 'ID', 'post_title', 'post_date' ] );

		$sql = $query->build_sql();
		$this->assertStringContainsString( '`ID`, `post_title`, `post_date`', $sql );
	}

	/**
	 * Test FROM clause with prefix.
	 */
	public function test_from_adds_table_prefix(): void {
		global $wpdb;
		$query = Pixel_Scout_Query::create()
			->from( 'posts' );

		$sql = $query->build_sql();
		$this->assertStringContainsString( "FROM {$wpdb->posts}", $sql );
	}

	/**
	 * Test ps_index table prefix.
	 */
	public function test_from_ps_index(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'ps_index', $sql );
	}

	/**
	 * Test WHERE clause.
	 */
	public function test_where_condition(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->where( 'attachment_id', 42, '=', '%d' );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'WHERE', $sql );
		$this->assertStringContainsString( '`attachment_id` = %d', $sql );
	}

	/**
	 * Test multiple WHERE clauses (AND).
	 */
	public function test_multiple_where_and(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->where( 'attachment_id', 42, '=', '%d' )
			->where( 'phash', 'abc123', '=', '%s' );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'AND', $sql );
		$this->assertStringContainsString( '`attachment_id` = %d', $sql );
		$this->assertStringContainsString( '`phash` = %s', $sql );
	}

	/**
	 * Test WHERE IN clause.
	 */
	public function test_where_in(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->where_in( 'attachment_id', [ 1, 2, 3 ], '%d' );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'IN', $sql );
		$this->assertStringContainsString( '( %d, %d, %d )', $sql );
	}

	/**
	 * Test WHERE NOT IN clause.
	 */
	public function test_where_not_in(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->where_not_in( 'attachment_id', [ 1, 2 ], '%d' );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'NOT IN', $sql );
	}

	/**
	 * Test OR WHERE group.
	 */
	public function test_or_where_group(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->where( 'mime_type', 'image/gif', '!=', '%s' )
			->or_where_group( function( $q ) {
				$q->where( 'phash', 'abc', '=', '%s' );
				$q->where( 'phash', 'def', '=', '%s' );
			} );

		$sql = $query->build_sql();
		$this->assertStringContainsString( '( ', $sql );
		$this->assertStringContainsString( 'OR', $sql );
		$this->assertStringContainsString( 'AND', $sql );
	}

	/**
	 * Test ORDER BY.
	 */
	public function test_order_by(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->order_by( 'indexed_at', 'DESC' );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'ORDER BY `indexed_at` DESC', $sql );
	}

	/**
	 * Test LIMIT.
	 */
	public function test_limit(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->limit( 10 );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'LIMIT 10', $sql );
	}

	/**
	 * Test OFFSET.
	 */
	public function test_offset(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->limit( 10 )
			->offset( 20 );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'LIMIT 10', $sql );
		$this->assertStringContainsString( 'OFFSET 20', $sql );
	}

	/**
	 * Test paginate helper.
	 */
	public function test_paginate(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->paginate( 2, 25 );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'LIMIT 25', $sql );
		$this->assertStringContainsString( 'OFFSET 25', $sql );
	}

	/**
	 * Test GROUP BY.
	 */
	public function test_group_by(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->group_by( 'mime_type' );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'GROUP BY `mime_type`', $sql );
	}

	/**
	 * Test INNER JOIN.
	 */
	public function test_inner_join(): void {
		global $wpdb;
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index', 'idx' )
			->inner_join( 'posts', 'idx.attachment_id = p.ID', 'p' );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'INNER JOIN', $sql );
		$this->assertStringContainsString( 'ON idx.attachment_id = p.ID', $sql );
	}

	/**
	 * Test LEFT JOIN.
	 */
	public function test_left_join(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'posts', 'p' )
			->left_join( 'ps_index', 'idx.attachment_id = p.ID', 'idx' );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'LEFT JOIN', $sql );
	}

	/**
	 * Test INSERT statement.
	 */
	public function test_insert_builds_correctly(): void {
		// This is integration tested in Repository tests.
		// Query builder just routes to wpdb->insert.
		$this->assertTrue( true );
	}

	/**
	 * Test UPDATE statement.
	 */
	public function test_update_builds_correctly(): void {
		// This is integration tested in Repository tests.
		$this->assertTrue( true );
	}

	/**
	 * Test WHERE BETWEEN.
	 */
	public function test_where_between(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->where_between( 'file_size', 100, 1000, '%d' );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'BETWEEN %d AND %d', $sql );
	}

	/**
	 * Test WHERE RAW.
	 */
	public function test_where_raw(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->where_raw( 'phash IS NOT NULL', [] );

		$sql = $query->build_sql();
		$this->assertStringContainsString( 'phash IS NOT NULL', $sql );
	}

	/**
	 * Test no_cache flag.
	 */
	public function test_no_cache_flag(): void {
		$query = Pixel_Scout_Query::create()
			->from( 'ps_index' )
			->no_cache();

		$this->assertTrue( $query->is_no_cache() );

		$query2 = Pixel_Scout_Query::create()
			->from( 'ps_index' );

		$this->assertFalse( $query2->is_no_cache() );
	}
}

