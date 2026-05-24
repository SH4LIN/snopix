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
use PixelScout\Api\{Rate_Limiter, REST_Controller, Duplicates_REST_Controller};
use PixelScout\Duplicates\{Duplicate_Progress, Duplicate_Finder, Duplicate_Scanner, Duplicate_Cron_Handler};
use PixelScout\Admin\Admin_Page;
use PixelScout\Frontend\Shortcode;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class — bootstraps all services and hooks.
 */
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
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade_db' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'init', array( $this, 'register_hooks' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
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
		$pipeline     = new Search_Pipeline( $repository, $factory, $calculator );
		$validator    = new Mime_Validator();
		$indexer      = new Image_Indexer( $validator, $factory, $repository );
		$bulk_indexer = new Bulk_Indexer( $repository, $indexer, new Index_Progress(), new Action_Scheduler() );
		$controller   = new REST_Controller(
			$pipeline,
			new Query_Image(),
			$repository,
			$bulk_indexer,
			new Index_Progress(),
			new Rate_Limiter()
		);
		$controller->register_routes();

		$dup_progress   = new Duplicate_Progress();
		$dup_finder     = new Duplicate_Finder( $similarity );
		$dup_scanner    = new Duplicate_Scanner( $repository, $dup_finder, $dup_progress, new Action_Scheduler() );
		$dup_controller = new Duplicates_REST_Controller( $dup_scanner, $dup_progress, $repository );
		$dup_controller->register_routes();
	}

	/**
	 * Register indexing domain hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		global $wpdb;
		$repository   = new Index_Repository( $wpdb );
		$similarity   = new Similarity();
		$validator    = new Mime_Validator();
		$loader       = new GD_Loader();
		$factory      = new Fingerprint_Factory(
			$loader,
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);
		$indexer      = new Image_Indexer( $validator, $factory, $repository );
		$bulk_indexer = new Bulk_Indexer( $repository, $indexer, new Index_Progress(), new Action_Scheduler() );

		( new Media_Hooks( $indexer ) )->register();
		( new Cron_Handler( $bulk_indexer ) )->register();

		$dup_progress = new Duplicate_Progress();
		$dup_finder   = new Duplicate_Finder( $similarity );
		$dup_scanner  = new Duplicate_Scanner( $repository, $dup_finder, $dup_progress, new Action_Scheduler() );
		( new Duplicate_Cron_Handler( $dup_scanner ) )->register();
		add_action(
			Duplicate_Scanner::DAILY_HOOK,
			static function () use ( $dup_scanner ) {
				$dup_scanner->schedule();
			}
		);
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
	 * Log a message when WP_DEBUG is enabled.
	 *
	 * @param string $message Message to log.
	 *
	 * @return void
	 */
	private static function debug_log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Pixel Scout] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Handle plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::instance()->schema->install();
		self::instance()->schema->maybe_upgrade();

		if ( ! wp_next_scheduled( Duplicate_Scanner::DAILY_HOOK ) ) {
			wp_schedule_event( time(), 'daily', Duplicate_Scanner::DAILY_HOOK );
		}

		self::debug_log( 'Plugin activation complete.' );
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'ps_bulk_index_batch' );
		wp_clear_scheduled_hook( Duplicate_Scanner::CRON_HOOK );
		wp_clear_scheduled_hook( Duplicate_Scanner::DAILY_HOOK );
	}

	/**
	 * Handle plugin uninstall.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		$schema = new Schema();
		$schema->uninstall();

		( new Index_Progress() )->reset();
		( new Duplicate_Progress() )->reset();

		delete_option( 'ps_settings' );
		delete_option( PIXEL_SCOUT_OPTION_DB_VERSION );
		delete_option( 'ps_duplicate_results' );
		delete_option( 'ps_duplicate_last_scanned' );

		delete_transient( Bulk_Indexer::PENDING_KEY );
		delete_transient( 'ps_duplicate_scan_state' );
	}
}
