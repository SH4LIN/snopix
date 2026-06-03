/**
 * Snopix admin app — smoke tests.
 *
 * Selectors are derived from source-code analysis of the React components and
 * are best-effort. They may need adjustment on the first run if the rendered
 * output differs from the compile-time source (e.g. i18n overrides, theme
 * conflicts, or WordPress version changes altering the admin shell).
 *
 * Routes: TanStack Router with hash history.
 *   /dashboard  → Dashboard component (StatsBar + heading)
 *   /duplicates → Duplicates component
 *   /tools      → Tools component
 *   /settings   → Settings component
 *
 * StatsBar labels (from StatsBar.tsx): Total | Indexed | Pending | Failed
 * Nav tab labels  (from AppShellDesktop.tsx / AppShellMobile.tsx):
 *   Dashboard | Duplicates | Tools | Settings
 */

import { test, expect } from './fixtures';
import { login, gotoSnopix } from './helpers';

test.beforeEach(async ({ page }) => {
	await login(page);
});

// ---------------------------------------------------------------------------
// 1. App renders inside #snopix-root
// ---------------------------------------------------------------------------

test('Snopix React app mounts and shows the Dashboard heading', async ({ page }) => {
	await test.step('navigate to Snopix admin page', async () => {
		console.log('[admin] navigating to Snopix admin page');
		await gotoSnopix(page);
		console.log('[admin] page loaded');
	});

	await test.step('assert app root and Dashboard heading are visible', async () => {
		const appRoot = page.locator('#snopix-root');
		await expect(appRoot).toBeVisible();

		await expect(
			page.getByRole('heading', { name: 'Dashboard', level: 1 })
		).toBeVisible({ timeout: 10_000 });

		console.log('[admin] Dashboard heading visible — app mounted successfully');
		const screenshot = await page.screenshot();
		await test.info().attach('dashboard-mounted', { body: screenshot, contentType: 'image/png' });
	});
});

// ---------------------------------------------------------------------------
// 2. StatsBar renders all four metric labels
// ---------------------------------------------------------------------------

test('StatsBar shows Total, Indexed, Pending, and Failed labels', async ({ page }) => {
	await test.step('navigate to Snopix admin page', async () => {
		console.log('[admin] navigating for StatsBar test');
		await gotoSnopix(page);
	});

	await test.step('assert all four StatsBar labels are visible', async () => {
		const statsBar = page.locator('[data-tour="dashboard-stats"]');
		await expect(statsBar).toBeVisible({ timeout: 10_000 });

		for (const label of ['Total', 'Indexed', 'Pending', 'Failed']) {
			await expect(statsBar.locator('.snopix-stat__label', { hasText: label })).toBeVisible();
			console.log(`[admin] StatsBar label "${label}" visible`);
		}

		const screenshot = await page.screenshot();
		await test.info().attach('stats-bar', { body: screenshot, contentType: 'image/png' });
	});
});

// ---------------------------------------------------------------------------
// 3. In-app navigation: tabs are present and clicking one changes the route
// ---------------------------------------------------------------------------

test('Nav tabs are visible and clicking Duplicates navigates to the Duplicates panel', async ({ page }) => {
	await test.step('navigate to Snopix admin page', async () => {
		console.log('[admin] navigating for nav-tabs test');
		await gotoSnopix(page);
	});

	await test.step('assert all four nav tabs are present', async () => {
		const nav = page.locator('[data-tour="nav-tabs"]');
		await expect(nav).toBeVisible({ timeout: 10_000 });

		for (const label of ['Dashboard', 'Duplicates', 'Tools', 'Settings']) {
			await expect(nav.getByText(label, { exact: true })).toBeVisible();
			console.log(`[admin] nav tab "${label}" visible`);
		}

		const screenshot = await page.screenshot();
		await test.info().attach('nav-tabs-visible', { body: screenshot, contentType: 'image/png' });
	});

	await test.step('click Duplicates tab and confirm route change', async () => {
		console.log('[admin] clicking Duplicates tab');
		const nav = page.locator('[data-tour="nav-tabs"]');
		await nav.getByText('Duplicates', { exact: true }).click();

		await page.waitForURL(/#\/duplicates/, { timeout: 10_000 });
		console.log('[admin] route changed to #/duplicates');

		await expect(page.getByRole('heading', { name: 'Dashboard', level: 1 })).toBeHidden();

		const screenshot = await page.screenshot();
		await test.info().attach('duplicates-route', { body: screenshot, contentType: 'image/png' });
	});
});

// ---------------------------------------------------------------------------
// 4. Navigating to Settings and back to Dashboard
// ---------------------------------------------------------------------------

test('Settings tab is reachable and has data-tour="settings-nav"', async ({ page }) => {
	await test.step('navigate to Snopix admin page', async () => {
		console.log('[admin] navigating for settings test');
		await gotoSnopix(page);
	});

	await test.step('click Settings and confirm route', async () => {
		console.log('[admin] clicking Settings button');
		const settingsBtn = page.locator('[data-tour="settings-nav"]');
		await expect(settingsBtn).toBeVisible({ timeout: 10_000 });
		await settingsBtn.click();
		await page.waitForURL(/#\/settings/, { timeout: 10_000 });
		console.log('[admin] route changed to #/settings');

		const screenshot = await page.screenshot();
		await test.info().attach('settings-route', { body: screenshot, contentType: 'image/png' });
	});

	await test.step('return to Dashboard via nav tab', async () => {
		console.log('[admin] returning to Dashboard');
		await page.locator('[data-tour="nav-tabs"]').getByText('Dashboard', { exact: true }).click();
		await page.waitForURL(/#\/dashboard/, { timeout: 10_000 });

		await expect(page.getByRole('heading', { name: 'Dashboard', level: 1 })).toBeVisible({ timeout: 10_000 });
		console.log('[admin] Dashboard heading confirmed');

		const screenshot = await page.screenshot();
		await test.info().attach('dashboard-returned', { body: screenshot, contentType: 'image/png' });
	});
});
