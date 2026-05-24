<?php
/**
 * Tests for Subsize_Watcher snapshot/diff.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Imaging\Subsize_Watcher;

/**
 * Subsize_Watcher unit tests.
 */
class Pixel_Scout_Subsize_Watcher_Test extends Pixel_Scout_TestCase {

	public function setUp(): void {
		parent::setUp();
		delete_option( Subsize_Watcher::OPTION_KEY );
	}

	public function tearDown(): void {
		delete_option( Subsize_Watcher::OPTION_KEY );
		parent::tearDown();
	}

	public function test_first_diff_seeds_snapshot_and_returns_empty(): void {
		$watcher = new Subsize_Watcher();
		$diff    = $watcher->diff();

		$this->assertSame( array(), $diff['new'] );
		$this->assertSame( array(), $diff['removed'] );
		$this->assertSame( array(), $diff['changed'] );
		$this->assertFalse( $diff['has_changes'] );
		$this->assertIsArray( get_option( Subsize_Watcher::OPTION_KEY ) );
	}

	public function test_added_size_is_detected_as_new(): void {
		$watcher = new Subsize_Watcher();
		$watcher->diff();

		add_image_size( 'ps_test_new', 999, 999, true );

		$diff = $watcher->diff();
		$this->assertContains( 'ps_test_new', $diff['new'] );
		$this->assertTrue( $diff['has_changes'] );

		remove_image_size( 'ps_test_new' );
	}

	public function test_removed_size_is_detected(): void {
		add_image_size( 'ps_test_gone', 100, 100, false );
		$watcher = new Subsize_Watcher();
		$watcher->diff();

		remove_image_size( 'ps_test_gone' );

		$diff = $watcher->diff();
		$this->assertContains( 'ps_test_gone', $diff['removed'] );
		$this->assertTrue( $diff['has_changes'] );
	}

	public function test_dim_change_is_detected_as_changed(): void {
		add_image_size( 'ps_test_resize', 100, 100, false );
		$watcher = new Subsize_Watcher();
		$watcher->diff();

		remove_image_size( 'ps_test_resize' );
		add_image_size( 'ps_test_resize', 200, 200, true );

		$diff = $watcher->diff();
		$this->assertCount( 1, $diff['changed'] );
		$this->assertSame( 'ps_test_resize', $diff['changed'][0]['name'] );
		$this->assertSame( array( 'w' => 100, 'h' => 100, 'crop' => false ), $diff['changed'][0]['old'] );
		$this->assertSame( array( 'w' => 200, 'h' => 200, 'crop' => true ), $diff['changed'][0]['new'] );

		remove_image_size( 'ps_test_resize' );
	}

	public function test_acknowledge_writes_current_snapshot(): void {
		$watcher = new Subsize_Watcher();
		$watcher->diff();

		add_image_size( 'ps_test_ack', 500, 500, true );
		$this->assertTrue( $watcher->diff()['has_changes'] );

		$this->assertTrue( $watcher->acknowledge() );
		$this->assertFalse( $watcher->diff()['has_changes'] );

		remove_image_size( 'ps_test_ack' );
	}

	public function test_zero_dimension_sizes_are_filtered_out(): void {
		add_image_size( 'ps_test_zero', 0, 100, false );
		$watcher = new Subsize_Watcher();
		$watcher->diff();

		$this->assertNotContains( 'ps_test_zero', array_keys( get_option( Subsize_Watcher::OPTION_KEY, array() ) ) );

		remove_image_size( 'ps_test_zero' );
	}
}
