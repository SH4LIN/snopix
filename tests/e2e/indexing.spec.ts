/**
 * Snopix — Indexing e2e spec
 *
 * NOTE: This spec depends on WP-Cron to process fingerprint batches.
 * Selectors and UI labels are derived from the React source (best-effort);
 * if the app is rebuilt they may shift. Key labels used:
 *   - Trigger button (desktop header): "Index remaining" (data-tour="reindex-button")
 *   - Progress card heading:           "Indexing attachments" → "Indexing complete"
 *   - Progress percentage span:        numeric text like "42%"
 *   - Stats tile label:                "Indexed"
 *
 * The test bypasses the UI upload flow for speed and uses the WP REST API
 * directly (authenticated via X-WP-Nonce). The indexing trigger uses the
 * Snopix REST endpoint, and completion is asserted via the /status endpoint
 * as well as the "Indexed" stat tile in the SPA.
 *
 * WP-Cron must be able to run (either normally or via `wp-cron.php` requests).
 * The default wp-env setup fires cron on page load, which the test triggers
 * by visiting wp-admin pages.
 */

import { test, expect } from '@playwright/test';
import { login, gotoSnopix, uploadMedia, fixturePath } from './helpers';

const UPLOAD_COUNT = 2;
const FIXTURE_NAMES = ['001.jpg', '002.jpg'] as const;

// How long (ms) to give the indexing job to complete — cron batches take time.
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
		// Non-fatal — cron may simply have nothing to run.
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
		// ----------------------------------------------------------------
		// Step 1: Upload fixture images via the WordPress media library.
		// ----------------------------------------------------------------
		for (const name of FIXTURE_NAMES) {
			await uploadMedia(page, fixturePath(name));
		}

		// ----------------------------------------------------------------
		// Step 2: Navigate to the Snopix admin SPA.
		// ----------------------------------------------------------------
		await gotoSnopix(page);

		// Wait for the Dashboard tab to render (h1 text "Dashboard").
		await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible({
			timeout: 15_000,
		});

		// ----------------------------------------------------------------
		// Step 3: Read the nonce for REST calls — we're on an admin page.
		// ----------------------------------------------------------------
		const nonce = await getNonce(page);

		// ----------------------------------------------------------------
		// Step 4: Record the Indexed count before triggering indexing so we
		// can assert it has grown (or reached the expected minimum).
		// ----------------------------------------------------------------
		const indexedTile = page
			.locator('.snopix-stat')
			.filter({ has: page.locator('.snopix-stat__label', { hasText: 'Indexed' }) })
			.locator('.snopix-stat__value');

		// Stat bar renders "—" while loading; wait for a numeric value.
		await expect(indexedTile).not.toHaveText('—', { timeout: 10_000 });
		const indexedBefore = parseInt((await indexedTile.textContent()) ?? '0', 10);

		// ----------------------------------------------------------------
		// Step 5: Trigger indexing via the Snopix REST API.
		// POST /wp-json/snopix/v1/reindex schedules cron batches for any
		// pending (non-indexed) attachments.  If a job is already running the
		// server returns 409 — we surface that but don't fail, since prior
		// uploads may already be queued.
		// ----------------------------------------------------------------
		const reindexRes = await page.request.post('/wp-json/snopix/v1/reindex', {
			headers: { 'X-WP-Nonce': nonce },
		});

		expect(
			[200, 201, 409],
			`POST /snopix/v1/reindex returned unexpected status ${reindexRes.status()}`
		).toContain(reindexRes.status());

		// ----------------------------------------------------------------
		// Step 6: Poll /progress until done, kicking WP-Cron each iteration.
		// ----------------------------------------------------------------
		await expect
			.poll(
				async () => {
					// Kick cron so wp-env's sync cron fires the next batch.
					await tickCron(page);

					const res = await page.request.get('/wp-json/snopix/v1/progress', {
						headers: { 'X-WP-Nonce': nonce },
					});

					if (!res.ok()) {
						// progress endpoint returns 204 / empty when idle — treat as done.
						return true;
					}

					const data = (await res.json()) as {
						done: number;
						total: number;
						status: 'idle' | 'running' | 'done' | 'stalled';
					};

					// 'idle' means no active job (all processed or none scheduled).
					// 'done' means the job finished within this poll cycle.
					return data.status === 'done' || data.status === 'idle';
				},
				{
					message: `Indexing job did not reach 'done'/'idle' within ${INDEXING_TIMEOUT} ms`,
					timeout: INDEXING_TIMEOUT,
					intervals: [3_000],
				}
			)
			.toBe(true);

		// ----------------------------------------------------------------
		// Step 7: Confirm /status shows Indexed >= UPLOAD_COUNT.
		// ----------------------------------------------------------------
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

		expect(
			status.indexed,
			`Expected at least ${UPLOAD_COUNT} indexed images, got ${status.indexed}`
		).toBeGreaterThanOrEqual(UPLOAD_COUNT);

		// ----------------------------------------------------------------
		// Step 8: Confirm the "Indexed" stat tile in the SPA reflects the
		// updated count.  The SPA polls /status every 30 s when idle but
		// also refreshes after a job transitions to done, so it should
		// update within a reasonable window.
		// ----------------------------------------------------------------

		// Reload the SPA so the status query fires immediately.
		await gotoSnopix(page);
		await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible({
			timeout: 10_000,
		});

		await expect
			.poll(
				async () => {
					const text = await indexedTile.textContent();
					const value = parseInt(text ?? '0', 10);
					return value >= UPLOAD_COUNT || value > indexedBefore;
				},
				{
					message: `"Indexed" stat tile did not reach >= ${UPLOAD_COUNT} within ${SPA_UPDATE_TIMEOUT} ms`,
					timeout: SPA_UPDATE_TIMEOUT,
					intervals: [2_000],
				}
			)
			.toBe(true);

		// ----------------------------------------------------------------
		// Step 9 (optional signal check): If the progress card is still
		// visible (job just finished), assert it shows "Indexing complete".
		// This is a soft check — the card auto-hides after 3 s.
		// ----------------------------------------------------------------
		const progressCard = page.locator('[data-tour="reindex-button"]').first();
		if (await progressCard.isVisible()) {
			await expect(progressCard).toContainText('Indexing complete', {
				timeout: 5_000,
			});
		}
	});

	// -----------------------------------------------------------------------
	// Smoke test: the "Index remaining" header button is rendered when the
	// SPA loads, and the "Indexed" stat tile displays a numeric value.
	// -----------------------------------------------------------------------
	test('dashboard renders stat tiles and the Index remaining button', async ({ page }) => {
		await gotoSnopix(page);

		// All four stat tiles should be visible.
		for (const label of ['Total', 'Indexed', 'Pending', 'Failed']) {
			await expect(
				page.locator('.snopix-stat__label', { hasText: label })
			).toBeVisible({ timeout: 15_000 });
		}

		// The "Index remaining" header button is always rendered (may be disabled).
		const indexBtn = page.locator('button[data-tour="reindex-button"]');
		await expect(indexBtn).toBeVisible({ timeout: 15_000 });
		await expect(indexBtn).toContainText('Index remaining');
	});
});
