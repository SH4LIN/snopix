import type { Page } from '@playwright/test';
import * as path from 'path';
import { execFileSync } from 'child_process';

export const WP_USER     = process.env.WP_ADMIN_USER     ?? 'admin';
export const WP_PASS     = process.env.WP_ADMIN_PASSWORD ?? 'password';
export const FIXTURES    = path.join( __dirname, '../fixtures/images' );

/**
 * Best-effort detection of the WP-CLI container name in the current Docker
 * environment. Probes `docker ps` for a name containing `cli` and excludes the
 * `tests` container so the helper doesn't shell into the test runner itself.
 * Empty when no container is found or Docker is unavailable — callers must
 * guard against the empty case before invoking WP-CLI.
 */
export const WP_CLI_CONTAINER: string = ( () => {
	try {
		return execFileSync( 'docker', [ 'ps', '--format', '{{.Names}}' ] )
			.toString()
			.split( '\n' )
			.find( n => n.includes( 'cli' ) && ! n.includes( 'tests' ) )
			?.trim() ?? '';
	} catch {
		return '';
	}
} )();

/**
 * Sign into wp-admin using the configured WP_USER / WP_PASS credentials.
 *
 * @param {Page} page Playwright page instance for the current test.
 *
 * @return {Promise<void>} Resolves once the admin dashboard has loaded.
 */
export async function wpLogin( page: Page ): Promise<void> {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', WP_USER );
	await page.fill( '#user_pass', WP_PASS );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

/**
 * Extract the REST API nonce exposed by `wpApiSettings` on any wp-admin page.
 *
 * @param {Page} page Playwright page instance for the current test.
 *
 * @return {Promise<string>} Nonce string, or the empty string when not exposed.
 */
export async function getRestNonce( page: Page ): Promise<string> {
	await page.goto( '/wp-admin/' );
	return page.evaluate( () => ( window as any ).wpApiSettings?.nonce ?? '' );
}

/**
 * Run a WP-CLI command inside the test docker container.
 *
 * Silently no-ops when no CLI container is detected so tests can run on hosts
 * without Docker. Errors during execution are swallowed because cron triggers
 * are best-effort.
 *
 * @param {...string} args CLI arguments forwarded to `wp` inside the container.
 *
 * @return {void}
 */
export function runCron( ...args: string[] ): void {
	if ( ! WP_CLI_CONTAINER ) return;
	try {
		execFileSync(
			'docker',
			[ 'exec', WP_CLI_CONTAINER, 'wp', '--allow-root', '--path=/var/www/html', ...args ],
			{ stdio: 'ignore' }
		);
	} catch {
		// cron errors are non-fatal
	}
}
