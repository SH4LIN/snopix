<?php
/**
 * Integration tests for Snopix\Admin\Admin_Page and Snopix\Admin\Editor_Assets.
 *
 * Covers menu/submenu registration and script/style enqueueing without
 * asserting any rendered DOM output.
 *
 * @package Snopix
 */

use Snopix\Admin\Admin_Page;
use Snopix\Admin\Editor_Assets;

/**
 * @covers \Snopix\Admin\Admin_Page
 * @covers \Snopix\Admin\Editor_Assets
 */
final class Admin_Assets_Test extends Snopix_Integration_TestCase {

	/**
	 * @var Admin_Page
	 */
	private Admin_Page $admin_page;

	/**
	 * @var Editor_Assets
	 */
	private Editor_Assets $editor_assets;

	/**
	 * Administrator user ID created for this test run.
	 *
	 * @var int
	 */
	private int $admin_user_id;

	public function set_up(): void {
		parent::set_up();

		$this->admin_page    = new Admin_Page();
		$this->editor_assets = new Editor_Assets();

		// Create and set an administrator so capability checks inside the
		// classes under test pass reliably.
		$this->admin_user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		// Activate the wp-admin environment so add_menu_page / add_media_page
		// can populate the global $menu / $submenu arrays.
		set_current_screen( 'upload' );
	}

	public function tear_down(): void {
		// Reset the media submenu entry added during the test so it does not
		// bleed into subsequent tests.
		global $submenu;
		unset( $submenu['upload.php'] );

		// Deregister/dequeue assets registered during these tests.
		wp_dequeue_script( 'snopix-admin' );
		wp_deregister_script( 'snopix-admin' );
		wp_dequeue_style( 'snopix-admin' );
		wp_deregister_style( 'snopix-admin' );

		wp_dequeue_script( 'snopix-editor' );
		wp_deregister_script( 'snopix-editor' );

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Admin_Page — menu registration
	// -------------------------------------------------------------------------

	/**
	 * After Admin_Page::register() the 'snopix' slug must appear under the
	 * 'upload.php' (Media) submenu.
	 */
	public function test_admin_page_registers_media_submenu(): void {
		global $submenu;

		$this->admin_page->register();

		$this->assertArrayHasKey(
			'upload.php',
			$submenu,
			'Media submenu parent key "upload.php" should exist after register().'
		);

		$slugs = array_column( $submenu['upload.php'], 2 );
		$this->assertContains(
			'snopix',
			$slugs,
			'The "snopix" page slug should be present in the Media submenu.'
		);
	}

	/**
	 * The registered submenu entry must carry the expected page title.
	 */
	public function test_admin_page_submenu_title_is_snopix(): void {
		global $submenu;

		$this->admin_page->register();

		$found = array();
		foreach ( $submenu['upload.php'] ?? array() as $entry ) {
			// $entry: [ title, capability, slug, display_title ]
			if ( isset( $entry[2] ) && 'snopix' === $entry[2] ) {
				$found = $entry;
				break;
			}
		}

		$this->assertNotEmpty( $found, 'No "snopix" submenu entry found.' );
		// Index 0 is the page title, index 1 is the display/menu title.
		$this->assertSame( 'Snopix', $found[0] );
	}

	// -------------------------------------------------------------------------
	// Admin_Page — asset enqueueing
	// -------------------------------------------------------------------------

	/**
	 * Admin_Page::enqueue() must enqueue 'snopix-admin' style and script when
	 * the hook string contains 'snopix'.
	 */
	public function test_admin_page_enqueues_assets_on_snopix_hook(): void {
		// Simulate the hook suffix WordPress passes for an add_media_page page.
		$hook = 'media_page_snopix';

		$this->admin_page->enqueue( $hook );

		$this->assertTrue(
			wp_style_is( 'snopix-admin', 'enqueued' ),
			'"snopix-admin" stylesheet should be enqueued on the Snopix page.'
		);

		$this->assertTrue(
			wp_script_is( 'snopix-admin', 'enqueued' ),
			'"snopix-admin" script should be enqueued on the Snopix page.'
		);
	}

	/**
	 * Admin_Page::enqueue() must not enqueue anything on an unrelated hook.
	 */
	public function test_admin_page_does_not_enqueue_on_unrelated_hook(): void {
		$this->admin_page->enqueue( 'edit.php' );

		$this->assertFalse(
			wp_style_is( 'snopix-admin', 'enqueued' ),
			'"snopix-admin" stylesheet should NOT be enqueued on unrelated hooks.'
		);

		$this->assertFalse(
			wp_script_is( 'snopix-admin', 'enqueued' ),
			'"snopix-admin" script should NOT be enqueued on unrelated hooks.'
		);
	}

	// -------------------------------------------------------------------------
	// Editor_Assets — registration hook
	// -------------------------------------------------------------------------

	/**
	 * Editor_Assets::register() must attach 'enqueue' to the
	 * 'enqueue_block_editor_assets' action.
	 */
	public function test_editor_assets_registers_block_editor_hook(): void {
		$this->editor_assets->register();

		$this->assertGreaterThan(
			0,
			has_action( 'enqueue_block_editor_assets', array( $this->editor_assets, 'enqueue' ) ),
			'Editor_Assets::enqueue() should be hooked to "enqueue_block_editor_assets".'
		);
	}

	// -------------------------------------------------------------------------
	// Editor_Assets — conditional enqueue
	// -------------------------------------------------------------------------

	/**
	 * Editor_Assets::enqueue() must register/enqueue 'snopix-editor' when the
	 * build artefact (index.asset.php) exists.
	 *
	 * NOTE: Editor_Assets::enqueue() is a no-op when SNOPIX_PLUGIN_DIR .
	 * 'admin/editor/build/index.asset.php' is absent (fresh checkout without
	 * `npm run build:editor`). This test is skipped in that scenario.
	 */
	public function test_editor_assets_enqueues_script_when_build_exists(): void {
		$asset_file = SNOPIX_PLUGIN_DIR . 'admin/editor/build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			$this->markTestSkipped( 'Editor build artefact not present; run `npm run build:editor` first.' );
		}

		$this->editor_assets->enqueue();

		$this->assertTrue(
			wp_script_is( 'snopix-editor', 'enqueued' ),
			'"snopix-editor" script should be enqueued when the build artefact exists.'
		);
	}

	/**
	 * Editor_Assets::enqueue() must be a no-op (no script registered) when the
	 * build artefact is absent.
	 *
	 * NOTE: This test is only meaningful when the artefact is genuinely absent.
	 * It is skipped if it happens to be present (i.e. a built checkout).
	 */
	public function test_editor_assets_is_noop_when_build_absent(): void {
		$asset_file = SNOPIX_PLUGIN_DIR . 'admin/editor/build/index.asset.php';

		if ( file_exists( $asset_file ) ) {
			$this->markTestSkipped( 'Editor build artefact is present; skip no-op guard test.' );
		}

		$this->editor_assets->enqueue();

		$this->assertFalse(
			wp_script_is( 'snopix-editor', 'enqueued' ),
			'"snopix-editor" should NOT be enqueued when the build artefact is absent.'
		);

		$this->assertFalse(
			wp_script_is( 'snopix-editor', 'registered' ),
			'"snopix-editor" should NOT be registered when the build artefact is absent.'
		);
	}
}
