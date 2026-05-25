<?php
/**
 * Admin page registration and asset enqueueing.
 *
 * @package Snopix
 */

namespace Snopix\Admin;

use Snopix\Hooks\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Handles WordPress admin menu registration and script/style loading.
 */
class Admin_Page {

	/**
	 * Plugin basename (e.g. `snopix/snopix.php`) for matching the row on the
	 * plugins screen. Defined lazily so the constant is resolved after the
	 * plugin's bootstrap file has run.
	 *
	 * @return string
	 */
	private function plugin_basename(): string {
		return defined( 'SNOPIX_PLUGIN_BASENAME' )
			? (string) SNOPIX_PLUGIN_BASENAME
			: plugin_basename( SNOPIX_PLUGIN_DIR . 'snopix.php' );
	}

	/**
	 * Register the admin menu page and enqueue hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_media_page(
			__( 'Snopix', 'snopix' ),
			__( 'Snopix', 'snopix' ),
			'manage_options',
			'snopix',
			array( $this, 'render' )
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_print_footer_scripts-plugins.php', array( $this, 'print_uninstall_guard' ) );
		add_filter( 'plugin_action_links_' . $this->plugin_basename(), array( $this, 'add_dashboard_link' ) );
	}

	/**
	 * Prepend a "Dashboard" link to the plugin's row on the Plugins screen.
	 *
	 * @param array<string, string> $links Existing action links.
	 *
	 * @return array<string, string>
	 */
	public function add_dashboard_link( $links ): array {
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$dashboard_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'upload.php?page=snopix' ) ),
			esc_html__( 'Dashboard', 'snopix' )
		);

		return array_merge( array( 'dashboard' => $dashboard_link ), $links );
	}

	/**
	 * Enqueue admin assets on the Snopix page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, 'snopix' ) ) {
			return;
		}

		wp_enqueue_style(
			'snopix-admin',
			SNOPIX_PLUGIN_URL . 'admin/app/dist/snopix-admin.css',
			array(),
			SNOPIX_VERSION
		);

		wp_enqueue_script(
			'snopix-admin',
			SNOPIX_PLUGIN_URL . 'admin/app/dist/snopix-admin.js',
			// wp-api-fetch is core's REST helper; declaring it here guarantees
			// the `wp.apiFetch` global is loaded before our bundle boots so the
			// shared `@wordpress/api-fetch` import resolves to the same
			// already-initialised instance instead of a duplicate.
			array( 'wp-api-fetch' ),
			SNOPIX_VERSION,
			true
		);

		wp_localize_script(
			'snopix-admin',
			'snopix_data',
			array(
				'rest_url' => esc_url_raw( rest_url( 'snopix/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'is_admin' => current_user_can( 'manage_options' ),
			)
		);

		wp_set_script_translations( 'snopix-admin', 'snopix', SNOPIX_PLUGIN_DIR . 'languages' );
	}

	/**
	 * Render the admin page mount point.
	 *
	 * @return void
	 */
	public function render(): void {
		require SNOPIX_PLUGIN_DIR . 'admin/app/views/admin-root.php';
	}

	/**
	 * Print a small footer script on the Plugins screen that intercepts the
	 * Snopix "Delete" link when the admin has opted into uninstall consent.
	 *
	 * Implemented as a JS confirm() because WordPress does not expose a
	 * server-side hook that runs between the user clicking Delete and the
	 * uninstaller firing. The confirm is best-effort UX, not a security
	 * boundary — the real cleanup decision still lives in the server-side
	 * `drop_on_uninstall` flag.
	 *
	 * @return void
	 */
	public function print_uninstall_guard(): void {
		if ( ! Settings::should_require_consent() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$basename = $this->plugin_basename();
		$message  = __(
			"Are you sure you want to delete Snopix?\nThe fingerprint table and every plugin option will be removed.",
			'snopix'
		);

		?>
		<script>
		(function () {
			var basename = <?php echo wp_json_encode( $basename ); ?>;
			var message  = <?php echo wp_json_encode( $message ); ?>;
			document.addEventListener('click', function (e) {
				var link = e.target.closest('a.delete[href*="action=delete-selected"], a.delete[href*="action=delete-plugin"]');
				if (!link) {
					return;
				}
				if (link.href.indexOf(encodeURIComponent(basename)) === -1 && link.href.indexOf(basename) === -1) {
					return;
				}
				if (!window.confirm(message)) {
					e.preventDefault();
					e.stopImmediatePropagation();
				}
			}, true);
		})();
		</script>
		<?php
	}
}
