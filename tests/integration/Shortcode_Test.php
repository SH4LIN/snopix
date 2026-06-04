<?php
/**
 * Integration tests for Snopix\Frontend\Shortcode.
 *
 * @package Snopix
 */

use Snopix\Frontend\Shortcode;

/**
 * @covers \Snopix\Frontend\Shortcode
 */
final class Shortcode_Test extends Snopix_Integration_TestCase {

	private Shortcode $shortcode;

	public function set_up(): void {
		parent::set_up();
		// Dequeue and deregister shortcode assets so each test starts clean,
		// regardless of what prior tests enqueued in the same process.
		wp_dequeue_style( 'snopix-search' );
		wp_deregister_style( 'snopix-search' );
		wp_dequeue_script( 'snopix-search' );
		wp_deregister_script( 'snopix-search' );
		$this->shortcode = new Shortcode();
		$this->shortcode->register();
	}

	public function tear_down(): void {
		remove_shortcode( 'snopix_search' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function test_shortcode_is_registered(): void {
		$this->assertTrue( shortcode_exists( 'snopix_search' ) );
	}

	// -------------------------------------------------------------------------
	// Markup - mount point
	// -------------------------------------------------------------------------

	public function test_render_returns_div_with_data_attribute(): void {
		$html = do_shortcode( '[snopix_search]' );
		$this->assertStringContainsString( 'data-snopix-search', $html );
		$this->assertStringContainsString( '<div ', $html );
	}

	public function test_render_id_is_prefixed_snopix_search(): void {
		$html = do_shortcode( '[snopix_search]' );
		$this->assertMatchesRegularExpression( '/id="snopix-search-\d+"/', $html );
	}

	public function test_render_id_increments_on_each_call(): void {
		$html1 = do_shortcode( '[snopix_search]' );
		$html2 = do_shortcode( '[snopix_search]' );

		preg_match( '/id="snopix-search-(\d+)"/', $html1, $m1 );
		preg_match( '/id="snopix-search-(\d+)"/', $html2, $m2 );

		$this->assertNotEmpty( $m1 );
		$this->assertNotEmpty( $m2 );
		$this->assertGreaterThan( (int) $m1[1], (int) $m2[1] );
	}

	// -------------------------------------------------------------------------
	// Default attributes
	// -------------------------------------------------------------------------

	public function test_default_variant_is_card(): void {
		$html = do_shortcode( '[snopix_search]' );
		$this->assertStringContainsString( 'data-variant="card"', $html );
	}

	public function test_default_title_is_search_by_image(): void {
		$html = do_shortcode( '[snopix_search]' );
		$this->assertStringContainsString( 'data-title="Search by image"', $html );
	}

	public function test_default_max_results_is_12(): void {
		$html = do_shortcode( '[snopix_search]' );
		$this->assertStringContainsString( 'data-max-results="12"', $html );
	}

	// -------------------------------------------------------------------------
	// variant attribute
	// -------------------------------------------------------------------------

	public function test_variant_inline_is_honoured(): void {
		$html = do_shortcode( '[snopix_search variant="inline"]' );
		$this->assertStringContainsString( 'data-variant="inline"', $html );
	}

	public function test_variant_narrow_is_honoured(): void {
		$html = do_shortcode( '[snopix_search variant="narrow"]' );
		$this->assertStringContainsString( 'data-variant="narrow"', $html );
	}

	public function test_unknown_variant_falls_back_to_card(): void {
		$html = do_shortcode( '[snopix_search variant="bogus"]' );
		$this->assertStringContainsString( 'data-variant="card"', $html );
	}

	// -------------------------------------------------------------------------
	// title attribute
	// -------------------------------------------------------------------------

	public function test_custom_title_is_honoured(): void {
		$html = do_shortcode( '[snopix_search title="Find similar"]' );
		$this->assertStringContainsString( 'data-title="Find similar"', $html );
	}

	public function test_title_is_escaped_in_output(): void {
		$html = do_shortcode( '[snopix_search title="A & B"]' );
		// esc_attr encodes & as &amp;
		$this->assertStringContainsString( 'data-title="A &amp; B"', $html );
	}

	// -------------------------------------------------------------------------
	// max_results attribute - clamping
	// -------------------------------------------------------------------------

	public function test_custom_max_results_is_honoured(): void {
		$html = do_shortcode( '[snopix_search max_results="24"]' );
		$this->assertStringContainsString( 'data-max-results="24"', $html );
	}

	public function test_max_results_clamped_to_minimum_1(): void {
		$html = do_shortcode( '[snopix_search max_results="0"]' );
		$this->assertStringContainsString( 'data-max-results="1"', $html );
	}

	public function test_max_results_clamped_to_maximum_48(): void {
		$html = do_shortcode( '[snopix_search max_results="999"]' );
		$this->assertStringContainsString( 'data-max-results="48"', $html );
	}

	public function test_negative_max_results_clamped_to_1(): void {
		$html = do_shortcode( '[snopix_search max_results="-5"]' );
		$this->assertStringContainsString( 'data-max-results="1"', $html );
	}

	// -------------------------------------------------------------------------
	// Asset enqueueing
	// -------------------------------------------------------------------------

	public function test_render_enqueues_stylesheet(): void {
		do_shortcode( '[snopix_search]' );
		$this->assertTrue( wp_style_is( 'snopix-search', 'enqueued' ) );
	}

	public function test_render_enqueues_script(): void {
		do_shortcode( '[snopix_search]' );
		$this->assertTrue( wp_script_is( 'snopix-search', 'enqueued' ) );
	}

	public function test_assets_not_enqueued_before_render(): void {
		// Fresh instance, register only - do not call do_shortcode.
		$fresh = new Shortcode();
		$fresh->register();

		$this->assertFalse( wp_style_is( 'snopix-search', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'snopix-search', 'enqueued' ) );
	}

	// -------------------------------------------------------------------------
	// Output structure sanity
	// -------------------------------------------------------------------------

	public function test_output_is_a_single_root_element(): void {
		$html = do_shortcode( '[snopix_search]' );
		// Strip the one expected <div …></div> - nothing should remain.
		$stripped = trim( preg_replace( '/<div [^>]+><\/div>/', '', $html ) );
		$this->assertSame( '', $stripped );
	}

	public function test_multiple_shortcodes_render_independently(): void {
		$html1 = do_shortcode( '[snopix_search variant="card"]' );
		$html2 = do_shortcode( '[snopix_search variant="inline"]' );

		$this->assertStringContainsString( 'data-variant="card"', $html1 );
		$this->assertStringContainsString( 'data-variant="inline"', $html2 );

		// Unique IDs.
		preg_match( '/id="([^"]+)"/', $html1, $id1 );
		preg_match( '/id="([^"]+)"/', $html2, $id2 );
		$this->assertNotSame( $id1[1], $id2[1] );
	}
}
