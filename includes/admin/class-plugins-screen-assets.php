<?php
/**
 * Plugins-screen asset enqueueing for the React uninstall-confirm modal.
 *
 * @package Snopix
 */

namespace Snopix\Admin;

use Snopix\Hooks\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the mini React bundle that intercepts the Snopix Delete link on
 * wp-admin/plugins.php and shows a confirmation modal with a data summary.
 *
 * Only loaded when the admin has opted into uninstall consent via the
 * `require_consent` setting — there is no point shipping the bundle to users
 * who get the native flow.
 */
class Plugins_Screen_Assets {

	/**
	 * Hook the enqueue check onto admin_enqueue_scripts.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
	}

	/**
	 * Enqueue the bundle when every gate passes.
	 *
	 * Gates:
	 *   - hook suffix is `plugins.php`
	 *   - current user can `delete_plugins`
	 *   - Settings::should_require_consent() is true
	 *
	 * @param string $hook Current admin hook suffix (passed by WP).
	 *
	 * @return void
	 */
	public function maybe_enqueue( string $hook ): void {
		if ( 'plugins.php' !== $hook ) {
			return;
		}
		if ( ! current_user_can( 'delete_plugins' ) ) {
			return;
		}
		if ( ! Settings::should_require_consent() ) {
			return;
		}

		wp_enqueue_style(
			'snopix-plugins-screen',
			SNOPIX_PLUGIN_URL . 'admin/plugins-screen/build/snopix-plugins-screen.css',
			array(),
			SNOPIX_VERSION
		);

		wp_enqueue_script(
			'snopix-plugins-screen',
			SNOPIX_PLUGIN_URL . 'admin/plugins-screen/build/snopix-plugins-screen.js',
			array( 'wp-api-fetch' ),
			SNOPIX_VERSION,
			true
		);

		wp_localize_script(
			'snopix-plugins-screen',
			'snopixPluginsScreen',
			array(
				'restUrl'         => esc_url_raw( rest_url( 'snopix/v1/' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'slug'            => 'snopix/snopix.php',
				'dropOnUninstall' => Settings::should_drop_on_uninstall(),
			)
		);

		wp_set_script_translations( 'snopix-plugins-screen', 'snopix', SNOPIX_PLUGIN_DIR . 'languages' );
	}
}
