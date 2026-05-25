<?php
/**
 * Plugin settings registration, sanitization, and typed accessors.
 *
 * @package Snopix
 */

namespace Snopix\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin settings via the WordPress Settings API.
 *
 * Owns the schema for the `snopix_settings` option: defaults, sanitization
 * (clamps, allowlists, casts), and typed accessors that engines (rate limiter,
 * search pipeline, bulk indexer, duplicate scanner, query image downscaler,
 * uninstall handler) read from at runtime.
 */
class Settings {

	/**
	 * Option name in `wp_options`.
	 */
	public const OPTION_NAME = 'snopix_settings';

	/**
	 * Canonical default payload. Engines must always be able to read a
	 * sensible value even when the option row is missing or malformed.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'search_visibility'   => 'anyone',
			'rate_limit'          => 10,
			'match_threshold'     => 0.85,
			'batch_size'          => 25,
			'downscale_max'       => 1024,
			'duplicate_threshold' => 0.95,
			'drop_on_uninstall'   => true,
			'require_consent'     => false,
		);
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register(): void {
		register_setting(
			'snopix_settings',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
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
	 * Sanitize settings input. Every key is clamped/cast to a safe value and
	 * unknown keys are dropped — engines can rely on the schema.
	 *
	 * @param array<string, mixed> $input Raw input array.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input ): array {
		$defaults = self::defaults();

		$allowed_visibility = array( 'anyone', 'logged_in' );
		$visibility         = isset( $input['search_visibility'] )
			? sanitize_key( (string) $input['search_visibility'] )
			: $defaults['search_visibility'];

		$rate_limit  = isset( $input['rate_limit'] ) ? (int) $input['rate_limit'] : (int) $defaults['rate_limit'];
		$batch_size  = isset( $input['batch_size'] ) ? (int) $input['batch_size'] : (int) $defaults['batch_size'];
		$downscale   = isset( $input['downscale_max'] ) ? (int) $input['downscale_max'] : (int) $defaults['downscale_max'];
		$match_thr   = isset( $input['match_threshold'] ) ? (float) $input['match_threshold'] : (float) $defaults['match_threshold'];
		$dup_thr     = isset( $input['duplicate_threshold'] ) ? (float) $input['duplicate_threshold'] : (float) $defaults['duplicate_threshold'];
		$drop_unins  = isset( $input['drop_on_uninstall'] ) ? (bool) $input['drop_on_uninstall'] : (bool) $defaults['drop_on_uninstall'];
		$req_consent = isset( $input['require_consent'] ) ? (bool) $input['require_consent'] : (bool) $defaults['require_consent'];

		return array(
			'search_visibility'   => in_array( $visibility, $allowed_visibility, true ) ? $visibility : 'anyone',
			'rate_limit'          => max( 1, min( 60, $rate_limit ) ),
			'batch_size'          => max( 5, min( 200, $batch_size ) ),
			'downscale_max'       => max( 256, min( 4096, $downscale ) ),
			'match_threshold'     => max( 0.5, min( 1.0, $match_thr ) ),
			'duplicate_threshold' => max( 0.8, min( 1.0, $dup_thr ) ),
			'drop_on_uninstall'   => $drop_unins,
			'require_consent'     => $req_consent,
		);
	}

	/**
	 * Read the canonical settings array, merged on top of {@see self::defaults()}.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Render the search visibility radio field used by the legacy Settings API
	 * page (kept for parity with WP's options.php form rendering).
	 *
	 * @return void
	 */
	public function render_visibility_field(): void {
		$current = self::get_visibility();
		$options = array(
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
		$all = self::all();
		return 'logged_in' === ( $all['search_visibility'] ?? 'anyone' )
			? 'logged_in'
			: 'anyone';
	}

	/**
	 * Rate-limit cap (requests per minute) for the public search endpoint.
	 *
	 * @return int
	 */
	public static function get_rate_limit(): int {
		$all = self::all();
		return (int) $all['rate_limit'];
	}

	/**
	 * Composite-score floor below which `/search` results are dropped.
	 *
	 * @return float
	 */
	public static function get_match_threshold(): float {
		$all = self::all();
		return (float) $all['match_threshold'];
	}

	/**
	 * Indexer batch size — attachments fingerprinted per WP-Cron tick.
	 *
	 * @return int
	 */
	public static function get_batch_size(): int {
		$all = self::all();
		return (int) $all['batch_size'];
	}

	/**
	 * Downscale ceiling (max edge in pixels) for query images before
	 * fingerprinting.
	 *
	 * @return int
	 */
	public static function get_downscale_max(): int {
		$all = self::all();
		return (int) $all['downscale_max'];
	}

	/**
	 * Composite-score floor used by the duplicate scanner when clustering.
	 *
	 * @return float
	 */
	public static function get_duplicate_threshold(): float {
		$all = self::all();
		return (float) $all['duplicate_threshold'];
	}

	/**
	 * Whether to drop the index table and options on plugin uninstall.
	 *
	 * @return bool
	 */
	public static function should_drop_on_uninstall(): bool {
		$all = self::all();
		return (bool) $all['drop_on_uninstall'];
	}

	/**
	 * Whether the admin should be asked to confirm before plugin deletion.
	 *
	 * @return bool
	 */
	public static function should_require_consent(): bool {
		$all = self::all();
		return (bool) $all['require_consent'];
	}
}
