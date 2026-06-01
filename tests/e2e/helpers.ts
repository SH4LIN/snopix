/**
 * Snopix e2e test helpers.
 *
 * Assumes a wp-env dev site running at http://localhost:8000 with the default
 * admin credentials (admin / password). All UI selectors are best-effort and
 * may need adjustment on first run if WordPress or a theme alters the markup.
 */

import path from 'path';
import { Page, expect } from '@playwright/test';

export const ADMIN = { user: 'admin', pass: 'password' };

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
	// Check if we're already logged in by looking for the WP admin bar.
	const adminBar = page.locator('#wpadminbar');
	const alreadyLoggedIn = await adminBar.count() > 0;
	if (alreadyLoggedIn) {
		return;
	}

	await page.goto('/wp-login.php');
	await page.locator('#user_login').fill(ADMIN.user);
	await page.locator('#user_pass').fill(ADMIN.pass);
	await page.locator('#wp-submit').click();

	// Wait until we land somewhere inside /wp-admin/
	await page.waitForURL(/\/wp-admin\//);
}

/**
 * Navigate to the Snopix admin page and wait for the React SPA to render.
 * Waits for a child element inside #snopix-root (not just the container itself)
 * to confirm the app has mounted.
 */
export async function gotoSnopix(page: Page): Promise<void> {
	await login(page);
	await page.goto(SNOPIX_ADMIN_URL);

	// The React app mounts into #snopix-root. Wait for at least one child node
	// to appear, which confirms the router + components have rendered.
	await expect(page.locator('#snopix-root > *').first()).toBeVisible({ timeout: 15_000 });
}

/**
 * Upload an image into the WordPress Media Library via /wp-admin/media-new.php.
 *
 * Uses the classic browser-uploader fallback (plupload flash/silverlight is
 * skipped in headless). If the "Browser uploader" fallback link is present,
 * clicks it first; then sets the file on the hidden file input and waits for
 * the upload confirmation row to appear.
 */
export async function uploadMedia(page: Page, fileAbsPath: string): Promise<void> {
	await login(page);
	await page.goto('/wp-admin/media-new.php');

	// Switch to the classic browser uploader if the link exists.
	const browserUploaderLink = page.locator('a#browsercontent, a:has-text("browser uploader")');
	if (await browserUploaderLink.count() > 0) {
		await browserUploaderLink.first().click();
	}

	// The classic uploader exposes a plain file input.
	const fileInput = page.locator('input[type="file"]#async-upload, input[type="file"][name="async-upload"]');
	await fileInput.waitFor({ state: 'attached', timeout: 10_000 });
	await fileInput.setInputFiles(fileAbsPath);

	// Submit the classic upload form.
	const submitBtn = page.locator('#html-upload');
	if (await submitBtn.count() > 0) {
		await submitBtn.click();
	}

	// Wait for the uploaded attachment row or success message to appear.
	// WP shows either a table row with the filename or a "Media added" notice.
	await expect(
		page.locator('.media-item .filename, #media-items .media-item, .updated.notice')
			.first()
	).toBeVisible({ timeout: 30_000 });
}

/**
 * Create and publish a new WordPress post whose body is the given shortcode.
 * Returns the public permalink URL of the published post.
 *
 * Strategy: opens wp-admin/post-new.php, switches to the Code Editor if the
 * block editor is active, pastes the shortcode, publishes, and captures the
 * permalink. Falls back gracefully if the classic editor is already in use.
 */
