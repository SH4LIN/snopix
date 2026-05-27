/**
 * Lightweight REST client for the plugins.php uninstall modal.
 *
 * The plugins.php bundle cannot share the admin app's @wordpress/api-fetch
 * setup because it runs in a different page context with no apiFetch
 * pre-registered. We use plain `fetch` with the X-WP-Nonce header. Both
 * endpoints are existing `manage_options`-gated routes.
 */

export interface Stats {
	indexed: number;
	duplicateGroups: number;
}

export async function fetchStats(
	restUrl: string,
	nonce: string,
	signal?: AbortSignal
): Promise<Stats> {
	const headers = {
		Accept: 'application/json',
		'X-WP-Nonce': nonce,
	};

	const [statusRes, dupRes] = await Promise.all([
		fetch(`${restUrl}status`, { headers, signal, credentials: 'same-origin' }),
		fetch(`${restUrl}duplicates/status`, {
			headers,
			signal,
			credentials: 'same-origin',
		}),
	]);

	if (!statusRes.ok) {
		throw new Error(`status ${statusRes.status}`);
	}
	const statusJson = (await statusRes.json()) as { indexed?: number };

	let duplicateGroups = 0;
	if (dupRes.ok) {
		const dupJson = (await dupRes.json()) as { group_count?: number };
		duplicateGroups = dupJson.group_count ?? 0;
	}

	return {
		indexed: statusJson.indexed ?? 0,
		duplicateGroups,
	};
}
