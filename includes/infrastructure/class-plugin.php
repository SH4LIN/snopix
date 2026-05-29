<?php
/**
 * Plugin bootstrap and lifecycle handlers.
 *
 * @package Snopix
 */

namespace Snopix\Infrastructure;

use Snopix\Repository\{Index_Repository, Schema};
use Snopix\Imaging\{GD_Loader, PHash_Processor, Color_Processor, Edge_Processor, Similarity};
use Snopix\Search\{Fingerprint_Factory, Query_Image, Score_Calculator, Search_Pipeline};
use Snopix\Indexing\{Mime_Validator, Index_Progress, Image_Indexer, Bulk_Indexer};
use Snopix\Hooks\{Media_Hooks, Cron_Handler, Settings};
use Snopix\Api\{Rate_Limiter, REST_Controller, Duplicates_REST_Controller, Notifications_REST_Controller};
use Snopix\Duplicates\{Duplicate_Progress, Duplicate_Finder, Duplicate_Scanner, Duplicate_Cron_Handler};
use Snopix\Notifications\Feature_Notification_Store;
use Snopix\Admin\Admin_Page;
use Snopix\Admin\Editor_Assets;
use Snopix\Frontend\Shortcode;
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
		add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'init', array( $this, 'register_editor_assets' ) );
	}

	/**
	 * Register block-editor asset enqueueing for the shortcode inspector
	 * panel.
	 *
	 * @return void
	 */
	public function register_editor_assets(): void {
		( new Editor_Assets() )->register();
	}

	/**
	 * Register the Snopix admin page.
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
		$dup_controller = new Duplicates_REST_Controller( $dup_scanner, $dup_progress );
		$dup_controller->register_routes();

		$notifications_controller = new Notifications_REST_Controller( new Feature_Notification_Store() );
		$notifications_controller->register_routes();
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

		$user_id = get_current_user_id();
		if ( $user_id ) {
			// Only auto-open the onboarding tour for users who have not already
			// finished or skipped it — otherwise a deactivate/reactivate cycle
			// would re-trigger the walkthrough they have already seen.
			$tour = get_user_meta( $user_id, 'snopix_tour_completed', true );
			if ( 'completed' !== $tour && 'skipped' !== $tour ) {
				set_transient( 'snopix_activation_redirect_' . $user_id, 1, 30 );
			}
		}

		Logger::debug( 'Plugin activation complete.' );
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'snopix_bulk_index_batch' );
		wp_clear_scheduled_hook( Duplicate_Scanner::CRON_HOOK );
		wp_clear_scheduled_hook( Duplicate_Scanner::DAILY_HOOK );
	}

	/**
	 * Handle plugin uninstall.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		// Read the cleanup preference BEFORE we drop the option — otherwise we
		// always see the default value once the row is gone.
		$should_drop = Settings::should_drop_on_uninstall();

		( new Index_Progress() )->reset();
		( new Duplicate_Progress() )->reset();

		delete_transient( Bulk_Indexer::PENDING_KEY );
		delete_transient( 'snopix_duplicate_scan_state' );

		if ( ! $should_drop ) {
			// User opted out of destructive uninstall — leave the table, the
			// settings row, and any cached duplicate results so a reinstall
			// resumes where they left off.
			return;
		}

		$schema = new Schema();
		$schema->uninstall();

		// Wipe per-user state across every user — dismissed notifications and
		// tour completion flags would otherwise survive a destructive uninstall
		// and pollute a fresh reinstall's onboarding.
		delete_metadata( 'user', 0, 'snopix_tour_completed', '', true );
		delete_metadata( 'user', 0, 'snopix_dismissed_notifications', '', true );

		delete_option( Settings::OPTION_NAME );
		delete_option( SNOPIX_OPTION_DB_VERSION );
		delete_option( 'snopix_duplicate_results' );
		delete_option( 'snopix_duplicate_last_scanned' );
	}

	/**
	 * Redirect newly-activating admins to the Snopix admin page with ?tour=1
	 * so the first-run walkthrough auto-opens.
	 *
	 * Guards: not AJAX, not network admin, current user can manage_options,
	 * not a bulk plugin activation, and the activation transient exists.
	 *
	 * @return void
	 */
	public function maybe_redirect_after_activation(): void {
		if ( wp_doing_ajax() ) {
			return;
		}
		if ( is_network_admin() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$transient_key = 'snopix_activation_redirect_' . $user_id;
		if ( ! get_transient( $transient_key ) ) {
			return;
		}

		delete_transient( $transient_key );

		wp_safe_redirect( admin_url( 'upload.php?page=snopix&tour=1' ) );
		exit;
	}
}
