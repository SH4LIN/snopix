<?php
/**
 * Plugin bootstrap and lifecycle handlers.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Infrastructure;

use PixelScout\Repository\{Index_Repository, Schema};
use PixelScout\Imaging\{GD_Loader, PHash_Processor, Color_Processor, Edge_Processor, Similarity};
use PixelScout\Search\{Fingerprint_Factory, Query_Image, Score_Calculator, Search_Pipeline};
use PixelScout\Indexing\{Mime_Validator, Index_Progress, Image_Indexer, Bulk_Indexer};
use PixelScout\Hooks\{Media_Hooks, Cron_Handler, Settings};
use PixelScout\Api\{Rate_Limiter, REST_Controller};
use PixelScout\Admin\Admin_Page;
use PixelScout\Frontend\Shortcode;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Schema manager.
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->schema = new Schema();
	}

	/**
	 * Get plugin singleton.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
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
		( new Admin_Page() )->register();
	}

	/**
	 * Register the search widget shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode(): void {
		( new Shortcode() )->register();
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
		$repository   = new Index_Repository( $wpdb );
		$similarity   = new Similarity();
		$loader       = new GD_Loader();
		$factory      = new Fingerprint_Factory(
			$loader,
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);
		$calculator   = new Score_Calculator( $similarity );
		$pipeline     = new Search_Pipeline( $repository, $factory, $calculator, $similarity );
		$validator    = new Mime_Validator();
		$indexer      = new Image_Indexer( $validator, $factory, $repository );
		$bulk_indexer = new Bulk_Indexer( $repository, $indexer, new Index_Progress(), new Action_Scheduler() );
		$settings     = new Settings();

		$controller = new REST_Controller(
			$pipeline,
			new Query_Image(),
			$repository,
			$bulk_indexer,
			new Index_Progress(),
			new Rate_Limiter(),
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
		$repository    = new Index_Repository( $wpdb );
		$validator     = new Mime_Validator();
		$loader        = new GD_Loader();
		$factory       = new Fingerprint_Factory(
			$loader,
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);
		$indexer       = new Image_Indexer( $validator, $factory, $repository );
		$bulk_indexer  = new Bulk_Indexer( $repository, $indexer, new Index_Progress(), new Action_Scheduler() );

		( new Media_Hooks( $indexer ) )->register();
		( new Cron_Handler( $bulk_indexer ) )->register();
	}

	/**
	 * Register plugin settings. Must run on admin_init.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		( new Settings() )->register();
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

		$schema = new Schema();
		$schema->uninstall();

		delete_option( 'ps_settings' );
		delete_option( PIXEL_SCOUT_OPTION_DB_VERSION );
		delete_transient( 'ps_bulk_progress' );
		delete_transient( 'ps_bulk_total' );
		delete_transient( 'ps_bulk_status' );

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
