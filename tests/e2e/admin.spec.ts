import { test, expect } from '@playwright/test';
import { wpLogin } from './helpers';

test.describe('Pixel Scout Admin Dashboard (Phase 6)', () => {
	test.beforeEach(async ({ page }) => {
		await wpLogin( page );
	});

	test('admin sidebar link exists after activation', async ({ page }) => {
		// Navigate to WordPress admin.
		await page.goto('/wp-admin/');

		// Look for Pixel Scout in admin menu.
		const psLink = page.locator('[href*="pixel-scout"]');
		await expect(psLink).toBeVisible();
	});

	test.skip('dashboard loads with stats', async ({ page }) => {
		// This test is skipped until Phase 6 is implemented.
		// Expected: navigate to Pixel Scout admin page, see StatsBar with counts.

		await page.goto('/wp-admin/admin.php?page=pixel-scout');
		const dashboard = page.locator('#ps-root');
		await expect(dashboard).toBeVisible();
	});

	test.skip('image table displays indexed images', async ({ page }) => {
		// Phase 6 test: verify ImageTable shows paginated list
		await page.goto('/wp-admin/admin.php?page=pixel-scout');

		const table = page.locator('table');
		await expect(table).toBeVisible();
	});

	test.skip('search preview accepts image upload', async ({ page }) => {
		// Phase 6 test: verify SearchPreview drop zone
		await page.goto('/wp-admin/admin.php?page=pixel-scout');

		const dropZone = page.locator('[data-testid="search-drop-zone"]');
		await expect(dropZone).toBeVisible();
	});

	test.skip('reindex button triggers bulk indexing', async ({ page }) => {
		// Phase 6 test: verify ReindexButton state transitions
		await page.goto('/wp-admin/admin.php?page=pixel-scout');

		const reindexBtn = page.locator('button:has-text("Index Remaining")');
		if (await reindexBtn.isVisible()) {
			await reindexBtn.click();
			// Verify progress bar appears
			const progressBar = page.locator('[data-testid="progress-bar"]');
			await expect(progressBar).toBeVisible();
		}
	});
});

/**
 * Admin settings page tests.
 */
test.describe('Pixel Scout Settings (Phase 5)', () => {
	test.skip('settings page shows search visibility option', async ({ page }) => {
		// Phase 5 test: navigate to settings
		await page.goto('/wp-admin/options-general.php?page=pixel-scout-settings');

		const searchVisibility = page.locator('input[name="search_visibility"]');
		await expect(searchVisibility).toBeVisible();
	});

	test.skip('can toggle between anyone and logged-in', async ({ page }) => {
		// Phase 5 test: verify radio options work
		await page.goto('/wp-admin/options-general.php?page=pixel-scout-settings');

		const loggedInOption = page.locator('input[value="logged_in"]');
		await loggedInOption.click();

		const saveBtn = page.locator('button:has-text("Save")');
		await saveBtn.click();

		// Verify success message
		const success = page.locator('.notice-success');
		await expect(success).toBeVisible();
	});
});

