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
use Snopix\Admin\Media_Surfaces;
use Snopix\Frontend\Shortcode;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class - bootstraps all services and hooks.
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
	 * Whether the shared service graph has been built this request.
	 *
	 * @var bool
	 */
	private bool $services_built = false;

	/**
	 * Shared index repository.
	 *
	 * @var Index_Repository
	 */
	private Index_Repository $repository;

	/**
	 * Shared similarity metrics provider.
	 *
	 * @var Similarity
	 */
	private Similarity $similarity;

	/**
	 * Shared fingerprint factory.
	 *
	 * @var Fingerprint_Factory
	 */
	private Fingerprint_Factory $factory;

	/**
	 * Shared MIME validator.
	 *
	 * @var Mime_Validator
	 */
	private Mime_Validator $validator;

	/**
	 * Shared bulk-index progress tracker.
	 *
	 * @var Index_Progress
	 */
	private Index_Progress $index_progress;

	/**
	 * Shared single-image indexer.
	 *
	 * @var Image_Indexer
	 */
	private Image_Indexer $indexer;

	/**
	 * Shared bulk indexer.
	 *
	 * @var Bulk_Indexer
	 */
	private Bulk_Indexer $bulk_indexer;

	/**
	 * Shared duplicate-scan progress tracker.
	 *
	 * @var Duplicate_Progress
	 */
	private Duplicate_Progress $dup_progress;

	/**
	 * Shared duplicate finder.
	 *
	 * @var Duplicate_Finder
	 */
	private Duplicate_Finder $dup_finder;

	/**
	 * Shared duplicate scanner.
	 *
	 * @var Duplicate_Scanner
	 */
	private Duplicate_Scanner $dup_scanner;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->schema = new Schema();
	}

	/**
	 * Build the shared service graph once per request.
	 *
	 * Both the REST-route and hook registrars need overlapping services
	 * (repository, fingerprint factory, indexers, duplicate scanner). On a REST
	 * request both `init` and `rest_api_init` fire, so without memoisation the
	 * whole graph would be constructed twice. The objects are lightweight and do
	 * no DB work in their constructors, but building them once keeps the wiring
	 * single-sourced.
	 *
	 * @return void
	 */
	private function build_services(): void {
		if ( $this->services_built ) {
			return;
		}

		global $wpdb;
		$this->repository     = new Index_Repository( $wpdb );
		$this->similarity     = new Similarity();
		$this->factory        = new Fingerprint_Factory(
			new GD_Loader(),
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);
		$this->validator      = new Mime_Validator();
		$this->index_progress = new Index_Progress();
		$this->indexer        = new Image_Indexer( $this->validator, $this->factory, $this->repository );
		$this->bulk_indexer   = new Bulk_Indexer( $this->repository, $this->indexer, $this->index_progress, new Action_Scheduler() );
		$this->dup_progress   = new Duplicate_Progress();
		$this->dup_finder     = new Duplicate_Finder( $this->similarity );
		$this->dup_scanner    = new Duplicate_Scanner( $this->repository, $this->dup_finder, $this->dup_progress, new Action_Scheduler() );

		$this->services_built = true;
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
		add_action( 'admin_init', array( $this, 'register_media_surfaces' ) );
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
	 * Register the "Search by image" integration into the WP media surfaces.
	 *
	 * @return void
	 */
	public function register_media_surfaces(): void {
		( new Media_Surfaces() )->register();
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
		$this->build_services();

		$calculator = new Score_Calculator( $this->similarity );
		$pipeline   = new Search_Pipeline( $this->repository, $this->factory, $calculator );
		$controller = new REST_Controller(
			$pipeline,
			new Query_Image(),
			$this->repository,
			$this->bulk_indexer,
			$this->index_progress,
			new Rate_Limiter()
		);
		$controller->register_routes();

		$dup_controller = new Duplicates_REST_Controller( $this->dup_scanner, $this->dup_progress );
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
		$this->build_services();

		( new Media_Hooks( $this->indexer ) )->register();
		( new Cron_Handler( $this->bulk_indexer ) )->register();

		( new Duplicate_Cron_Handler( $this->dup_scanner ) )->register();

		$dup_scanner  = $this->dup_scanner;
		$dup_progress = $this->dup_progress;
		add_action(
			Duplicate_Scanner::DAILY_HOOK,
			static function () use ( $dup_scanner, $dup_progress ) {
				// Don't restart (and discard the progress of) a scan that is
				// already running when the daily event fires.
				if ( Job_Status::RUNNING === $dup_progress->get()['status'] ) {
					return;
				}
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
			// finished or skipped it - otherwise a deactivate/reactivate cycle
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
		global $wpdb;

		// Read the cleanup preference BEFORE we drop the option - otherwise we
		// always see the default value once the row is gone.
		if ( ! Settings::should_drop_on_uninstall() ) {
			// User opted out - leave everything so a reinstall resumes where it left off.
			return;
		}

		( new Index_Progress() )->reset();
		( new Duplicate_Progress() )->reset();

		delete_transient( Bulk_Indexer::PENDING_KEY );
		delete_transient( 'snopix_duplicate_scan_state' );

		// Wildcard-delete ephemeral transients (rate-limiter + activation redirect)
		// that can't be removed by key because they're namespaced by IP / user-ID.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM `{$wpdb->options}`
			WHERE `option_name` LIKE '_transient_snopix_ratelimit_%'
			   OR `option_name` LIKE '_transient_timeout_snopix_ratelimit_%'
			   OR `option_name` LIKE '_transient_snopix_activation_redirect_%'
			   OR `option_name` LIKE '_transient_timeout_snopix_activation_redirect_%'"
		);

		wp_clear_scheduled_hook( Bulk_Indexer::CRON_HOOK );
		wp_clear_scheduled_hook( Duplicate_Scanner::CRON_HOOK );
		wp_clear_scheduled_hook( Duplicate_Scanner::DAILY_HOOK );

		$schema = new Schema();
		$schema->uninstall();

		// Wipe per-user state across every user - dismissed notifications and
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
