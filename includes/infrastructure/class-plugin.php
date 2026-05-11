<?php
/**
 * Plugin bootstrap and lifecycle handlers.
 *
 * @package Pixel_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pixel_Scout_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Pixel_Scout_Plugin|null
	 */
	private static ?Pixel_Scout_Plugin $instance = null;

	/**
	 * Schema manager.
	 *
	 * @var Pixel_Scout_Schema
	 */
	private Pixel_Scout_Schema $schema;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->schema = new Pixel_Scout_Schema();
	}

	/**
	 * Get plugin singleton.
	 *
	 * @return Pixel_Scout_Plugin
	 */
	public static function instance(): Pixel_Scout_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
	}

	/**
	 * Handle plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Pixel Scout] Activation hook triggered.' );
		}

		self::instance()->schema->install();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Pixel Scout] Schema installed successfully.' );
		}

		self::instance()->schema->maybe_upgrade();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Pixel Scout] Plugin activation complete.' );
		}
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Pixel Scout] Deactivation hook triggered.' );
		}

		wp_clear_scheduled_hook( 'ps_bulk_index_batch' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Pixel Scout] Scheduled events cleared.' );
		}
	}

	/**
	 * Handle plugin uninstall.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Pixel Scout] Uninstall routine triggered.' );
		}

		$schema = new Pixel_Scout_Schema();
		$schema->uninstall();

		delete_option( 'ps_settings' );
		delete_option( PIXEL_SCOUT_OPTION_DB_VERSION );
		delete_transient( 'ps_bulk_progress' );
		delete_transient( 'ps_bulk_total' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Pixel Scout] Uninstall complete.' );
		}
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'pixel-scout', false, dirname( plugin_basename( PIXEL_SCOUT_FILE ) ) . '/languages' );
	}
}


