<?php
/**
 * Admin page registration and asset enqueueing.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Handles WordPress admin menu registration and script/style loading.
 */
class Admin_Page {

	/**
	 * Register the admin menu page and enqueue hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_media_page(
			__( 'Pixel Scout', 'pixel-scout' ),
			__( 'Pixel Scout', 'pixel-scout' ),
			'manage_options',
			'pixel-scout',
			[ $this, 'render' ]
		);

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue admin assets on the Pixel Scout page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, 'pixel-scout' ) ) {
			return;
		}

		wp_enqueue_style(
			'ps-admin',
			PIXEL_SCOUT_PLUGIN_URL . 'admin/dist/ps-admin.css',
			[],
			PIXEL_SCOUT_VERSION
		);

		wp_enqueue_script(
			'ps-admin',
			PIXEL_SCOUT_PLUGIN_URL . 'admin/dist/ps-admin.js',
			[],
			PIXEL_SCOUT_VERSION,
			true
		);

		wp_localize_script(
			'ps-admin',
			'ps_data',
			[
				'rest_url' => esc_url_raw( rest_url( 'ps/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'is_admin' => current_user_can( 'manage_options' ),
			]
		);

		wp_set_script_translations( 'ps-admin', 'pixel-scout', PIXEL_SCOUT_PLUGIN_DIR . 'languages' );
	}

	/**
	 * Render the admin page mount point.
	 *
	 * @return void
	 */
	public function render(): void {
		require PIXEL_SCOUT_PLUGIN_DIR . 'admin/views/admin-root.php';
	}
}
