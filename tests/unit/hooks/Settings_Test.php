<?php
/**
 * Tests for Settings sanitisation.
 *
 * @package Pixel_Scout
 */

require_once dirname( __DIR__ ) . '/class-testcase.php';

use PixelScout\Hooks\Settings;

/**
 * Settings unit tests.
 */
class Pixel_Scout_Settings_Test extends Pixel_Scout_TestCase {

	private Settings $settings;

	/**
	 * Reset option + build fresh instance.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		delete_option( 'ps_settings' );
		$this->settings = new Settings();
	}

	/**
	 * Drop persisted option after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( 'ps_settings' );
		parent::tearDown();
	}

	/**
	 * Sanitise allows known values.
	 *
	 * @return void
	 */
	public function test_sanitize_accepts_anyone(): void {
		$out = $this->settings->sanitize( array( 'search_visibility' => 'anyone' ) );
		$this->assertSame( 'anyone', $out['search_visibility'] );
	}

	/**
	 * Sanitise allows logged_in.
	 *
	 * @return void
	 */
	public function test_sanitize_accepts_logged_in(): void {
		$out = $this->settings->sanitize( array( 'search_visibility' => 'logged_in' ) );
		$this->assertSame( 'logged_in', $out['search_visibility'] );
	}

	/**
	 * Sanitise rejects unknown values, falling back to anyone.
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_unknown_value(): void {
		$out = $this->settings->sanitize( array( 'search_visibility' => 'admins_only' ) );
		$this->assertSame( 'anyone', $out['search_visibility'] );
	}

	/**
	 * Sanitise falls back to anyone when key is missing.
	 *
	 * @return void
	 */
	public function test_sanitize_defaults_when_key_missing(): void {
		$out = $this->settings->sanitize( array() );
		$this->assertSame( 'anyone', $out['search_visibility'] );
	}

	/**
	 * `get_visibility` reads from the persisted option.
	 *
	 * @return void
	 */
	public function test_get_visibility_reads_option(): void {
		update_option( 'ps_settings', array( 'search_visibility' => 'logged_in' ) );
		$this->assertSame( 'logged_in', $this->settings->get_visibility() );
	}

	/**
	 * `get_visibility` falls back to anyone when option missing.
	 *
	 * @return void
	 */
	public function test_get_visibility_defaults_to_anyone(): void {
		$this->assertSame( 'anyone', $this->settings->get_visibility() );
	}
}
