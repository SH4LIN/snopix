<?php
/**
 * Frontend search widget shortcode handler.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Renders the [ps_search] shortcode.
 */
class Shortcode {
	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'ps_search', [ $this, 'render' ] );
	}

	/**
	 * Render the search widget HTML.
	 *
	 * @param array $atts Shortcode attributes (unused).
	 *
	 * @return string
	 */
	public function render( array $atts ): string {
		wp_enqueue_style(
			'ps-search',
			PIXEL_SCOUT_PLUGIN_URL . 'public/assets/css/search.css',
			[],
			PIXEL_SCOUT_VERSION
		);
		wp_enqueue_script(
			'ps-search',
			PIXEL_SCOUT_PLUGIN_URL . 'public/assets/js/search.js',
			[],
			PIXEL_SCOUT_VERSION,
			true
		);
		wp_localize_script(
			'ps-search',
			'ps_public',
			[
				'rest_url' => esc_url_raw( rest_url( 'ps/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			]
		);

		ob_start();
		?>
		<div class="ps-search-widget" id="ps-search-widget">
			<div class="ps-drop-zone" id="ps-drop-zone">
				<svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
					<circle cx="20" cy="20" r="20" fill="#F5F5F7"/>
					<path d="M20 12v16M12 20h16" stroke="#6E6E73" stroke-width="2" stroke-linecap="round"/>
				</svg>
				<p class="ps-drop-label"><?php esc_html_e( 'Find similar images', 'pixel-scout' ); ?></p>
				<p class="ps-drop-sub"><?php esc_html_e( 'Drop an image or click to browse', 'pixel-scout' ); ?></p>
				<input type="file" id="ps-file-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" />
			</div>
			<div class="ps-results-grid" id="ps-results" hidden></div>
			<p class="ps-error-msg" id="ps-error" hidden></p>
		</div>
		<?php
		return ob_get_clean();
	}
}
