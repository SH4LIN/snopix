import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStore } from '../store/use-store';

declare const ps_data: { rest_url: string; nonce: string };

/**
 * Issue an authenticated POST to a Pixel Scout REST endpoint and return the
 * decoded JSON body. Throws on a non-2xx status so React Query treats the call
 * as a failed mutation.
 *
 * @param {string} path REST sub-path appended to `ps_data.rest_url`.
 *
 * @return {Promise<T>} Parsed JSON response body.
 */
async function post<T>(path: string): Promise<T> {
	const res = await fetch(`${ps_data.rest_url}${path}`, {
		method: 'POST',
		headers: { 'X-WP-Nonce': ps_data.nonce },
	});
	if (!res.ok) throw new Error(`${path} failed`);
	return res.json();
}

/**
 * Issue an authenticated GET to a Pixel Scout REST endpoint and return the
 * decoded JSON body. Throws on a non-2xx status.
 *
 * @param {string} path REST sub-path appended to `ps_data.rest_url`.
 *
 * @return {Promise<T>} Parsed JSON response body.
 */
async function get<T>(path: string): Promise<T> {
	const res = await fetch(`${ps_data.rest_url}${path}`, {
		headers: { 'X-WP-Nonce': ps_data.nonce },
	});
	if (!res.ok) throw new Error(`${path} failed`);
	return res.json();
}

/**
 * Mutation that triggers a full wipe-and-reindex via
 * `POST /wp-json/ps/v1/tools/reindex-all`. Sets the indexing state to
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
 * `POST /wp-json/ps/v1/tools/clear-index`. Returns the deletion count for the
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
 * via `POST /wp-json/ps/v1/tools/delete-orphans`.
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
 * `POST /wp-json/ps/v1/tools/clear-cache`. Invalidates every cached query so
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
 * Poll `/wp-json/ps/v1/tools/orphans` every 30 s for the current orphan-row
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

export interface SubsizeDiff {
	new: string[];
	removed: string[];
	changed: Array<{
		name: string;
		old: { w: number; h: number; crop: boolean };
		new: { w: number; h: number; crop: boolean };
	}>;
	has_changes: boolean;
}

export interface SubsizeProgress {
	done: number;
	total: number;
	status: 'idle' | 'running' | 'done' | 'stalled';
}

/**
 * Poll registered-subsize diff every 30s — but only while `has_changes` is
 * still false. Once changes are detected, polling stops because the diff
 * cannot grow more "true" without a user action.
 */
export function useSubsizeDiff() {
	return useQuery<SubsizeDiff>({
		queryKey: ['subsize-diff'],
		queryFn: () => get<SubsizeDiff>('tools/subsizes/diff'),
		refetchInterval: (query) =>
			query.state.data?.has_changes === false ? 30_000 : false,
	});
}

/**
 * Backfill only missing subsizes. Does NOT update the snapshot.
 */
export function useRegenMissing() {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: () =>
			post<{ scheduled: boolean; count: number }>(
				'tools/subsizes/regen-missing'
			),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: ['subsize-progress'] });
			qc.invalidateQueries({ queryKey: ['status'] });
		},
	});
}

/**
 * Full rebuild + snapshot acknowledgement.
 */
export function useRegenAll() {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: () =>
			post<{ scheduled: boolean; count: number }>(
				'tools/subsizes/regen-all'
			),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: ['subsize-diff'] });
			qc.invalidateQueries({ queryKey: ['subsize-progress'] });
			qc.invalidateQueries({ queryKey: ['status'] });
		},
	});
}

/**
 * Dismiss `has_changes` without rebuilding (escape hatch for removed-only diffs).
 */
export function useAcknowledgeSubsizeDiff() {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: () =>
			post<{ acknowledged: boolean }>('tools/subsizes/acknowledge'),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: ['subsize-diff'] });
		},
	});
}

/**
 * Poll regen progress every 5s while running so the card can render a bar.
 */
export function useSubsizeProgress() {
	return useQuery<SubsizeProgress>({
		queryKey: ['subsize-progress'],
		queryFn: () => get<SubsizeProgress>('tools/subsizes/progress'),
		refetchInterval: (query) =>
			query.state.data?.status === 'running' ? 5_000 : false,
	});
}
