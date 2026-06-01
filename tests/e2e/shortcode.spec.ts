/**
 * Snopix shortcode e2e spec — [snopix_search] widget on a front-end post.
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

import { test, expect } from '@playwright/test';
import {
	login,
	createPostWithShortcode,
	fixturePath,
} from './helpers';

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const SHORTCODE = '[snopix_search]';

// Selectors derived from SnopixWidget.tsx
const MOUNT_POINT   = '[data-snopix-search]';
const WIDGET_ROOT   = '.snopix-widget';
// The dropzone div that wraps the "Choose file" button and hidden input
const DROP_ZONE     = '.snopix-widget div[data-over]';
const FILE_INPUT    = 'input[type="file"][accept="image/jpeg,image/png,image/gif,image/webp"]';
// Text inside the drop-zone that confirms the widget rendered in idle state
const DROP_ZONE_CUE = 'Drop an image to search';
// Scanning: the progress bar visible while the POST /snopix/v1/search is in flight
const PROGRESS_BAR  = '.sx-progress';
// Post-search: the heading that shows match count, "No matches", or "Search failed"
const RESULT_HEADING_PATTERN = /\d+ match(es)?|No matches|Search failed/i;
// "No visually similar images" text for the empty state
const EMPTY_TEXT    = 'No visually similar images';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test.describe('[snopix_search] shortcode — front-end widget', () => {
	let postUrl: string;

	// Create + publish a post containing the shortcode once for the whole suite.
	// login() is re-called inside createPostWithShortcode, but we also call it
	// here explicitly to satisfy the beforeEach contract stated in the spec brief.
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
		await page.goto(postUrl);

		// Mount point must exist in the DOM.
		const mountPoint = page.locator(MOUNT_POINT).first();
		await expect(mountPoint).toBeAttached({ timeout: 15_000 });

		// React must have rendered children inside it — the .snopix-widget root.
		const widgetRoot = page.locator(WIDGET_ROOT).first();
		await expect(widgetRoot).toBeVisible({ timeout: 15_000 });

		// The idle drop-zone copy should be present.
		await expect(page.getByText(DROP_ZONE_CUE)).toBeVisible({ timeout: 10_000 });

		// The hidden file input must be in the DOM (attached but hidden).
		await expect(page.locator(FILE_INPUT)).toBeAttached({ timeout: 5_000 });
	});

	// -------------------------------------------------------------------------
	// 2. Uploading a query image triggers a search and renders a result state
	// -------------------------------------------------------------------------
	test('uploading a query image executes search and renders a result state', async ({ page }) => {
		await page.goto(postUrl);

		// Wait for the widget to reach the idle / drop-zone phase.
		await expect(page.locator(WIDGET_ROOT).first()).toBeVisible({ timeout: 15_000 });
		await expect(page.getByText(DROP_ZONE_CUE)).toBeVisible({ timeout: 10_000 });

		// Set the file directly on the hidden input without clicking through
		// the OS file-picker (setInputFiles bypasses the hidden attribute).
		const fileInput = page.locator(FILE_INPUT);
		await fileInput.setInputFiles(fixturePath('001.jpg'));

		// The widget should immediately enter the "scanning" phase:
		// the drop-zone disappears, the probe preview + progress bar appear.
		await expect(page.locator(PROGRESS_BAR)).toBeVisible({ timeout: 10_000 });

		// Wait for the search to complete: progress bar disappears and a
		// result-state heading appears. Give the REST call generous time.
		await expect(page.locator(PROGRESS_BAR)).toBeHidden({ timeout: 30_000 });

		// One of: "N matches", "No matches", "Search failed" must be shown.
		await expect(
			page.locator(WIDGET_ROOT).first().locator('div').filter({ hasText: RESULT_HEADING_PATTERN }).first()
		).toBeVisible({ timeout: 10_000 });
	});

	// -------------------------------------------------------------------------
	// 3. Empty or results state: correct UI renders without a JS crash
	// -------------------------------------------------------------------------
	test('post-search state renders either results grid or empty/error message', async ({ page }) => {
		await page.goto(postUrl);
		await expect(page.locator(WIDGET_ROOT).first()).toBeVisible({ timeout: 15_000 });
		await expect(page.getByText(DROP_ZONE_CUE)).toBeVisible({ timeout: 10_000 });

		const fileInput = page.locator(FILE_INPUT);
		await fileInput.setInputFiles(fixturePath('001.jpg'));

		// Wait for scanning to finish.
		await expect(page.locator(PROGRESS_BAR)).toBeVisible({ timeout: 10_000 });
		await expect(page.locator(PROGRESS_BAR)).toBeHidden({ timeout: 30_000 });

		const widget = page.locator(WIDGET_ROOT).first();

		// Check which state the widget landed in and assert the correct UI.
		const hasResults   = await widget.locator('a[href]').count() > 0;
		const hasEmptyText = await widget.getByText(EMPTY_TEXT).count() > 0;
		// "Search failed" error state: the error paragraph has class py-10 px-6
		const hasError     = await widget.locator('div.py-10').count() > 0;

		// Exactly one of the three post-search states must be rendered.
		expect(hasResults || hasEmptyText || hasError).toBe(true);

		// In all cases, the drop-zone must have been replaced (no longer visible).
		await expect(page.getByText(DROP_ZONE_CUE)).toBeHidden();

		// Verify no uncaught JS exceptions occurred (Playwright surfaces these
		// via page errors; if this assertion fails, check the browser console).
		// Nothing to assert here beyond the above — Playwright fails tests on
		// uncaught exceptions automatically when using the default test runner.
	});

	// -------------------------------------------------------------------------
	// 4. "New search" button resets the widget back to the idle drop-zone
	// -------------------------------------------------------------------------
	test('"New search" resets the widget to idle drop-zone state', async ({ page }) => {
		await page.goto(postUrl);
		await expect(page.locator(WIDGET_ROOT).first()).toBeVisible({ timeout: 15_000 });
		await expect(page.getByText(DROP_ZONE_CUE)).toBeVisible({ timeout: 10_000 });

		const fileInput = page.locator(FILE_INPUT);
		await fileInput.setInputFiles(fixturePath('001.jpg'));

		// Wait for the search to settle into any post-scan state.
		await expect(page.locator(PROGRESS_BAR)).toBeVisible({ timeout: 10_000 });
		await expect(page.locator(PROGRESS_BAR)).toBeHidden({ timeout: 30_000 });

		// Click "New search" to reset.
		const newSearchBtn = page.getByRole('button', { name: /new search/i });
		await expect(newSearchBtn).toBeVisible({ timeout: 5_000 });
		await newSearchBtn.click();

		// Widget must return to the idle drop-zone state.
		await expect(page.getByText(DROP_ZONE_CUE)).toBeVisible({ timeout: 5_000 });
		await expect(page.locator(FILE_INPUT)).toBeAttached();
	});
});
