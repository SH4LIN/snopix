<?php
/**
 * Integration tests for Snopix\Admin\Media_Surfaces.
 *
 * Covers the two concerns that gate this surface:
 *   - enqueue() only loads assets on the media/upload/post screens.
 *   - the Upload New Media toggle/panel markup is only output on media-new.php.
 *
 * @package Snopix
 */

use Snopix\Admin\Media_Surfaces;

/**
 * @covers \Snopix\Admin\Media_Surfaces
 */
final class Media_Surfaces_Test extends Snopix_Integration_TestCase {

	private Media_Surfaces $surfaces;

	private int $admin_user_id;

	public function set_up(): void {
		parent::set_up();

		$this->surfaces = new Media_Surfaces();

		// enqueue() is gated on capability-bearing admin screens; run as admin.
		$this->admin_user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );
	}

	public function tear_down(): void {
		// Drop anything the surface enqueued so it does not bleed across tests.
		foreach ( array( 'snopix-search', 'snopix-media' ) as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}

		unset( $GLOBALS['pagenow'] );

		parent::tear_down();
	}

	/**
	 * Assert that neither the widget nor the glue bundle is enqueued.
	 */
	private function assertNothingEnqueued( string $context ): void {
		foreach ( array( 'snopix-search', 'snopix-media' ) as $handle ) {
			$this->assertFalse(
				wp_style_is( $handle, 'enqueued' ),
				"\"{$handle}\" stylesheet should NOT be enqueued {$context}."
			);
			$this->assertFalse(
				wp_script_is( $handle, 'enqueued' ),
				"\"{$handle}\" script should NOT be enqueued {$context}."
			);
		}
	}

	/**
	 * Assert that both the widget and the glue bundle are enqueued.
	 */
	private function assertSurfaceEnqueued( string $context ): void {
		$this->assertTrue(
			wp_style_is( 'snopix-search', 'enqueued' ),
			"\"snopix-search\" stylesheet should be enqueued {$context}."
		);
		$this->assertTrue(
			wp_script_is( 'snopix-search', 'enqueued' ),
			"\"snopix-search\" script should be enqueued {$context}."
		);
		$this->assertTrue(
			wp_style_is( 'snopix-media', 'enqueued' ),
			"\"snopix-media\" stylesheet should be enqueued {$context}."
		);
		$this->assertTrue(
			wp_script_is( 'snopix-media', 'enqueued' ),
			"\"snopix-media\" script should be enqueued {$context}."
		);
	}

	// -------------------------------------------------------------------------
	// Asset enqueueing - screen gating
	// -------------------------------------------------------------------------

	public function test_enqueue_loads_assets_on_media_new_screen(): void {
		set_current_screen( 'media-new.php' );
		$this->assertSame( 'media', get_current_screen()->base, 'Sanity: media-new.php maps to base "media".' );

		$this->surfaces->enqueue();

		$this->assertSurfaceEnqueued( 'on the Upload New Media screen' );
	}

	public function test_enqueue_loads_assets_on_upload_screen(): void {
		set_current_screen( 'upload' );

		$this->surfaces->enqueue();

		$this->assertSurfaceEnqueued( 'on the Media Library screen' );
	}

	public function test_enqueue_loads_assets_on_post_editor_screen(): void {
		set_current_screen( 'post' );

		$this->surfaces->enqueue();

		$this->assertSurfaceEnqueued( 'on the post editor screen' );
	}

	public function test_enqueue_is_noop_on_unrelated_screen(): void {
		set_current_screen( 'edit' );

		$this->surfaces->enqueue();

		$this->assertNothingEnqueued( 'on an unrelated admin screen' );
	}

	public function test_enqueue_is_noop_without_a_screen(): void {
		// No set_current_screen(): get_current_screen() returns null.
		unset( $GLOBALS['current_screen'] );

		$this->surfaces->enqueue();

		$this->assertNothingEnqueued( 'when there is no current screen' );
	}

	// -------------------------------------------------------------------------
	// Upload New Media markup - page gating
	// -------------------------------------------------------------------------

	private function capture_toggle(): string {
		ob_start();
		$this->surfaces->render_upload_toggle();
		return (string) ob_get_clean();
	}

	private function capture_panel(): string {
		ob_start();
		$this->surfaces->render_upload_panel();
		return (string) ob_get_clean();
	}

	public function test_toggle_renders_on_media_new_php(): void {
		$GLOBALS['pagenow'] = 'media-new.php';

		$html = $this->capture_toggle();

		$this->assertStringContainsString( 'id="snopix-upload-toggle"', $html );
		$this->assertStringContainsString( 'data-mode="upload"', $html );
		$this->assertStringContainsString( 'data-mode="search"', $html );
		// Segmented toggle uses aria-pressed, not tab roles.
		$this->assertStringContainsString( 'aria-pressed', $html );
		$this->assertStringNotContainsString( 'role="tab"', $html );
	}

	public function test_panel_renders_on_media_new_php(): void {
		$GLOBALS['pagenow'] = 'media-new.php';

		$html = $this->capture_panel();

		$this->assertStringContainsString( 'id="snopix-upload-panel"', $html );
		$this->assertStringContainsString( 'data-snopix-search', $html );
	}

	public function test_toggle_is_empty_off_media_new_php(): void {
		$GLOBALS['pagenow'] = 'upload.php';

		$this->assertSame( '', $this->capture_toggle() );
	}

	public function test_panel_is_empty_off_media_new_php(): void {
		$GLOBALS['pagenow'] = 'upload.php';

		$this->assertSame( '', $this->capture_panel() );
	}
}
