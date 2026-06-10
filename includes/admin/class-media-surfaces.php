<?php
/**
 * "Search by image" integration into the WordPress media surfaces.
 *
 * Reuses the frontend search widget (`assets/search`) and the
 * `snopix/v1/search` endpoint - no new backend. This class only enqueues the
 * widget plus a small glue bundle (`assets/media`) on the right admin screens
 * and server-renders the toggle/panel for the Upload New Media page.
 *
 * @package Snopix
 */

namespace Snopix\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the search widget into the three admin media surfaces:
 *   - Upload New Media page (`media-new.php`)  - server-rendered toggle/panel.
 *   - Media modal (featured image / "Add Media") - wp.media router tab.
 *   - Media Library grid (`upload.php?mode=grid`) - injected trigger + panel.
 */
class Media_Surfaces {

	/**
	 * Widget chrome variant used across the admin surfaces.
	 */
	private const VARIANT = 'card';

	/**
	 * Result cap for the admin surfaces.
	 */
	private const MAX_RESULTS = 8;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		// Fired by media_upload_form() on media-new.php; guarded to that page.
		add_action( 'pre-upload-ui', array( $this, 'render_upload_toggle' ) );
		add_action( 'post-upload-ui', array( $this, 'render_upload_panel' ) );
	}

	/**
	 * Enqueue the widget + glue on the media surfaces only.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen ) {
			return;
		}

		switch ( $screen->base ) {
			case 'media': // media-new.php (Upload New Media).
			case 'upload': // upload.php (Media Library grid/list).
				$this->enqueue_widget();
				$this->enqueue_glue( array( 'snopix-search' ) );
				break;
			case 'post': // Post/page/CPT editor - the media modal lives here.
				wp_enqueue_media();
				$this->enqueue_widget();
				$this->enqueue_glue( array( 'snopix-search', 'media-views' ) );
				break;
			default:
				return;
		}
	}

	/**
	 * Enqueue the React search widget bundle + its REST config.
	 *
	 * @return void
	 */
	private function enqueue_widget(): void {
		wp_enqueue_style(
			'snopix-search',
			SNOPIX_PLUGIN_URL . 'assets/search/snopix-search.css',
			array(),
			SNOPIX_VERSION
		);
		wp_enqueue_script(
			'snopix-search',
			SNOPIX_PLUGIN_URL . 'assets/search/snopix-search.js',
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
	}

	/**
	 * Enqueue the admin media glue bundle + its config.
	 *
	 * @param array<int, string> $deps Script dependencies.
	 *
	 * @return void
	 */
	private function enqueue_glue( array $deps ): void {
		wp_enqueue_style(
			'snopix-media',
			SNOPIX_PLUGIN_URL . 'assets/media/snopix-media.css',
			array( 'snopix-search' ),
			SNOPIX_VERSION
		);
		wp_enqueue_script(
			'snopix-media',
			SNOPIX_PLUGIN_URL . 'assets/media/snopix-media.js',
			$deps,
			SNOPIX_VERSION,
			true
		);
		wp_localize_script(
			'snopix-media',
			'snopix_media',
			array(
				'variant'    => self::VARIANT,
				'maxResults' => self::MAX_RESULTS,
				'i18n'       => array(
					'trigger'    => __( 'Search by image', 'snopix' ),
					'panelTitle' => __( 'Search by image', 'snopix' ),
					'close'      => __( 'Close', 'snopix' ),
				),
			)
		);
	}

	/**
	 * Render the Upload / Search-by-image toggle above the native uploader.
	 *
	 * Hooked on `pre-upload-ui`, which also fires inside legacy media-modal
	 * upload tabs, so render only on the standalone Upload New Media page.
	 *
	 * @return void
	 */
	public function render_upload_toggle(): void {
		if ( ! $this->is_upload_page() ) {
			return;
		}
		?>
		<div id="snopix-upload-toggle" class="snopix-upload-toggle">
			<button type="button" data-mode="upload" class="is-active" aria-pressed="true">
				<?php esc_html_e( 'Upload files', 'snopix' ); ?>
			</button>
			<button type="button" data-mode="search" aria-pressed="false">
				<?php esc_html_e( 'Search by image', 'snopix' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render the (hidden) Snopix search panel below the native uploader.
	 *
	 * Hooked on `post-upload-ui`; the widget auto-boots into the
	 * `[data-snopix-search]` node since it is in the initial DOM.
	 *
	 * @return void
	 */
	public function render_upload_panel(): void {
		if ( ! $this->is_upload_page() ) {
			return;
		}
		printf(
			'<div id="snopix-upload-panel" class="snopix-upload-panel" hidden><div data-snopix-search data-variant="%1$s" data-max-results="%2$d"></div></div>',
			esc_attr( self::VARIANT ),
			esc_attr( self::MAX_RESULTS )
		);
	}

	/**
	 * Whether the current request is the standalone Upload New Media page.
	 *
	 * @return bool
	 */
	private function is_upload_page(): bool {
		return isset( $GLOBALS['pagenow'] ) && 'media-new.php' === $GLOBALS['pagenow'];
	}
}
