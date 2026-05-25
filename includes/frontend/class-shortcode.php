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
 *
 * Supported attributes:
 *   - variant      One of `card` (default), `inline`, or `narrow`. Controls
 *                  the widget chrome (header, drop-row direction, results
 *                  grid density). Anything else falls back to `card`.
 *   - title        Header label shown alongside the Snopix mark. Ignored
 *                  by the `inline` variant which has no header. Defaults
 *                  to "Search by image".
 *   - max_results  Cap on result cards rendered after a search. Clamped
 *                  between 1 and 48. Defaults to 12.
 *
 * Markup is a single mount point — the React bundle in `public/app/dist`
 * boots into every `[data-snopix-search]` element on the page, so multiple
 * shortcodes can live on the same page without sharing state.
 */
class Shortcode {
	/**
	 * Counter for mount-point ids so repeated shortcodes on a page get
	 * deterministic, unique element ids.
	 *
	 * @var int
	 */
	private static int $instance = 0;

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'snopix_search', array( $this, 'render' ) );
	}

	/**
	 * Render the search widget mount point.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render( $atts = array() ): string {
		$parsed = shortcode_atts(
			array(
				'variant'     => 'card',
				'title'       => __( 'Search by image', 'snopix' ),
				'max_results' => 12,
			),
			is_array( $atts ) ? $atts : array(),
			'snopix_search'
		);

		$variant = in_array( $parsed['variant'], array( 'card', 'inline', 'narrow' ), true )
			? $parsed['variant']
			: 'card';
		$title       = (string) $parsed['title'];
		$max_results = max( 1, min( 48, (int) $parsed['max_results'] ) );

		wp_enqueue_style(
			'snopix-search',
			SNOPIX_PLUGIN_URL . 'public/app/dist/snopix-search.css',
			array(),
			SNOPIX_VERSION
		);
		wp_enqueue_script(
			'snopix-search',
			SNOPIX_PLUGIN_URL . 'public/app/dist/snopix-search.js',
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

		++self::$instance;
		$mount_id = 'snopix-search-' . self::$instance;

		return sprintf(
			'<div id="%1$s" data-snopix-search data-variant="%2$s" data-title="%3$s" data-max-results="%4$d"></div>',
			esc_attr( $mount_id ),
			esc_attr( $variant ),
			esc_attr( $title ),
			$max_results
		);
	}
}
