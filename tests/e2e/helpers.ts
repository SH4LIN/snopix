/**
 * Snopix e2e test helpers.
 *
 * Assumes a wp-env dev site running at http://localhost:8000 with the default
 * admin credentials (admin / password). All UI selectors are best-effort and
 * may need adjustment on first run if WordPress or a theme alters the markup.
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

/**
 * Upload an image into the WordPress Media Library via the browser uploader.
 *
 * Navigates directly to the browser-uploader variant of media-new.php so
 * no link click is required (the link is hidden in newer WordPress versions).
 */
export async function uploadMedia(page: Page, fileAbsPath: string): Promise<void> {
	await login(page);
	await page.goto('/wp-admin/media-new.php?browser-uploader');

	const fileInput = page.locator('input[type="file"]#async-upload, input[type="file"][name="async-upload"]');
	await fileInput.waitFor({ state: 'attached', timeout: 10_000 });
	await fileInput.setInputFiles(fileAbsPath);

	const submitBtn = page.locator('#html-upload');
	if (await submitBtn.count() > 0) {
		await submitBtn.click();
	}

	await expect(
		page.locator('.media-item .filename, #media-items .media-item, .updated.notice').first()
	).toBeVisible({ timeout: 30_000 });
}

/**
 * Create and publish a new WordPress post whose body is the given shortcode.
 * Returns the public permalink URL of the published post.
 */
export async function createPostWithShortcode(page: Page, shortcode: string): Promise<string> {
	await login(page);
	await page.goto('/wp-admin/post-new.php');

	const blockEditor = page.locator('.block-editor-writing-flow, .edit-post-layout');
	const classicEditor = page.locator('#content');

	const isBlockEditor = await blockEditor.count() > 0;

	if (isBlockEditor) {
		// Dismiss any blocking modal (e.g. "Welcome to the block editor").
		const modal = page.locator('.components-modal__screen-overlay');
		if (await modal.isVisible({ timeout: 3_000 }).catch(() => false)) {
			await page.keyboard.press('Escape');
			await modal.waitFor({ state: 'hidden', timeout: 5_000 }).catch(() => {});
		}

		const optionsMenuBtn = page.locator(
			'button[aria-label="Options"], button[aria-label="Editor options"]'
		).first();
		await optionsMenuBtn.waitFor({ state: 'visible', timeout: 10_000 });
		await optionsMenuBtn.click();

		const codeEditorItem = page.locator(
			'button:has-text("Code editor"), a:has-text("Code editor")'
		).first();
		await codeEditorItem.waitFor({ state: 'visible', timeout: 5_000 });
		await codeEditorItem.click();

		const codeArea = page.locator('.editor-post-text-editor, textarea.editor-post-text-editor');
		await codeArea.waitFor({ state: 'visible', timeout: 10_000 });
		await codeArea.fill(shortcode);

		await _publishBlockEditorPost(page);
	} else {
		await classicEditor.waitFor({ state: 'visible', timeout: 10_000 });
		await classicEditor.fill(shortcode);
		await page.locator('#publish').click();
		await page.waitForURL(/\/wp-admin\/post\.php\?post=\d+&action=edit/, { timeout: 20_000 });
	}

	return await _extractPermalink(page);
}

async function _publishBlockEditorPost(page: Page): Promise<void> {
	const publishBtn = page.locator(
		'button.editor-post-publish-button, button[aria-label="Publish"]'
	).first();
	await publishBtn.waitFor({ state: 'visible', timeout: 10_000 });
	await publishBtn.click();

	const confirmBtn = page.locator(
		'.editor-post-publish-panel button.editor-post-publish-button,' +
		' button[aria-label="Publish"]:not([disabled])'
	).last();

	try {
		await confirmBtn.waitFor({ state: 'visible', timeout: 5_000 });
		await confirmBtn.click();
	} catch {
		// Panel didn't appear — post may have published on the first click.
	}

	await expect(
		page.locator('.editor-post-publish-panel__postpublish, .components-notice.is-success').first()
	).toBeVisible({ timeout: 20_000 });
}

async function _extractPermalink(page: Page): Promise<string> {
	const viewPostLink = page.locator(
		'.editor-post-publish-panel__postpublish a, ' +
		'a:has-text("View Post"), ' +
		'a:has-text("View page")'
	).first();

	if (await viewPostLink.count() > 0) {
		const href = await viewPostLink.getAttribute('href');
		if (href) return href;
	}

	const classicViewLink = page.locator('#message a, #edit-slug-box a').first();
	if (await classicViewLink.count() > 0) {
		const href = await classicViewLink.getAttribute('href');
		if (href) return href;
	}

	const slugBox = page.locator('#sample-permalink');
	if (await slugBox.count() > 0) {
		const text = await slugBox.innerText();
		const match = text.match(/https?:\/\/\S+/);
		if (match) return match[0];
	}

	throw new Error('Could not determine permalink after publishing post.');
}
