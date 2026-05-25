<?php
/**
 * Plugin settings registration and rendering.
 *
 * @package Snopix
 */

namespace Snopix\Hooks;

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
			'snopix_settings',
			'snopix_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array( 'search_visibility' => 'anyone' ),
			)
		);

		add_settings_section(
			'snopix_general',
			__( 'General', 'snopix' ),
			'__return_false',
			'snopix_settings'
		);

		add_settings_field(
			'snopix_search_visibility',
			__( 'Search visibility', 'snopix' ),
			array( $this, 'render_visibility_field' ),
			'snopix_settings',
			'snopix_general'
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
		$settings = get_option( 'snopix_settings', array( 'search_visibility' => 'anyone' ) );
		$current  = $settings['search_visibility'] ?? 'anyone';
		$options  = array(
			'anyone'    => __( 'Anyone', 'snopix' ),
			'logged_in' => __( 'Logged-in users only', 'snopix' ),
		);

		foreach ( $options as $value => $label ) {
			printf(
				'<label><input type="radio" name="snopix_settings[search_visibility]" value="%s"%s> %s</label><br>',
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
		$settings = get_option( 'snopix_settings', array( 'search_visibility' => 'anyone' ) );
		return $settings['search_visibility'] ?? 'anyone';
	}
}
