<?php
/**
 * Plugin settings registration and rendering.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Manages plugin settings via the WordPress Settings API.
 */
class Settings {

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register(): void {
		register_setting(
			'ps_settings',
			'ps_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array( 'search_visibility' => 'anyone' ),
			)
		);

		add_settings_section(
			'ps_general',
			__( 'General', 'pixel-scout' ),
			'__return_false',
			'ps_settings'
		);

		add_settings_field(
			'ps_search_visibility',
			__( 'Search visibility', 'pixel-scout' ),
			array( $this, 'render_visibility_field' ),
			'ps_settings',
			'ps_general'
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array<string, mixed> $input Raw input array.
	 *
	 * @return array<string, string>
	 */
	public function sanitize( array $input ): array {
		$allowed    = array( 'anyone', 'logged_in' );
		$visibility = isset( $input['search_visibility'] ) ? sanitize_key( $input['search_visibility'] ) : 'anyone';

		return array(
			'search_visibility' => in_array( $visibility, $allowed, true ) ? $visibility : 'anyone',
		);
	}

	/**
	 * Render the search visibility radio field.
	 *
	 * @return void
	 */
	public function render_visibility_field(): void {
		$settings = get_option( 'ps_settings', array( 'search_visibility' => 'anyone' ) );
		$current  = $settings['search_visibility'] ?? 'anyone';
		$options  = array(
			'anyone'    => __( 'Anyone', 'pixel-scout' ),
			'logged_in' => __( 'Logged-in users only', 'pixel-scout' ),
		);

		foreach ( $options as $value => $label ) {
			printf(
				'<label><input type="radio" name="ps_settings[search_visibility]" value="%s"%s> %s</label><br>',
				esc_attr( $value ),
				checked( $current, $value, false ),
				esc_html( $label )
			);
		}
	}

	/**
	 * Get the configured search visibility value.
	 *
	 * @return string
	 */
	public function get_visibility(): string {
		$settings = get_option( 'ps_settings', array( 'search_visibility' => 'anyone' ) );
		return $settings['search_visibility'] ?? 'anyone';
	}
}
