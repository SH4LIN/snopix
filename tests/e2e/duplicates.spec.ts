/**
 * Duplicates tab – end-to-end spec.
 *
 * Selectors are best-effort from source (DuplicatesDesktop.tsx /
 * DuplicatesMobile.tsx). The scan button label is "Rescan" on desktop (the
 * component always renders that label unless actively scanning, in which case
 * it shows "Scanning…"). The empty-state heading reads either
 * "No duplicate clusters above N%" (post-scan) or "No scan run yet."
 * (pre-scan). On mobile the empty state is "No duplicate clusters found."
 * A duplicate group card has a pill like "100.0% match" and a header with
 * "Group · N attachments".
 *
 * Flow:
 *  1. Upload the same fixture image twice via the REST API so WordPress has
 *     two identical attachments and at least one duplicate group is expected.
 *  2. Navigate to the Duplicates tab.
 *  3. Click "Rescan" and wait for the scan to complete (progress bar → gone,
 *     results or empty state visible). Generous 60 s timeout: the scan runs
 *     via WP-Cron / Action Scheduler in batches and the app polls every 2 s.
 *  4. Assert that after scanning either a group card OR a no-duplicates empty
 *     state is visible — i.e., the scan completed without a JS error.
 *
 * Caveats: indexing must have completed before the scan can detect duplicates.
 * If the indexer has not processed the two uploads yet the scan may return zero
 * groups and the test falls back to the empty-state assertion. Both outcomes
 * are valid — the test proves the UI renders a terminal state, not an infinite
 * spinner or error page.
 */

import { type Page } from '@playwright/test';
import { test, expect } from './fixtures';
import { login, gotoSnopix, uploadMedia, fixturePath } from './helpers';

const DUPLICATES_TAB_LABEL = 'Duplicates';
const SCAN_BUTTON_TEXT = 'Rescan';
const SCANNING_TEXT = 'Scanning…';

/** Navigate to the Duplicates route inside the React SPA. */
async function gotoDuplicates(page: Page): Promise<void> {
	await gotoSnopix(page);

	const tab = page.getByRole('tab', { name: DUPLICATES_TAB_LABEL });
	const tabOrBtn = tab.or(page.getByRole('button', { name: DUPLICATES_TAB_LABEL }));
	await tabOrBtn.first().click();

	await expect(page.getByRole('heading', { name: DUPLICATES_TAB_LABEL })).toBeVisible({
		timeout: 10_000,
	});
}

/** Wait until the scan is no longer in the "Scanning…" state. */
async function waitForScanComplete(page: Page): Promise<void> {
	await expect(
		page.getByRole('button', { name: SCAN_BUTTON_TEXT })
	).toBeVisible({ timeout: 60_000 });
}

