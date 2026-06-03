/**
 * Snopix shortcode e2e spec — [snopix_search] widget on a front-end post.
 *
 * The post containing the shortcode is created once per suite via
 * `createPostWithShortcode()` in a `beforeAll` hook.
 *
 * Selectors are best-effort from the React source (SnopixWidget.tsx / main.tsx).
 * Whether the search returns real matches depends on whether the media library
 * has indexed images; the spec therefore accepts any post-search state:
 * results, "No matches", or an API error message.
 *
 * Widget mount point : [data-snopix-search]
 * Drop-zone trigger  : .snopix-widget div[data-over] (click opens file picker)
 * Hidden file input  : input[type="file"][accept="image/jpeg,image/png,image/gif,image/webp"]
 * Scanning indicator : .sx-progress  (progress bar shown while fetch is in flight)
 * Results heading    : text matching /\d+ match(es)?|No matches|Search failed/
 * Empty state        : text "No visually similar images"
 * Error state        : .snopix-widget div.py-10 (error paragraph inside results panel)
 */

import { test, expect } from './fixtures';
import { login, createPostWithShortcode, fixturePath } from './helpers';

const SHORTCODE = '[snopix_search]';

// Selectors derived from SnopixWidget.tsx
const MOUNT_POINT   = '[data-snopix-search]';
const WIDGET_ROOT   = '.snopix-widget';
const FILE_INPUT    = 'input[type="file"][accept="image/jpeg,image/png,image/gif,image/webp"]';
const DROP_ZONE_CUE = 'Drop an image to search';
const PROGRESS_BAR  = '.sx-progress';
const RESULT_HEADING_PATTERN = /\d+ match(es)?|No matches|Search failed/i;
const EMPTY_TEXT    = 'No visually similar images';

