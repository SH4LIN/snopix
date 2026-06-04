/**
 * Snopix - Indexing e2e spec
 *
 * NOTE: This spec depends on WP-Cron to process fingerprint batches.
 * Selectors and UI labels are derived from the React source (best-effort);
 * if the app is rebuilt they may shift. Key labels used:
 *   - Trigger button (desktop header): "Index remaining" (data-tour="reindex-button")
 *   - Progress card heading:           "Indexing attachments" → "Indexing complete"
 *   - Progress percentage span:        numeric text like "42%"
 *   - Stats tile label:                "Indexed"
 *
 *
 * WP-Cron must be able to run (either normally or via `wp-cron.php` requests).
 * The default wp-env setup fires cron on page load, which the test triggers
 * by visiting wp-admin pages.
 */

import { test, expect } from './fixtures';
import { login, gotoSnopix, uploadMedia, fixturePath } from './helpers';

const UPLOAD_COUNT = 2;
const FIXTURE_NAMES = ['001.jpg', '002.jpg'] as const;

// How long (ms) to give the indexing job to complete - cron batches take time.
const INDEXING_TIMEOUT = 90_000;

// How long to give the "Indexed" stat tile to update in the SPA.
const SPA_UPDATE_TIMEOUT = 30_000;

/** Read the WP REST nonce injected by WordPress into every admin page. */
async function getNonce(page: import('@playwright/test').Page): Promise<string> {
	// wp_localize_script / wp_add_inline_script injects wpApiSettings on admin pages.
	return page.evaluate(
		() =>
			(window as unknown as { wpApiSettings?: { nonce: string } })
				.wpApiSettings?.nonce ?? ''
	);
}

/**
 * Trigger WP-Cron by requesting wp-cron.php in the background.
 * The browser is already authenticated so the cookie jar carries through.
 */
async function tickCron(page: import('@playwright/test').Page): Promise<void> {
	try {
		await page.request.get('/wp-cron.php?doing_wp_cron', { timeout: 10_000 });
	} catch {
		// Non-fatal - cron may simply have nothing to run.
	}
}

