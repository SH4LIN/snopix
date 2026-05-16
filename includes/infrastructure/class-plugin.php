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
		add_action( 'plugins_loaded', [ $this, 'maybe_upgrade_db' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'init', [ $this, 'register_hooks' ] );
		add_action( 'init', [ $this, 'register_shortcode' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	/**
	 * Register the Pixel Scout admin page.
	 *
	 * @return void
	 */
	public function register_admin_page(): void {
		( new Pixel_Scout_Admin_Page() )->register();
	}

	/**
	 * Register the search widget shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode(): void {
		( new Pixel_Scout_Shortcode() )->register();
	}

	/**
	 * Run DB migrations if version changed.
	 *
	 * @return void
	 */
	public function maybe_upgrade_db(): void {
		$this->schema->maybe_upgrade();
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		global $wpdb;
		$repository   = new Pixel_Scout_Index_Repository( $wpdb );
		$similarity   = new Pixel_Scout_Similarity();
		$loader       = new Pixel_Scout_GD_Loader();
		$factory      = new Pixel_Scout_Fingerprint_Factory(
			$loader,
			new Pixel_Scout_PHash_Processor(),
			new Pixel_Scout_Color_Processor(),
			new Pixel_Scout_Edge_Processor()
		);
		$calculator   = new Pixel_Scout_Score_Calculator( $similarity );
		$pipeline     = new Pixel_Scout_Search_Pipeline( $repository, $factory, $calculator, $similarity );
		$validator    = new Pixel_Scout_Mime_Validator();
		$indexer      = new Pixel_Scout_Image_Indexer( $validator, $factory, $repository );
		$bulk_indexer = new Pixel_Scout_Bulk_Indexer( $repository, $indexer, new Pixel_Scout_Index_Progress() );
		$settings     = new Pixel_Scout_Settings();

		$controller = new Pixel_Scout_REST_Controller(
			$pipeline,
			new Pixel_Scout_Query_Image(),
			$repository,
			$bulk_indexer,
			new Pixel_Scout_Index_Progress(),
			new Pixel_Scout_Rate_Limiter(),
			$settings
		);
		$controller->register_routes();
	}

	/**
	 * Register indexing domain hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		global $wpdb;
		$repository    = new Pixel_Scout_Index_Repository( $wpdb );
		$validator     = new Pixel_Scout_Mime_Validator();
		$loader        = new Pixel_Scout_GD_Loader();
		$factory       = new Pixel_Scout_Fingerprint_Factory(
			$loader,
			new Pixel_Scout_PHash_Processor(),
			new Pixel_Scout_Color_Processor(),
			new Pixel_Scout_Edge_Processor()
		);
		$indexer       = new Pixel_Scout_Image_Indexer( $validator, $factory, $repository );
		$bulk_indexer  = new Pixel_Scout_Bulk_Indexer( $repository, $indexer, new Pixel_Scout_Index_Progress() );

		( new Pixel_Scout_Media_Hooks( $indexer ) )->register();
		( new Pixel_Scout_Cron_Handler( $bulk_indexer ) )->register();
	}

	/**
	 * Register plugin settings. Must run on admin_init.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		( new Pixel_Scout_Settings() )->register();
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


