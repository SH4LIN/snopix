/**
 * Snopix e2e test helpers — browser-side utilities.
 *
 * REST API operations (upload media, create posts) use `requestUtils` from
 * `./fixtures` instead of browser UI flows. Only helpers that require an
 * actual browser page live here.
 *
 * Assumes a wp-env dev site running at http://localhost:8000 with the default
 * admin credentials (admin / password).
 */

import path from 'path';
import { Page, expect } from '@playwright/test';

export const SNOPIX_ADMIN_URL = '/wp-admin/upload.php?page=snopix';

/**
 * Resolve an absolute path to a file under tests/fixtures/images.
 * helpers.ts lives in tests/e2e/, so we go one level up.
 *
 * @example fixturePath('001.jpg')
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
 * Waits for a child element inside #snopix-root to confirm the app has
 * mounted, then dismisses the first-run tour if it is blocking the UI.
 */
export async function gotoSnopix(page: Page): Promise<void> {
	await login(page);
	await page.goto(SNOPIX_ADMIN_URL);

	// Wait for at least one child node inside #snopix-root to confirm the
	// router + components have rendered.
	await expect(page.locator('#snopix-root > *').first()).toBeVisible({ timeout: 15_000 });
	await dismissTour(page);
}

/**
 * Dismiss the Snopix first-run tour if it is currently blocking the UI.
 *
 * The tour renders a solid overlay that intercepts pointer events on every
 * element beneath it. Clicking "Skip tour" removes it immediately. Safe to
 * call when the tour is not open — the check is a no-op in that case.
 */
export async function dismissTour(page: Page): Promise<void> {
	const skipBtn = page.locator('.snopix-tour__skip');
	const visible = await skipBtn.isVisible({ timeout: 3_000 }).catch(() => false);
	if (visible) {
		await skipBtn.click();
		await page.locator('.snopix-tour').waitFor({ state: 'hidden', timeout: 5_000 });
	}
}
