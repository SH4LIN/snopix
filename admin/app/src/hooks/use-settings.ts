import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

declare const ps_data: { rest_url: string; nonce: string };

export interface PSSettings {
	search_visibility: 'anyone' | 'logged_in';
}

/**
 * Fetch the persisted Pixel Scout settings via `GET /wp-json/ps/v1/settings`.
 *
 * Cached for 30 s — settings rarely change and the form is the only writer,
 * which invalidates the query manually on success.
 *
 * @return {import('@tanstack/react-query').UseQueryResult<PSSettings>}
 */
export function useSettings() {
	return useQuery<PSSettings>({
		queryKey: ['settings'],
		queryFn: async () => {
			const res = await fetch(`${ps_data.rest_url}settings`, {
				headers: { 'X-WP-Nonce': ps_data.nonce },
			});
			if (!res.ok) {
				throw new Error('Failed to fetch settings');
			}
			return res.json();
		},
		staleTime: 30_000,
	});
}

/**
 * Mutation that persists a settings payload via `POST /wp-json/ps/v1/settings`.
 *
 * On success the `['settings']` query is invalidated so any other open
 * consumer (e.g. a future widget) re-reads the canonical state.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<PSSettings, Error, Partial<PSSettings>>}
 */
export function useUpdateSettings() {
	const qc = useQueryClient();
	return useMutation<PSSettings, Error, Partial<PSSettings>>({
		mutationFn: async (payload) => {
			const res = await fetch(`${ps_data.rest_url}settings`, {
				method: 'POST',
				headers: {
					'X-WP-Nonce': ps_data.nonce,
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(payload),
			});
			if (!res.ok) {
				throw new Error('Failed to update settings');
			}
			return res.json();
		},
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: ['settings'] });
		},
	});
}