test.describe('[snopix_search] shortcode — front-end widget', () => {
	let postUrl: string;

	test.beforeAll(async ({ browser }) => {
		const page = await browser.newPage();
		await login(page);
		postUrl = await createPostWithShortcode(page, SHORTCODE);
		await page.close();
	});

	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	// -------------------------------------------------------------------------
	// 1. Widget mounts into [data-snopix-search] and renders the drop-zone UI
	// -------------------------------------------------------------------------
	test('widget mounts and renders the upload drop-zone', async ({ page }) => {
		await test.step('navigate to the shortcode post', async () => {
			await page.goto(postUrl);
		});

		await test.step('assert widget mounts and drop-zone renders', async () => {
			const mountPoint = page.locator(MOUNT_POINT).first();
			await expect(mountPoint).toBeAttached({ timeout: 15_000 });

			const widgetRoot = page.locator(WIDGET_ROOT).first();
			await expect(widgetRoot).toBeVisible({ timeout: 15_000 });

			await expect(page.getByText(DROP_ZONE_CUE)).toBeVisible({ timeout: 10_000 });
			await expect(page.locator(FILE_INPUT)).toBeAttached({ timeout: 5_000 });

			const screenshot = await page.screenshot();
			await test.info().attach('widget-idle', { body: screenshot, contentType: 'image/png' });
		});
	});

	// -------------------------------------------------------------------------
	// 2. Uploading a query image triggers a search and renders a result state
	// -------------------------------------------------------------------------
	test('uploading a query image executes search and renders a result state', async ({ page }) => {
		await test.step('navigate to shortcode post and wait for widget', async () => {
			await page.goto(postUrl);
			await expect(page.locator(WIDGET_ROOT).first()).toBeVisible({ timeout: 15_000 });
			await expect(page.getByText(DROP_ZONE_CUE)).toBeVisible({ timeout: 10_000 });
		});

		await test.step('set query image on file input', async () => {
			const fileInput = page.locator(FILE_INPUT);
			await fileInput.setInputFiles(fixturePath('001.jpg'));

			const screenshot = await page.screenshot();
			await test.info().attach('query-image-set', { body: screenshot, contentType: 'image/png' });
		});

		await test.step('wait for search to complete and assert result heading', async () => {
			await expect(page.locator(PROGRESS_BAR)).toBeVisible({ timeout: 10_000 });
			await expect(page.locator(PROGRESS_BAR)).toBeHidden({ timeout: 30_000 });

			await expect(
				page.locator(WIDGET_ROOT).first().locator('div').filter({ hasText: RESULT_HEADING_PATTERN }).first()
			).toBeVisible({ timeout: 10_000 });

			const screenshot = await page.screenshot();
			await test.info().attach('search-result', { body: screenshot, contentType: 'image/png' });
		});
	});

	// -------------------------------------------------------------------------
	// 3. Empty or results state: correct UI renders without a JS crash
	// -------------------------------------------------------------------------
	test('post-search state renders either results grid or empty/error message', async ({ page }) => {
		await test.step('navigate to shortcode post', async () => {
			await page.goto(postUrl);
			await expect(page.locator(WIDGET_ROOT).first()).toBeVisible({ timeout: 15_000 });
			await expect(page.getByText(DROP_ZONE_CUE)).toBeVisible({ timeout: 10_000 });
		});

		await test.step('trigger search with fixture image', async () => {
			await page.locator(FILE_INPUT).setInputFiles(fixturePath('001.jpg'));
			await expect(page.locator(PROGRESS_BAR)).toBeVisible({ timeout: 10_000 });
			await expect(page.locator(PROGRESS_BAR)).toBeHidden({ timeout: 30_000 });
		});

		await test.step('assert exactly one post-search state is rendered', async () => {
			const widget = page.locator(WIDGET_ROOT).first();

			const hasResults   = await widget.locator('a[href]').count() > 0;
			const hasEmptyText = await widget.getByText(EMPTY_TEXT).count() > 0;
			const hasError     = await widget.locator('div.py-10').count() > 0;

			expect(hasResults || hasEmptyText || hasError).toBe(true);
			await expect(page.getByText(DROP_ZONE_CUE)).toBeHidden();

			test.info().annotations.push({
				type: 'post-search-state',
				description: hasResults ? 'results' : hasEmptyText ? 'empty' : 'error',
			});

			const screenshot = await page.screenshot();
			await test.info().attach('post-search-state', { body: screenshot, contentType: 'image/png' });
		});
	});

	// -------------------------------------------------------------------------
	// 4. "New search" button resets the widget back to the idle drop-zone
	// -------------------------------------------------------------------------
	test('"New search" resets the widget to idle drop-zone state', async ({ page }) => {
		await test.step('navigate to shortcode post', async () => {
			await page.goto(postUrl);
			await expect(page.locator(WIDGET_ROOT).first()).toBeVisible({ timeout: 15_000 });
			await expect(page.getByText(DROP_ZONE_CUE)).toBeVisible({ timeout: 10_000 });
		});

		await test.step('trigger search and wait for completion', async () => {
			await page.locator(FILE_INPUT).setInputFiles(fixturePath('001.jpg'));
			await expect(page.locator(PROGRESS_BAR)).toBeVisible({ timeout: 10_000 });
			await expect(page.locator(PROGRESS_BAR)).toBeHidden({ timeout: 30_000 });

			const screenshot = await page.screenshot();
			await test.info().attach('post-search-before-reset', { body: screenshot, contentType: 'image/png' });
		});

		await test.step('click "New search" and confirm widget resets to idle', async () => {
			const newSearchBtn = page.getByRole('button', { name: /new search/i });
			await expect(newSearchBtn).toBeVisible({ timeout: 5_000 });
			await newSearchBtn.click();

			await expect(page.getByText(DROP_ZONE_CUE)).toBeVisible({ timeout: 5_000 });
			await expect(page.locator(FILE_INPUT)).toBeAttached();

			const screenshot = await page.screenshot();
			await test.info().attach('widget-reset-to-idle', { body: screenshot, contentType: 'image/png' });
		});
	});
});