test.describe('Snopix indexing', () => {
	// Give the whole suite enough room for uploads + cron batches.
	test.setTimeout(180_000);

	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('uploads fixture images, triggers indexing, and confirms Indexed count increases', async ({
		page,
	}) => {
		let nonce = '';
		let indexedBefore = 0;

		// ----------------------------------------------------------------
		// Step 1: Upload fixture images via the WordPress media library.
		// ----------------------------------------------------------------
		await test.step('upload fixture images via REST API', async () => {
			for (const name of FIXTURE_NAMES) {
				console.log(`[indexing] uploading fixture: ${name}`);
				await uploadMedia(page, fixturePath(name));
				console.log(`[indexing] uploaded: ${name}`);
			}

			const screenshot = await page.screenshot();
			await test.info().attach('after-uploads', { body: screenshot, contentType: 'image/png' });
		});

		// ----------------------------------------------------------------
		// Step 2: Capture REST nonce while still on /wp-admin/.
		// wpApiSettings is NOT injected on the Snopix admin page.
		// ----------------------------------------------------------------
		await test.step('capture WP REST nonce from admin page', async () => {
			nonce = await getNonce(page);
			console.log(`[indexing] nonce captured (present: ${nonce.length > 0})`);
			test.info().annotations.push({ type: 'nonce-present', description: String(nonce.length > 0) });
		});

		// ----------------------------------------------------------------
		// Step 3: Navigate to the Snopix admin SPA.
		// ----------------------------------------------------------------
		await test.step('navigate to Snopix admin SPA', async () => {
			console.log('[indexing] navigating to Snopix admin SPA');
			await gotoSnopix(page);
			await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible({
				timeout: 15_000,
			});
			console.log('[indexing] Dashboard visible');

			const screenshot = await page.screenshot();
			await test.info().attach('snopix-dashboard', { body: screenshot, contentType: 'image/png' });
		});

		// ----------------------------------------------------------------
		// Step 4: Record the Indexed count before triggering indexing.
		// ----------------------------------------------------------------
		await test.step('record Indexed count before indexing', async () => {
			const indexedTile = page
				.locator('.snopix-stat')
				.filter({ has: page.locator('.snopix-stat__label', { hasText: 'Indexed' }) })
				.locator('.snopix-stat__value');

			await expect(indexedTile).not.toHaveText('-', { timeout: 10_000 });
			indexedBefore = parseInt((await indexedTile.textContent()) ?? '0', 10);
			console.log(`[indexing] Indexed count before: ${indexedBefore}`);
			test.info().annotations.push({ type: 'indexed-before', description: String(indexedBefore) });
		});

		// ----------------------------------------------------------------
		// Step 5: Trigger indexing via the Snopix REST API.
		// ----------------------------------------------------------------
		await test.step('POST /snopix/v1/reindex to schedule indexing', async () => {
			console.log('[indexing] triggering reindex via REST API');
			const reindexRes = await page.request.post('/wp-json/snopix/v1/reindex', {
				headers: { 'X-WP-Nonce': nonce },
			});

			console.log(`[indexing] POST /snopix/v1/reindex → ${reindexRes.status()}`);
			expect(
				[200, 201, 409],
				`POST /snopix/v1/reindex returned unexpected status ${reindexRes.status()}`
			).toContain(reindexRes.status());

			test.info().annotations.push({ type: 'reindex-status', description: String(reindexRes.status()) });
		});

		// ----------------------------------------------------------------
		// Step 6: Poll /progress until done, kicking WP-Cron each iteration.
		// ----------------------------------------------------------------
		await test.step('poll /progress until indexing job is done', async () => {
			console.log('[indexing] polling /progress...');
			await expect
				.poll(
					async () => {
						await tickCron(page);

						const res = await page.request.get('/wp-json/snopix/v1/progress', {
							headers: { 'X-WP-Nonce': nonce },
						});

						if (!res.ok()) {
							console.log('[indexing] /progress returned non-OK - treating as idle');
							return true;
						}

						const data = (await res.json()) as {
							done: number;
							total: number;
							status: 'idle' | 'running' | 'done' | 'stalled';
						};

						console.log(`[indexing] progress: ${data.done}/${data.total} status=${data.status}`);
						return data.status === 'done' || data.status === 'idle';
					},
					{
						message: `Indexing job did not reach 'done'/'idle' within ${INDEXING_TIMEOUT} ms`,
						timeout: INDEXING_TIMEOUT,
						intervals: [3_000],
					}
				)
				.toBe(true);

			console.log('[indexing] indexing job reached terminal state');
			const screenshot = await page.screenshot();
			await test.info().attach('indexing-complete', { body: screenshot, contentType: 'image/png' });
		});

		// ----------------------------------------------------------------
		// Step 7: Confirm /status shows Indexed >= UPLOAD_COUNT.
		// ----------------------------------------------------------------
		await test.step('verify /status reports Indexed >= upload count', async () => {
			const statusRes = await page.request.get('/wp-json/snopix/v1/status', {
				headers: { 'X-WP-Nonce': nonce },
			});

			expect(statusRes.ok(), 'GET /snopix/v1/status failed').toBe(true);

			const status = (await statusRes.json()) as {
				total: number;
				indexed: number;
				pending: number;
				failed: number;
			};

			console.log(`[indexing] /status → total=${status.total} indexed=${status.indexed} pending=${status.pending} failed=${status.failed}`);
			test.info().annotations.push({ type: 'status-indexed', description: String(status.indexed) });

			expect(
				status.indexed,
				`Expected at least ${UPLOAD_COUNT} indexed images, got ${status.indexed}`
			).toBeGreaterThanOrEqual(UPLOAD_COUNT);
		});

		// ----------------------------------------------------------------
		// Step 8: Confirm the "Indexed" stat tile in the SPA reflects the update.
		// ----------------------------------------------------------------
		await test.step('reload SPA and verify Indexed stat tile updated', async () => {
			console.log('[indexing] reloading SPA to verify stat tile');
			await gotoSnopix(page);
			await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible({
				timeout: 10_000,
			});

			const indexedTile = page
				.locator('.snopix-stat')
				.filter({ has: page.locator('.snopix-stat__label', { hasText: 'Indexed' }) })
				.locator('.snopix-stat__value');

			await expect
				.poll(
					async () => {
						const text = await indexedTile.textContent();
						const value = parseInt(text ?? '0', 10);
						console.log(`[indexing] stat tile Indexed = ${value}`);
						return value >= UPLOAD_COUNT || value > indexedBefore;
					},
					{
						message: `"Indexed" stat tile did not reach >= ${UPLOAD_COUNT} within ${SPA_UPDATE_TIMEOUT} ms`,
						timeout: SPA_UPDATE_TIMEOUT,
						intervals: [2_000],
					}
				)
				.toBe(true);

			const screenshot = await page.screenshot();
			await test.info().attach('stat-tile-updated', { body: screenshot, contentType: 'image/png' });
		});

		// ----------------------------------------------------------------
		// Step 9 (optional): If the progress card is still visible, assert
		// it shows "Indexing complete". Soft check - card auto-hides after 3 s.
		// ----------------------------------------------------------------
		await test.step('optional: confirm progress card shows Indexing complete', async () => {
			const progressCard = page.locator('div[data-tour="reindex-button"]').first();
			if (await progressCard.isVisible()) {
				console.log('[indexing] progress card still visible - checking for "Indexing complete"');
				await expect(progressCard).toContainText('Indexing complete', {
					timeout: 5_000,
				});

				const screenshot = await page.screenshot();
				await test.info().attach('progress-card-complete', { body: screenshot, contentType: 'image/png' });
			}
		});
	});

	// -----------------------------------------------------------------------
	// Smoke test: the "Index remaining" header button is rendered when the
	// SPA loads, and the "Indexed" stat tile displays a numeric value.
	// -----------------------------------------------------------------------
	test('dashboard renders stat tiles and the Index remaining button', async ({ page }) => {
		await test.step('navigate to Snopix admin page', async () => {
			console.log('[indexing:smoke] navigating to Snopix admin page');
			await gotoSnopix(page);
		});

		await test.step('assert all stat tiles and the Index remaining button are visible', async () => {
			for (const label of ['Total', 'Indexed', 'Pending', 'Failed']) {
				await expect(page.locator('.snopix-stat__label', { hasText: label })).toBeVisible({ timeout: 15_000 });
				console.log(`[indexing:smoke] stat tile "${label}" visible`);
			}

			const indexBtn = page.locator('button[data-tour="reindex-button"]');
			await expect(indexBtn).toBeVisible({ timeout: 15_000 });
			await expect(indexBtn).toContainText('Index remaining');
			console.log('[indexing:smoke] "Index remaining" button visible');

			const screenshot = await page.screenshot();
			await test.info().attach('dashboard-smoke', { body: screenshot, contentType: 'image/png' });
		});
	});
});
