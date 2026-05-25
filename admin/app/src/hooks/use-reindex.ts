import { useEffect, useRef } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStore } from '../store/use-store';
import { apiFetch } from '../lib/api';

export interface Progress {
	done: number;
	total: number;
	status: 'idle' | 'running' | 'done' | 'stalled';
}

const STALL_MS = 45_000;
const DONE_RESET_MS = 3_000;

/**
 * Poll `/progress` while indexing is running and drive the indexing state
 * machine (running → done → idle, or running → stalled → idle after 45 s of
 * no counter movement). Returns the latest progress payload for UI use.
 *
 * @return {Progress|undefined} Latest progress payload, or undefined while idle.
 */
export function useIndexingProgress() {
	const { indexingState, setIndexingState } = useStore();
	const isRunning = indexingState === 'running';

	const lastDoneRef = useRef<number>(-1);
	const lastChangeRef = useRef<number>(0);
	const resetTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

	// Reset stall-detection refs whenever a new run starts.
	useEffect(() => {
		if (indexingState === 'running') {
			lastDoneRef.current = -1;
			lastChangeRef.current = Date.now();
			if (resetTimerRef.current) {
				clearTimeout(resetTimerRef.current);
				resetTimerRef.current = null;
			}
		}
	}, [indexingState]);

	// Cleanup timer on unmount.
	useEffect(() => {
		return () => {
			if (resetTimerRef.current) {
				clearTimeout(resetTimerRef.current);
			}
		};
	}, []);

	const { data: progress } = useQuery<Progress>({
		queryKey: ['progress'],
		queryFn: () => apiFetch<Progress>('snopix/v1/progress'),
		enabled: isRunning,
		refetchInterval: isRunning ? 2_000 : false,
	});

	// State machine transitions — runs outside render cycle.
	useEffect(() => {
		if (!isRunning || !progress) {
			return;
		}

		if (progress.status === 'done') {
			setIndexingState('done');
			if (resetTimerRef.current) {
				clearTimeout(resetTimerRef.current);
			}

			resetTimerRef.current = setTimeout(() => {
				setIndexingState('idle');
				window.location.reload();
			}, DONE_RESET_MS);
			return;
		}

		if (progress.status === 'stalled') {
			setIndexingState('stalled');
			return;
		}

		if (progress.done !== lastDoneRef.current) {
			lastDoneRef.current = progress.done;
			lastChangeRef.current = Date.now();
		} else if (Date.now() - lastChangeRef.current > STALL_MS) {
			setIndexingState('stalled');
		}
	}, [progress, isRunning, setIndexingState]);

	return progress;
}

/**
 * Mutation that POSTs to `/wp-json/snopix/v1/reindex` to start an "index missing"
 * background job. On success flips the global state to `'running'` and
 * invalidates the `/status` query so the counter updates immediately.
 *
 * Rejects with `ConflictError` (re-exported from `lib/api`) when the server
 * returns 409 so the caller can show a toast instead of treating it as a
 * generic failure.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<unknown, Error, void>}
 */
export function useReindex() {
	const { setIndexingState } = useStore();
	const qc = useQueryClient();

	return useMutation({
		mutationFn: () => apiFetch({ path: 'snopix/v1/reindex', method: 'POST' }),
		onSuccess: () => {
			setIndexingState('running');
			qc.invalidateQueries({ queryKey: ['status'] });
		},
	});
}

/**
 * Mutation that POSTs to `/wp-json/snopix/v1/reset-progress` to abort the
 * in-flight bulk job, clear pending queue, and reset the progress envelope.
 * Used by the Reset button surfaced on stalled/running progress bars.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<unknown, Error, void>}
 */
export function useResetProgress() {
	const { setIndexingState } = useStore();
	const qc = useQueryClient();

	return useMutation({
		mutationFn: () =>
			apiFetch({ path: 'snopix/v1/reset-progress', method: 'POST' }),
		onSuccess: () => {
			setIndexingState('idle');
			qc.invalidateQueries({ queryKey: ['status'] });
			qc.invalidateQueries({ queryKey: ['progress'] });
		},
	});
}
