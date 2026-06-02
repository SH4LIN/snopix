/**
 * Snopix e2e test helpers.
 *
 * Assumes a wp-env dev site running at http://localhost:8000 with the default
 * admin credentials (admin / password).
 */

import fs from 'fs';
import path from 'path';
import { Page, expect } from '@playwright/test';

export const SNOPIX_ADMIN_URL = '/wp-admin/upload.php?page=snopix';

/**
 * Resolve an absolute path to a file under tests/fixtures/images.
 */
export function fixturePath(name: string): string {
	return path.resolve(__dirname, '../fixtures/images', name);
}

/**
 * Log in to WordPress as the default admin.
 * Idempotent: skips the login flow if the admin toolbar is already present.
 */
export async function login(page: Page): Promise<void> {
	const adminBar = page.locator('#wpadminbar');
	const alreadyLoggedIn = await adminBar.count() > 0;
	if (alreadyLoggedIn) {
		return;
	}

	await page.goto('/wp-login.php');
	await page.locator('#user_login').fill('admin');
	await page.locator('#user_pass').fill('password');
	await page.locator('#wp-submit').click();

	await page.waitForURL(/\/wp-admin\//);
}

/**
 * Navigate to the Snopix admin page and wait for the React SPA to render.
 * Dismisses the first-run tour if it is blocking the UI.
 */
export async function gotoSnopix(page: Page): Promise<void> {
	await login(page);
	await page.goto(SNOPIX_ADMIN_URL);

	await expect(page.locator('#snopix-root > *').first()).toBeVisible({ timeout: 15_000 });
	await dismissTour(page);
}

/**
 * Dismiss the Snopix first-run tour if it is currently blocking the UI.
 *
 * The tour renders a solid overlay that intercepts pointer events. Clicking
 * "Skip tour" removes it immediately. Safe to call when the tour is not open.
 */
export async function dismissTour(page: Page): Promise<void> {
	const skipBtn = page.locator('.snopix-tour__skip');
	const visible = await skipBtn.isVisible({ timeout: 3_000 }).catch(() => false);
	if (visible) {
		await skipBtn.click();
		await page.locator('.snopix-tour').waitFor({ state: 'hidden', timeout: 5_000 });
	}
}

/**
 * Navigate to an admin page and extract the WP REST API nonce from
 * wpApiSettings. The nonce is session-scoped and valid for ~12 h.
 */
async function getWpNonce(page: Page): Promise<string> {
	await page.goto('/wp-admin/');
	return page.evaluate(
		() =>
			(window as unknown as { wpApiSettings?: { nonce: string } })
				.wpApiSettings?.nonce ?? ''
	);
}

function mimeType(filePath: string): string {
	if (/\.jpe?g$/i.test(filePath)) return 'image/jpeg';
	if (/\.png$/i.test(filePath)) return 'image/png';
	if (/\.gif$/i.test(filePath)) return 'image/gif';
	if (/\.webp$/i.test(filePath)) return 'image/webp';
	return 'application/octet-stream';
}

/**
 * Upload an image to the WordPress Media Library via the REST API.
 *
 * Uses page.request (inherits the page's auth cookies) with the WP nonce,
 * avoiding browser-side upload UI that is hidden when JavaScript is active.
 */
export async function uploadMedia(page: Page, fileAbsPath: string): Promise<void> {
	await login(page);
	const nonce = await getWpNonce(page);
	const filename = path.basename(fileAbsPath);

	const res = await page.request.post('/wp-json/wp/v2/media', {
		headers: {
			'X-WP-Nonce': nonce,
			'Content-Disposition': `attachment; filename="${filename}"`,
			'Content-Type': mimeType(fileAbsPath),
		},
		data: fs.readFileSync(fileAbsPath),
	});

	if (!res.ok()) {
		throw new Error(`uploadMedia failed ${res.status()}: ${await res.text()}`);
	}
}

/**
 * Create and publish a WordPress post whose body is the given shortcode.
 * Returns the public permalink URL of the published post.
 *
 * Uses the REST API to avoid block-editor UI interactions that are sensitive
 * to WP version changes.
 */
export async function createPostWithShortcode(page: Page, shortcode: string): Promise<string> {
	await login(page);
	const nonce = await getWpNonce(page);

	const res = await page.request.post('/wp-json/wp/v2/posts', {
		headers: {
			'X-WP-Nonce': nonce,
			'Content-Type': 'application/json',
		},
		data: JSON.stringify({
			title: 'Snopix Search Test',
			content: shortcode,
			status: 'publish',
		}),
	});

	if (!res.ok()) {
		throw new Error(`createPostWithShortcode failed ${res.status()}: ${await res.text()}`);
	}

	const post = await res.json() as { link: string };
	return post.link;
}
