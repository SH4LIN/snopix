/**
 * Extended Playwright test fixtures for Snopix e2e tests.
 *
 * Adds `requestUtils` (worker-scoped) so tests can perform REST API operations
 * (upload media, create posts) without going through the browser UI.
 */
import { test as base, expect } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

const test = base.extend<{}, { requestUtils: RequestUtils }>({
	requestUtils: [
		async ({}, use, workerInfo) => {
			const requestUtils = await RequestUtils.setup({
				user: { username: 'admin', password: 'password' },
				baseURL: workerInfo.project.use.baseURL || 'http://localhost:8000',
			});
			await use(requestUtils);
		},
		{ scope: 'worker' },
	],
});

export { test, expect };
