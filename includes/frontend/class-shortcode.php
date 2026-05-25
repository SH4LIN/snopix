<?php
/**
 * Frontend search widget shortcode handler.
 *
 * @package Snopix
 */

namespace Snopix\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Renders the [snopix_search] shortcode.
 */
class Shortcode {
	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'snopix_search', array( $this, 'render' ) );
	}

	/**
	 * Render the search widget HTML.
	 *
	 * @return string
	 */
	public function render(): string {
		wp_enqueue_style(
			'snopix-search',
			SNOPIX_PLUGIN_URL . 'public/assets/css/search.css',
			array(),
			SNOPIX_VERSION
		);
		wp_enqueue_script(
			'snopix-search',
			SNOPIX_PLUGIN_URL . 'public/assets/js/search.js',
			array(),
			SNOPIX_VERSION,
			true
		);
		wp_localize_script(
			'snopix-search',
			'snopix_public',
			array(
				'rest_url' => esc_url_raw( rest_url( 'snopix/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);

		ob_start();
		?>
		<div class="snopix-search-widget" id="snopix-search-widget">
			<div class="snopix-drop-zone" id="snopix-drop-zone">
				<svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
					<circle cx="20" cy="20" r="20" fill="#F5F5F7"/>
					<path d="M20 12v16M12 20h16" stroke="#6E6E73" stroke-width="2" stroke-linecap="round"/>
				</svg>
				<p class="snopix-drop-label"><?php esc_html_e( 'Find similar images', 'snopix' ); ?></p>
				<p class="snopix-drop-sub"><?php esc_html_e( 'Drop an image or click to browse', 'snopix' ); ?></p>
				<input type="file" id="snopix-file-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" />
			</div>
			<div class="snopix-results-grid" id="snopix-results" hidden></div>
			<p class="snopix-error-msg" id="snopix-error" hidden></p>
		</div>
		<?php
		return ob_get_clean();
	}
}
