/**
 * Click-intercept utilities for the Snopix uninstall flow.
 *
 * Locates the plugin's Delete link on `wp-admin/plugins.php`, attaches a
 * capture-phase listener, and surfaces the original navigation target to the
 * consumer. If the link cannot be found — selector drift in a future WP
 * release, non-standard plugin path — the function is a no-op and the native
 * delete flow runs uninterrupted.
 */

const SLUG_ATTR = 'data-slug';

const DELETE_LINK_SELECTORS = [
	'.row-actions .delete a',
	'.delete a',
] as const;

export interface InterceptHandle {
	originalHref: string;
}

export type InterceptCallback = (handle: InterceptHandle) => void;

export function findDeleteLink(slug: string): HTMLAnchorElement | null {
	const row = document.querySelector<HTMLTableRowElement>(
		`tr[${SLUG_ATTR}="${slug}"]`
	);
	if (!row) {
		return null;
	}
	for (const selector of DELETE_LINK_SELECTORS) {
		const link = row.querySelector<HTMLAnchorElement>(selector);
		if (link && link.href) {
			return link;
		}
	}
	return null;
}

export function attachIntercept(
	slug: string,
	onIntercept: InterceptCallback
): () => void {
	const link = findDeleteLink(slug);
	if (!link) {
		// eslint-disable-next-line no-console
		console.warn('[snopix] Delete link not found, modal disabled.');
		return () => {};
	}

	const handler = (event: MouseEvent) => {
		event.preventDefault();
		event.stopPropagation();
		onIntercept({ originalHref: link.href });
	};

	link.addEventListener('click', handler, { capture: true });
	return () => link.removeEventListener('click', handler, { capture: true });
}
