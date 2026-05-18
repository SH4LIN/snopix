import type { Page } from '@playwright/test';
import * as path from 'path';
import { execFileSync } from 'child_process';

export const WP_USER     = process.env.WP_ADMIN_USER     ?? 'admin';
export const WP_PASS     = process.env.WP_ADMIN_PASSWORD ?? 'password';
export const FIXTURES    = path.join( __dirname, '../fixtures/images' );

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

export async function wpLogin( page: Page ): Promise<void> {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', WP_USER );
	await page.fill( '#user_pass', WP_PASS );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

export async function getRestNonce( page: Page ): Promise<string> {
	await page.goto( '/wp-admin/' );
	return page.evaluate( () => ( window as any ).wpApiSettings?.nonce ?? '' );
}

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
