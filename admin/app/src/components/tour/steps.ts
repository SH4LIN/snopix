/**
 * Step configuration for the first-run walkthrough.
 *
 * Pure data — no React imports — keyed on the `data-tour` attributes the
 * shells render. A step with no `target` renders as a centered welcome card.
 * A step with `route` triggers a router navigation before measuring.
 */

import { __ } from '@wordpress/i18n';

export interface TourStep {
	target?: string;
	route?: string;
	title: string;
	content: string;
}

export function buildSteps(): TourStep[] {
	return [
		{
			route: '/dashboard',
			title: __('Welcome to Snopix', 'snopix'),
			content: __(
				'Reverse-image search and duplicate detection for your media library. Take a minute to see the main features.',
				'snopix'
			),
		},
		{
			route: '/dashboard',
			target: '[data-tour="dashboard-stats"]',
			title: __('Index health at a glance', 'snopix'),
			content: __(
				'Total, indexed, pending, and failed counts update as Snopix fingerprints your library.',
				'snopix'
			),
		},
		{
			route: '/dashboard',
			target: '[data-tour="reindex-button"]',
			title: __('Start by indexing your library', 'snopix'),
			content: __(
				'Snopix needs to fingerprint your media before it can search or detect duplicates. Click here to kick off a bulk index job — pending items will be processed in the background.',
				'snopix'
			),
		},
		{
			route: '/dashboard',
			target: '[data-tour="search"]',
			title: __('Search by image', 'snopix'),
			content: __(
				'Drop or upload an image here to find visually similar items already in your indexed library. Great for spotting reused photos before publishing.',
				'snopix'
			),
		},
		{
			route: '/duplicates',
			target: '[data-tour="duplicates-scan"]',
			title: __('Scan for duplicates', 'snopix'),
			content: __(
				'Run a scan to group visually identical attachments. You can then pick which copy to keep and bulk-delete the rest.',
				'snopix'
			),
		},
		{
			target: '[data-tour="nav-tabs"]',
			title: __('Switch between sections', 'snopix'),
			content: __(
				'Dashboard for search and stats, Duplicates for cleanup, Tools for advanced jobs, Settings for configuration.',
				'snopix'
			),
		},
		{
			target: '[data-tour="settings-nav"]',
			title: __('Settings & cleanup', 'snopix'),
			content: __(
				'Tune match thresholds, rate-limits, and what happens on uninstall. The walkthrough will not auto-open again.',
				'snopix'
			),
		},
	];
}
