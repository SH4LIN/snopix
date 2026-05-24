import { useEffect, useRef } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStore } from '../store/use-store';
import { ConflictError } from './use-reindex';

declare const ps_data: { rest_url: string; nonce: string };

export interface DuplicateImage {
	id: number;
	title: string;
	filename: string;
	file_size: number;
	width: number;
	height: number;
	mime_type: string;
	thumbnail_url: string;
	full_url: string;
}

export interface DuplicateGroup {
	match_type: 'exact' | 'perceptual';
	images: DuplicateImage[];
}

export interface DuplicatesData {
	groups: DuplicateGroup[];
	last_scanned: string;
	group_count: number;
}

export interface DuplicateScanProgress {
	done: number;
	total: number;
	status: 'idle' | 'running' | 'done';
}

const DONE_RESET_MS = 3_000;

/**
 * Lightweight wrapper around `fetch` that injects the WP REST nonce and the
 * Pixel Scout REST base URL. Caller-supplied `init` fields override defaults.
 *
 * @param {string}       path REST sub-path appended to `ps_data.rest_url`.
 * @param {RequestInit=} init Optional Fetch init overrides.
 *
 * @return {Promise<Response>} Raw fetch response — callers must inspect `ok`.
 */
async function apiFetch(path: string, init?: RequestInit): Promise<Response> {
	return fetch(`${ps_data.rest_url}${path}`, {
		headers: { 'X-WP-Nonce': ps_data.nonce },
		...init,
	});
}

/**
 * Fetch the cached duplicate-scan result via `GET /wp-json/ps/v1/duplicates`.
 *
 * Cached for 60 s on the client to keep the Duplicates tab responsive while
 * the user toggles between routes.
 *
 * @return {import('@tanstack/react-query').UseQueryResult<DuplicatesData>}
 */
export function useDuplicates() {
	return useQuery<DuplicatesData>({
		queryKey: ['duplicates'],
		queryFn: async () => {
			const res = await apiFetch('duplicates');
			if (!res.ok) throw new Error('Failed to fetch duplicates');
			return res.json();
		},
		staleTime: 60_000,
	});
}

/**
 * Mutation that POSTs to `/wp-json/ps/v1/duplicates/scan` to schedule a fresh
 * duplicate-detection job. On success flips the duplicate-scan state to
 * `'running'` so the polling hook starts firing.
 *
 * Rejects with {@link ConflictError} when the server returns 409 so the
 * caller can surface a toast instead of treating it as a generic failure.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<unknown, Error, void>}
 */
export function useStartDuplicateScan() {
	const { setDuplicateScanState } = useStore();
	const qc = useQueryClient();

	return useMutation({
		mutationFn: async () => {
			const res = await apiFetch('duplicates/scan', { method: 'POST' });
			if (res.status === 409) {
				const body = await res.json().catch(() => ({}));
				throw new ConflictError(
					body?.message ?? 'A duplicate scan is already in progress.',
					body?.code ?? 'scan_running'
				);
			}
			if (!res.ok) throw new Error('Failed to start scan');
			return res.json();
		},
		onSuccess: () => {
			setDuplicateScanState('running');
			qc.invalidateQueries({ queryKey: ['duplicates-progress'] });
		},
	});
}

/**
 * Mutation that POSTs to `/wp-json/ps/v1/duplicates/reset` to abort an
 * in-flight scan, clear the cross-batch state, and reset progress to idle.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<unknown, Error, void>}
 */
export function useResetDuplicateScan() {
	const { setDuplicateScanState } = useStore();
	const qc = useQueryClient();

	return useMutation({
		mutationFn: async () => {
			const res = await apiFetch('duplicates/reset', { method: 'POST' });
			if (!res.ok) throw new Error('Reset failed');
			return res.json();
		},
		onSuccess: () => {
			setDuplicateScanState('idle');
			qc.invalidateQueries({ queryKey: ['duplicates-progress'] });
		},
	});
}

/**
 * Poll `/wp-json/ps/v1/duplicates/progress` while a scan is running and drive
 * the running → done → idle transition. After completion the `/duplicates`
 * query is invalidated so the new group list is fetched.
 *
 * @return {DuplicateScanProgress|undefined} Latest progress payload or undefined while idle.
 */
export function useDuplicateScanProgress() {
	const { duplicateScanState, setDuplicateScanState } = useStore();
	const isRunning = duplicateScanState === 'running';
	const resetTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
	const qc = useQueryClient();

	useEffect(() => {
		return () => {
			if (resetTimerRef.current) clearTimeout(resetTimerRef.current);
		};
	}, []);

	// One-shot mount probe: if the server says a scan is running, flip the
	// local state to running so the polling query becomes enabled. Without
	// this a hard reload during a scan would leave the UI thinking it was
	// idle and allow the user to start a duplicate job.
	//
	// `queryFn` is captured once (staleTime: Infinity) so closing over
	// `duplicateScanState` from the hook scope would freeze the value forever
	// — read live store state at signal time instead.
	useQuery<DuplicateScanProgress>({
		queryKey: ['duplicates-progress-hydrate'],
		queryFn: async () => {
			const res = await apiFetch('duplicates/progress');
			if (!res.ok) throw new Error('Failed to fetch scan progress');
			const body: DuplicateScanProgress = await res.json();
			if (
				body.status === 'running' &&
				useStore.getState().duplicateScanState === 'idle'
			) {
				setDuplicateScanState('running');
			}
			return body;
		},
		staleTime: Infinity,
	});

	const { data: progress } = useQuery<DuplicateScanProgress>({
		queryKey: ['duplicates-progress'],
		queryFn: async () => {
			const res = await apiFetch('duplicates/progress');
			if (!res.ok) throw new Error('Failed to fetch scan progress');
			return res.json();
		},
		enabled: isRunning,
		refetchInterval: isRunning ? 2_000 : false,
	});

	useEffect(() => {
		if (!isRunning || !progress) return;

		if (progress.status === 'done') {
			setDuplicateScanState('done');
			if (resetTimerRef.current) {
				clearTimeout(resetTimerRef.current);
			}

			resetTimerRef.current = setTimeout(() => {
				setDuplicateScanState('idle');
				qc.invalidateQueries({ queryKey: ['duplicates'] });
			}, DONE_RESET_MS);
		}
	}, [progress, isRunning, setDuplicateScanState, qc]);

	return progress;
}

/**
 * Mutation that deletes a single attachment via
 * `DELETE /wp-json/ps/v1/duplicates/attachment/{id}`. Used by both the
 * per-group delete button and the bulk-delete pass.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<unknown, Error, number>}
 */
export function useDeleteAttachment() {
	const qc = useQueryClient();

	return useMutation({
		mutationFn: async (id: number) => {
			const res = await apiFetch(`duplicates/attachment/${id}`, {
				method: 'DELETE',
			});
			if (!res.ok) throw new Error('Failed to delete attachment');
			return res.json();
		},
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: ['duplicates'] });
		},
	});
}