export async function createPostWithShortcode(page: Page, shortcode: string): Promise<string> {
	await login(page);
	await page.goto('/wp-admin/post-new.php');

	// Detect which editor is active.
	const blockEditor = page.locator('.block-editor-writing-flow, .edit-post-layout');
	const classicEditor = page.locator('#content');

	const isBlockEditor = await blockEditor.count() > 0;

	if (isBlockEditor) {
		// Open the Options (⋮) menu to find "Code editor".
		// WP 5.x+ keeps this in the editor toolbar's three-dot menu.
		const optionsMenuBtn = page.locator(
			'button[aria-label="Options"], button[aria-label="Editor options"]'
		).first();
		await optionsMenuBtn.waitFor({ state: 'visible', timeout: 10_000 });
		await optionsMenuBtn.click();

		// Click "Code editor" in the dropdown.
		const codeEditorItem = page.locator(
			'button:has-text("Code editor"), a:has-text("Code editor")'
		).first();
		await codeEditorItem.waitFor({ state: 'visible', timeout: 5_000 });
		await codeEditorItem.click();

		// The code editor textarea should now be present.
		const codeArea = page.locator('.editor-post-text-editor, textarea.editor-post-text-editor');
		await codeArea.waitFor({ state: 'visible', timeout: 10_000 });
		await codeArea.fill(shortcode);

		// Publish the post.
		await _publishBlockEditorPost(page);
	} else {
		// Classic editor path.
		await classicEditor.waitFor({ state: 'visible', timeout: 10_000 });
		await classicEditor.fill(shortcode);

		// Click Publish.
		await page.locator('#publish').click();
		await page.waitForURL(/\/wp-admin\/post\.php\?post=\d+&action=edit/, { timeout: 20_000 });
	}

	// Extract the permalink from the post-editor page.
	return await _extractPermalink(page);
}

/**
 * Publish a post in the block editor by clicking the Publish button twice
 * (WP asks for confirmation on first click, then confirms on second).
 */
async function _publishBlockEditorPost(page: Page): Promise<void> {
	// First "Publish" button in the header toolbar.
	const publishBtn = page.locator(
		'button.editor-post-publish-button, button[aria-label="Publish"]'
	).first();
	await publishBtn.waitFor({ state: 'visible', timeout: 10_000 });
	await publishBtn.click();

	// WP may show a pre-publish panel; click the final "Publish" there.
	const confirmBtn = page.locator(
		'.editor-post-publish-panel button.editor-post-publish-button,' +
		' button[aria-label="Publish"]:not([disabled])'
	).last();

	// Wait briefly to see if the panel appeared.
	try {
		await confirmBtn.waitFor({ state: 'visible', timeout: 5_000 });
		await confirmBtn.click();
	} catch {
		// Panel didn't appear — post may have published on the first click.
	}

	// Wait for the "published" notice.
	await expect(
		page.locator('.editor-post-publish-panel__postpublish, .components-notice.is-success')
			.first()
	).toBeVisible({ timeout: 20_000 });
}

/**
 * Extract the public permalink from wherever WP renders it after publish.
 * Checks the post-publish panel, the classic editor's View Post link,
 * and the #sample-permalink area, in that order.
 */
async function _extractPermalink(page: Page): Promise<string> {
	// Block editor: post-publish panel shows a "View Post" link.
	const viewPostLink = page.locator(
		'.editor-post-publish-panel__postpublish a, ' +
		'a:has-text("View Post"), ' +
		'a:has-text("View page")'
	).first();

	if (await viewPostLink.count() > 0) {
		const href = await viewPostLink.getAttribute('href');
		if (href) return href;
	}

	// Classic editor: "View Post" link in the notice bar.
	const classicViewLink = page.locator('#message a, #edit-slug-box a').first();
	if (await classicViewLink.count() > 0) {
		const href = await classicViewLink.getAttribute('href');
		if (href) return href;
	}

	// Fallback: read the permalink from the sample-permalink span.
	const slugBox = page.locator('#sample-permalink');
	if (await slugBox.count() > 0) {
		const text = await slugBox.innerText();
		// The span text is the URL minus the trailing slug placeholder.
		const match = text.match(/https?:\/\/\S+/);
		if (match) return match[0];
	}

	throw new Error('Could not determine permalink after publishing post.');
}
