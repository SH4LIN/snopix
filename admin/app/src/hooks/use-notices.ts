import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '../lib/api';

export type NoticeSeverity = 'info' | 'success' | 'warning';

export interface FeatureNotice {
	id: string;
	title: string;
	body: string;
	icon: string;
	severity: NoticeSeverity;
	since_version: string;
	cta_label: string;
	cta_route: string;
	cta_url: string;
}

const NOTICES_KEY = ['feature-notices'] as const;

/**
 * Fetch the active feature-notice list for the current user.
 *
 * Server returns only notices the user has NOT dismissed, in registry order.
 * Cached aggressively because the list only changes when (a) the user
 * dismisses one (handled by optimistic update in `useDismissNotice`) or
 * (b) a plugin update ships a new notice — both are rare events.
 *
 * @return {import('@tanstack/react-query').UseQueryResult<FeatureNotice[]>}
 */
export function useNotices() {
	return useQuery<FeatureNotice[]>({
		queryKey: NOTICES_KEY,
		queryFn: () => apiFetch<FeatureNotice[]>('snopix/v1/notices'),
		staleTime: 5 * 60_000,
		refetchOnWindowFocus: false,
	});
}

/**
 * Mutation that records dismissal of a feature notice for the current user.
 *
 * Performs an optimistic update so the banner disappears immediately; if the
 * server rejects the dismiss (e.g. unknown ID) the cache is rolled back from
 * the snapshot taken at mutation start.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<{ dismissed: boolean; id: string }, Error, string, { previous?: FeatureNotice[] }>}
 */
export function useDismissNotice() {
	const qc = useQueryClient();

	return useMutation<
		{ dismissed: boolean; id: string },
		Error,
		string,
		{ previous?: FeatureNotice[] }
	>({
		mutationFn: (id) =>
			apiFetch<{ dismissed: boolean; id: string }>({
				path: `snopix/v1/notices/${encodeURIComponent(id)}/dismiss`,
				method: 'POST',
			}),
		onMutate: async (id) => {
			await qc.cancelQueries({ queryKey: NOTICES_KEY });
			const previous = qc.getQueryData<FeatureNotice[]>(NOTICES_KEY);
			qc.setQueryData<FeatureNotice[]>(
				NOTICES_KEY,
				(previous ?? []).filter((n) => n.id !== id)
			);
			return { previous };
		},
		onError: (_err, _id, context) => {
			if (context?.previous) {
				qc.setQueryData(NOTICES_KEY, context.previous);
			}
		},
	});
}

/**
 * Mutation that bulk-dismisses every active notice for the current user.
 *
 * Uses the same optimistic-update pattern as the single-dismiss mutation:
 * empties the cached list immediately, rolls back if the request fails.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<{ dismissed: boolean; added: number }, Error, void, { previous?: FeatureNotice[] }>}
 */
export function useDismissAllNotices() {
	const qc = useQueryClient();

	return useMutation<
		{ dismissed: boolean; added: number },
		Error,
		void,
		{ previous?: FeatureNotice[] }
	>({
		mutationFn: () =>
			apiFetch<{ dismissed: boolean; added: number }>({
				path: 'snopix/v1/notices/dismiss-all',
				method: 'POST',
			}),
		onMutate: async () => {
			await qc.cancelQueries({ queryKey: NOTICES_KEY });
			const previous = qc.getQueryData<FeatureNotice[]>(NOTICES_KEY);
			qc.setQueryData<FeatureNotice[]>(NOTICES_KEY, []);
			return { previous };
		},
		onError: (_err, _vars, context) => {
			if (context?.previous) {
				qc.setQueryData(NOTICES_KEY, context.previous);
			}
		},
	});
}
