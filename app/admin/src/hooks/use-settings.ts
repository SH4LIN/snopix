import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '../lib/api';

export interface PSSettings {
	search_visibility: 'anyone' | 'logged_in';
	rate_limit: number;
	match_threshold: number;
	batch_size: number;
	downscale_max: number;
	duplicate_threshold: number;
	drop_on_uninstall: boolean;
}

/**
 * Fetch the persisted Snopix settings via `GET /wp-json/snopix/v1/settings`.
 *
 * Cached for 30 s — settings rarely change and the form is the only writer,
 * which invalidates the query manually on success.
 *
 * @return {import('@tanstack/react-query').UseQueryResult<PSSettings>}
 */
export function useSettings() {
	return useQuery<PSSettings>({
		queryKey: ['settings'],
		queryFn: () => apiFetch<PSSettings>('snopix/v1/settings'),
		staleTime: 60_000,
		refetchOnWindowFocus: false,
	});
}

/**
 * Mutation that persists a settings payload via `POST /wp-json/snopix/v1/settings`.
 *
 * On success the `['settings']` query is invalidated so any other open
 * consumer (e.g. a future widget) re-reads the canonical state.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<PSSettings, Error, Partial<PSSettings>>}
 */
export function useUpdateSettings() {
	const qc = useQueryClient();
	return useMutation<PSSettings, Error, Partial<PSSettings>>({
		mutationFn: (payload) =>
			apiFetch<PSSettings>({
				path: 'snopix/v1/settings',
				method: 'POST',
				data: payload,
			}),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: ['settings'] });
		},
	});
}
