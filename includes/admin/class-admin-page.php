<?php
/**
 * Admin page registration and asset enqueueing.
 *
 * @package Snopix
 */

namespace Snopix\Admin;

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
			__( 'Snopix', 'snopix' ),
			__( 'Snopix', 'snopix' ),
			'manage_options',
			'snopix',
			array( $this, 'render' )
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue admin assets on the Snopix page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, 'snopix' ) ) {
			return;
		}

		wp_enqueue_style(
			'snopix-admin',
			SNOPIX_PLUGIN_URL . 'admin/app/dist/snopix-admin.css',
			array(),
			SNOPIX_VERSION
		);

		wp_enqueue_script(
			'snopix-admin',
			SNOPIX_PLUGIN_URL . 'admin/app/dist/snopix-admin.js',
			// wp-api-fetch is core's REST helper; declaring it here guarantees
			// the `wp.apiFetch` global is loaded before our bundle boots so the
			// shared `@wordpress/api-fetch` import resolves to the same
			// already-initialised instance instead of a duplicate.
			array( 'wp-api-fetch' ),
			SNOPIX_VERSION,
			true
		);

		wp_localize_script(
			'snopix-admin',
			'snopix_data',
			array(
				'rest_url' => esc_url_raw( rest_url( 'snopix/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'is_admin' => current_user_can( 'manage_options' ),
			)
		);

		wp_set_script_translations( 'snopix-admin', 'snopix', SNOPIX_PLUGIN_DIR . 'languages' );
	}

	/**
	 * Render the admin page mount point.
	 *
	 * @return void
	 */
	public function render(): void {
		require SNOPIX_PLUGIN_DIR . 'admin/app/views/admin-root.php';
	}
}
