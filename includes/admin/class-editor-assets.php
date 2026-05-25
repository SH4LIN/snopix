<?php
/**
 * Block editor asset registration for the Snopix shortcode panel.
 *
 * @package Snopix
 */

namespace Snopix\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the editor-side bundle that injects a Snopix Search panel into
 * the core/shortcode block inspector. Built by @wordpress/scripts; the
 * companion `index.asset.php` carries the dependency + version hash so we
 * never have to hand-maintain the @wordpress/* dependency array.
 */
class Editor_Assets {

	/**
	 * Filesystem path to the editor build directory, relative to the plugin
	 * root.
	 *
	 * @var string
	 */
	private const BUILD_PATH = 'admin/editor/build/';

	/**
	 * Register the editor-asset enqueue hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the editor bundle. No-op if the build artefact is missing
	 * (e.g. fresh checkout before `npm run build:editor`).
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$asset_file = SNOPIX_PLUGIN_DIR . self::BUILD_PATH . 'index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		$dependencies = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
			? $asset['dependencies']
			: array();
		$version = isset( $asset['version'] ) ? (string) $asset['version'] : SNOPIX_VERSION;

		wp_enqueue_script(
			'snopix-editor',
			SNOPIX_PLUGIN_URL . self::BUILD_PATH . 'index.js',
			$dependencies,
			$version,
			true
		);

		wp_set_script_translations( 'snopix-editor', 'snopix', SNOPIX_PLUGIN_DIR . 'languages' );
	}
}