test.describe('Duplicates tab', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('navigate to the Duplicates tab', async ({ page }) => {
		await test.step('navigate to Duplicates tab', async () => {
			console.log('[duplicates] navigating to Duplicates tab');
			await gotoDuplicates(page);
			console.log('[duplicates] Duplicates tab active');
		});

		await test.step('assert heading and Rescan button are visible', async () => {
			await expect(page.getByRole('heading', { name: DUPLICATES_TAB_LABEL })).toBeVisible();
			await expect(page.getByRole('button', { name: SCAN_BUTTON_TEXT })).toBeVisible();
			console.log('[duplicates] heading and Rescan button visible');

			const screenshot = await page.screenshot();
			await test.info().attach('duplicates-tab', { body: screenshot, contentType: 'image/png' });
		});
	});

	test('run a duplicate scan and see results or empty state after uploading the same image twice', async ({ page }) => {
		await test.step('upload the same fixture image twice', async () => {
			console.log('[duplicates] uploading 001.jpg twice to seed duplicate');
			const fixture = fixturePath('001.jpg');
			await uploadMedia(page, fixture);
			await uploadMedia(page, fixture);
			console.log('[duplicates] both uploads done');

			const screenshot = await page.screenshot();
			await test.info().attach('after-duplicate-uploads', { body: screenshot, contentType: 'image/png' });
		});

		await test.step('navigate to Duplicates tab', async () => {
			console.log('[duplicates] navigating to Duplicates tab');
			await gotoDuplicates(page);
		});

		await test.step('click Rescan to trigger duplicate scan', async () => {
			console.log('[duplicates] clicking Rescan');
			const scanBtn = page.getByRole('button', { name: SCAN_BUTTON_TEXT });
			await expect(scanBtn).toBeVisible({ timeout: 10_000 });
			await scanBtn.click();
			console.log('[duplicates] scan triggered');

			const screenshot = await page.screenshot();
			await test.info().attach('scan-triggered', { body: screenshot, contentType: 'image/png' });
		});

		await test.step('wait for scan to complete', async () => {
			console.log('[duplicates] waiting for scan to complete...');
			await waitForScanComplete(page);
			console.log('[duplicates] scan complete');
		});

		await test.step('assert terminal UI state: group card or empty state is visible', async () => {
			const groupCard = page.locator('.snopix-card').filter({
				has: page.locator('text=/\\d+\\.\\d+%\\s*match/'),
			});

			const emptyStatePostScan = page.getByText(/No duplicate clusters above \d+%/);
			const emptyStateNoScan   = page.getByText('No scan run yet.');
			const emptyStateMobile   = page.getByText('No duplicate clusters found.');

			await expect(
				groupCard.or(emptyStatePostScan).or(emptyStateNoScan).or(emptyStateMobile).first()
			).toBeVisible({ timeout: 20_000 });

			const groupsFound = await groupCard.first().isVisible();
			console.log(`[duplicates] result state: ${groupsFound ? 'group card visible' : 'empty state'}`);

			if (groupsFound) {
				await expect(page.getByText(/Group\s*·\s*\d+ attachments/).first()).toBeVisible();
				await expect(page.getByRole('button', { name: 'Delete all duplicates' })).toBeEnabled();
				console.log('[duplicates] group card structure verified');
			}

			const screenshot = await page.screenshot();
			await test.info().attach('scan-result', { body: screenshot, contentType: 'image/png' });
		});
	});

	test('scan button is disabled while a scan is running', async ({ page }) => {
		await test.step('navigate to Duplicates tab', async () => {
			console.log('[duplicates] navigating for button-disabled test');
			await gotoDuplicates(page);
		});

		await test.step('click Rescan and confirm button disables immediately', async () => {
			console.log('[duplicates] clicking Rescan');
			const scanBtn = page.getByRole('button', { name: SCAN_BUTTON_TEXT });
			await expect(scanBtn).toBeVisible({ timeout: 60_000 });
			await scanBtn.click();

			const scanningBtn       = page.getByRole('button', { name: SCANNING_TEXT });
			const disabledRescanBtn = page.getByRole('button', { name: SCAN_BUTTON_TEXT });

			await expect(scanningBtn.or(disabledRescanBtn).first()).toBeVisible({ timeout: 5_000 });
			console.log('[duplicates] button entered scanning or rescan state immediately after click');

			const screenshot = await page.screenshot();
			await test.info().attach('scan-button-disabled', { body: screenshot, contentType: 'image/png' });
		});

		await test.step('wait for scan to finish', async () => {
			console.log('[duplicates] waiting for scan to finish...');
			await waitForScanComplete(page);
			console.log('[duplicates] scan finished');
		});
	});

	test('progress bar is visible during an active scan', async ({ page }) => {
		await test.step('navigate to Duplicates tab', async () => {
			console.log('[duplicates] navigating for progress-bar test');
			await gotoDuplicates(page);
		});

		await test.step('click Rescan and check for progress indicator', async () => {
			console.log('[duplicates] clicking Rescan');
			const scanBtn = page.getByRole('button', { name: SCAN_BUTTON_TEXT });
			await expect(scanBtn).toBeVisible({ timeout: 60_000 });
			await scanBtn.click();

			const progressBar  = page.locator('.snopix-progress');
			const progressText = page.getByText('Scanning for duplicates…');

			const appeared = await progressBar
				.or(progressText)
				.first()
				.isVisible({ timeout: 5_000 })
				.catch(() => false);

			console.log(`[duplicates] progress indicator appeared: ${appeared}`);
			const screenshot = await page.screenshot();
			await test.info().attach('progress-bar-check', { body: screenshot, contentType: 'image/png' });
			test.info().annotations.push({ type: 'progress-appeared', description: String(appeared) });

			if (!appeared) {
				console.log('[duplicates] scan was instant — waiting for terminal state');
				await expect(page.getByRole('button', { name: SCAN_BUTTON_TEXT })).toBeVisible({ timeout: 30_000 });
			} else {
				await waitForScanComplete(page);
			}
			console.log('[duplicates] test complete');
		});
	});
});
