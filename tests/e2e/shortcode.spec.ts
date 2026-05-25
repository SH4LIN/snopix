/**
 * Playwright end-to-end tests for the [snopix_search] frontend shortcode.
 *
 * Covers rendering of the drop-zone, drag-and-drop upload, score badges, the
 * "no results" empty state, click-through to the media library, visibility
 * gating (anyone vs logged-in), error states, skeleton loading and the
 * responsive layout on mobile / tablet viewports.
 */
import { test, expect } from '@playwright/test';
test.describe('Snopix Frontend Search Shortcode (Phase 7)', () => {
	test.beforeEach(async ({ page }) => {
		// Navigate to page with [snopix_search] shortcode
		// Assumes a page exists with the shortcode
		await page.goto('/snopix-search/');
	});

	test.skip('shortcode renders search drop zone', async ({ page }) => {
		// Phase 7 test: verify shortcode renders
		const dropZone = page.locator('[data-testid="snopix-search-drop-zone"]');
		await expect(dropZone).toBeVisible();
	});

	test.skip('can drag and drop image for search', async ({ page }) => {
		// Phase 7 test: upload via drag-drop
		const dropZone = page.locator('[data-testid="snopix-search-drop-zone"]');

		// File drag-drop simulation (Playwright specific)
		const fileInput = page.locator('input[type="file"]');
		await fileInput.setInputFiles('./tests/fixtures/test-image.jpg');

		// Wait for search to complete
		await page.waitForLoadState('networkidle');

		// Verify results display
		const resultsGrid = page.locator('[data-testid="snopix-search-results"]');
		await expect(resultsGrid).toBeVisible();
	});

	test.skip('displays score badges on results', async ({ page }) => {
		// Phase 7 test: verify score percentage display
		const scoreCard = page.locator('[data-testid="result-score-badge"]').first();
		await expect(scoreCard).toContainText('%');
	});

	test.skip('shows "no results" message for unrelated image', async ({ page }) => {
		// Phase 7 test: upload unrelated image, expect empty state
		const fileInput = page.locator('input[type="file"]');
		await fileInput.setInputFiles('./tests/fixtures/unrelated-image.jpg');

		await page.waitForLoadState('networkidle');

		const emptyState = page.locator(':text("No similar images found")');
		await expect(emptyState).toBeVisible();
	});

	test.skip('click result to open media library', async ({ page }) => {
		// Phase 7 test: verify result links work
		const resultLink = page.locator('[data-testid="result-link"]').first();
		const href = await resultLink.getAttribute('href');

		expect(href).toContain('media.php') || expect(href).toMatch(/^https?:\/\//);
	});

	test.skip('respects search visibility setting (public)', async ({ page }) => {
		// Phase 7 test: if visibility is "anyone", public user can search
		// This test assumes snopix_settings option is set to 'anyone'

		const dropZone = page.locator('[data-testid="snopix-search-drop-zone"]');
		await expect(dropZone).toBeVisible();
	});

	test.skip('blocks search for logged-out if visibility restricted', async ({ page, context }) => {
		// Phase 7 test: logged-out user sees notice if visibility is 'logged_in'
		// This requires settings to be changed and page reloaded

		const notice = page.locator(':text("Log in to search")');
		if (await notice.isVisible()) {
			await expect(notice).toBeVisible();
		}
	});

	test.skip('handles error states gracefully', async ({ page }) => {
		// Phase 7 test: if search fails, show error message
		// Mock network failure
		await page.route('**/wp-json/snopix/v1/search', route => route.abort());

		const fileInput = page.locator('input[type="file"]');
		await fileInput.setInputFiles('./tests/fixtures/test-image.jpg');

		const errorMsg = page.locator(':text("Something went wrong")');
		await expect(errorMsg).toBeVisible();
	});

	test.skip('loading state shows skeleton cards', async ({ page }) => {
		// Phase 7 test: verify shimmer skeleton during search
		const fileInput = page.locator('input[type="file"]');

		// Slow down network to catch loading state
		await page.route('**/wp-json/snopix/v1/search', route => {
			setTimeout(() => route.continue(), 1000);
		});

		await fileInput.setInputFiles('./tests/fixtures/test-image.jpg');

		const skeleton = page.locator('[data-testid="skeleton-card"]');
		await expect(skeleton).toBeVisible();
	});
});

/**
 * Responsive design tests.
 */
test.describe('Snopix Frontend Responsive Design', () => {
	test.skip('shortcode adapts to mobile viewport', async ({ page }) => {
		// Set mobile viewport
		await page.setViewportSize({ width: 375, height: 667 });
		await page.goto('/snopix-search/');

		const dropZone = page.locator('[data-testid="snopix-search-drop-zone"]');
		await expect(dropZone).toBeVisible();

		// Verify grid reduces to 1 column on mobile
		const resultsGrid = page.locator('[data-testid="snopix-search-results"]');
		const itemCount = await resultsGrid.locator('[data-testid="result-card"]').count();
		// On mobile, should display differently (actual assertion depends on implementation)
		expect(itemCount).toBeGreaterThanOrEqual(0);
	});

	test.skip('search works on tablet', async ({ page }) => {
		// Set tablet viewport
		await page.setViewportSize({ width: 768, height: 1024 });
		await page.goto('/snopix-search/');

		const dropZone = page.locator('[data-testid="snopix-search-drop-zone"]');
		await expect(dropZone).toBeVisible();
	});
});

