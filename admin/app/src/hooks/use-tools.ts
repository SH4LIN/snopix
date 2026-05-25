import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStore } from '../store/use-store';
import { apiFetch } from '../lib/api';

const post = <T>(path: string): Promise<T> =>
	apiFetch<T>({ path: `snopix/v1/${path}`, method: 'POST' });
const get = <T>(path: string): Promise<T> => apiFetch<T>(`snopix/v1/${path}`);

/**
 * Mutation that triggers a full wipe-and-reindex via
 * `POST /wp-json/snopix/v1/tools/reindex-all`. Sets the indexing state to
 * `'running'` and invalidates every status-driven query on success.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<{scheduled: boolean}, Error, void>}
 */
export function useReindexAll() {
	const { setIndexingState } = useStore();
	const qc = useQueryClient();
	return useMutation({
		mutationFn: () => post<{ scheduled: boolean }>('tools/reindex-all'),
		onSuccess: () => {
			setIndexingState('running');
			qc.invalidateQueries({ queryKey: ['status'] });
			qc.invalidateQueries({ queryKey: ['images'] });
			qc.invalidateQueries({ queryKey: ['orphans'] });
		},
	});
}

/**
 * Mutation that empties the entire fingerprint table via
 * `POST /wp-json/snopix/v1/tools/clear-index`. Returns the deletion count for the
 * Tools tab to display.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<{deleted: number}, Error, void>}
 */
export function useClearIndex() {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: () => post<{ deleted: number }>('tools/clear-index'),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: ['status'] });
			qc.invalidateQueries({ queryKey: ['images'] });
			qc.invalidateQueries({ queryKey: ['orphans'] });
		},
	});
}

/**
 * Mutation that removes index rows whose backing attachment no longer exists,
 * via `POST /wp-json/snopix/v1/tools/delete-orphans`.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<{deleted: number}, Error, void>}
 */
export function useDeleteOrphans() {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: () => post<{ deleted: number }>('tools/delete-orphans'),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: ['status'] });
			qc.invalidateQueries({ queryKey: ['images'] });
			qc.invalidateQueries({ queryKey: ['orphans'] });
		},
	});
}

/**
 * Mutation that flushes plugin caches and progress transients via
 * `POST /wp-json/snopix/v1/tools/clear-cache`. Invalidates every cached query so
 * the UI re-reads from the server.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<{cleared: boolean}, Error, void>}
 */
export function useClearCache() {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: () => post<{ cleared: boolean }>('tools/clear-cache'),
		onSuccess: () => {
			qc.invalidateQueries();
		},
	});
}

/**
 * Poll `/wp-json/snopix/v1/tools/orphans` every 30 s for the current orphan-row
 * count so the Tools tab can render the actionable number on the
 * "Delete Orphans" card.
 *
 * @return {import('@tanstack/react-query').UseQueryResult<{orphans: number}>}
 */
export function useOrphanCount() {
	return useQuery<{ orphans: number }>({
		queryKey: ['orphans'],
		queryFn: () => get<{ orphans: number }>('tools/orphans'),
		refetchInterval: 30_000,
	});
}
