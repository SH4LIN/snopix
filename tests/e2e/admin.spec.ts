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

import { test, expect } from '@playwright/test';
import { login, gotoSnopix } from './helpers';

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------

test.beforeEach(async ({ page }) => {
	await login(page);
});

// ---------------------------------------------------------------------------
// 1. App renders inside #snopix-root
// ---------------------------------------------------------------------------

test('Snopix React app mounts and shows the Dashboard heading', async ({ page }) => {
	await gotoSnopix(page);

	// The app shell wraps everything in #snopix-app (both desktop and mobile).
	const appRoot = page.locator('#snopix-root');
	await expect(appRoot).toBeVisible();

	// DashboardDesktop renders an <h1> with the text "Dashboard".
	// After gotoSnopix the router redirects / → /dashboard automatically.
	await expect(
		page.getByRole('heading', { name: 'Dashboard', level: 1 })
	).toBeVisible({ timeout: 10_000 });
});

// ---------------------------------------------------------------------------
// 2. StatsBar renders all four metric labels
// ---------------------------------------------------------------------------

test('StatsBar shows Total, Indexed, Pending, and Failed labels', async ({ page }) => {
	await gotoSnopix(page);

	// The StatsBar container carries data-tour="dashboard-stats".
	const statsBar = page.locator('[data-tour="dashboard-stats"]');
	await expect(statsBar).toBeVisible({ timeout: 10_000 });

	// Each tile has a .snopix-stat__label child with the translated text.
	// Labels come from StatsBar.tsx: Total / Indexed / Pending / Failed.
	for (const label of ['Total', 'Indexed', 'Pending', 'Failed']) {
		await expect(
			statsBar.locator('.snopix-stat__label', { hasText: label })
		).toBeVisible();
	}
});

// ---------------------------------------------------------------------------
// 3. In-app navigation: tabs are present and clicking one changes the route
// ---------------------------------------------------------------------------

test('Nav tabs are visible and clicking Duplicates navigates to the Duplicates panel', async ({ page }) => {
	await gotoSnopix(page);

	// The nav container carries data-tour="nav-tabs" in both shells.
	const nav = page.locator('[data-tour="nav-tabs"]');
	await expect(nav).toBeVisible({ timeout: 10_000 });

	// All four tab labels must be present (desktop: role="tab"; mobile: plain buttons).
	for (const label of ['Dashboard', 'Duplicates', 'Tools', 'Settings']) {
		await expect(nav.getByText(label, { exact: true })).toBeVisible();
	}

	// Click the Duplicates tab.
	await nav.getByText('Duplicates', { exact: true }).click();

	// The hash route should change to #/duplicates.
	await page.waitForURL(/#\/duplicates/, { timeout: 10_000 });

	// The Dashboard heading should no longer be present (route changed).
	await expect(
		page.getByRole('heading', { name: 'Dashboard', level: 1 })
	).toBeHidden();
});

// ---------------------------------------------------------------------------
// 4. Navigating to Settings and back to Dashboard
// ---------------------------------------------------------------------------

test('Settings tab is reachable and has data-tour="settings-nav"', async ({ page }) => {
	await gotoSnopix(page);

	// AppShellDesktop gives the Settings button data-tour="settings-nav".
	const settingsBtn = page.locator('[data-tour="settings-nav"]');
	await expect(settingsBtn).toBeVisible({ timeout: 10_000 });

	await settingsBtn.click();
	await page.waitForURL(/#\/settings/, { timeout: 10_000 });

	// Navigate back to Dashboard via the tab.
	await page.locator('[data-tour="nav-tabs"]').getByText('Dashboard', { exact: true }).click();
	await page.waitForURL(/#\/dashboard/, { timeout: 10_000 });

	await expect(
		page.getByRole('heading', { name: 'Dashboard', level: 1 })
	).toBeVisible({ timeout: 10_000 });
});
