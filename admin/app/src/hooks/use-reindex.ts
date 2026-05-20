import { useEffect, useRef } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStore } from '../store/use-store';

declare const ps_data: { rest_url: string; nonce: string };

export interface Progress {
	done: number;
	total: number;
	status: 'idle' | 'running' | 'done';
}

const STALL_MS = 45_000;
const DONE_RESET_MS = 3_000;
const STALLED_RESET_MS = 5_000;

/**
 * Fetch the current indexing progress envelope.
 *
 * Hits `GET /wp-json/ps/v1/progress` and throws on a non-2xx response so React
 * Query treats it as an error.
 *
 * @return {Promise<Progress>} Done/total counters and a status discriminator.
 */
async function fetchProgress(): Promise<Progress> {
	const res = await fetch(`${ps_data.rest_url}progress`, {
		headers: { 'X-WP-Nonce': ps_data.nonce },
	});
	if (!res.ok) throw new Error('Failed to fetch progress');
	return res.json();
}

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
			if (resetTimerRef.current) clearTimeout(resetTimerRef.current);
		};
	}, []);

	const { data: progress } = useQuery<Progress>({
		queryKey: ['progress'],
		queryFn: fetchProgress,
		enabled: isRunning,
		refetchInterval: isRunning ? 2_000 : false,
	});

	// State machine transitions — runs outside render cycle.
	useEffect(() => {
		if (!isRunning || !progress) return;

		if (progress.status === 'done') {
			setIndexingState('done');
			resetTimerRef.current = setTimeout(() => {
				setIndexingState('idle');
				window.location.reload();
			}, DONE_RESET_MS);
			return;
		}

		if (progress.done !== lastDoneRef.current) {
			lastDoneRef.current = progress.done;
			lastChangeRef.current = Date.now();
		} else if (Date.now() - lastChangeRef.current > STALL_MS) {
			setIndexingState('stalled');
			resetTimerRef.current = setTimeout(
				() => setIndexingState('idle'),
				STALLED_RESET_MS
			);
		}
	}, [progress, isRunning, setIndexingState]);

	return progress;
}

/**
 * Mutation that POSTs to `/wp-json/ps/v1/reindex` to start an "index missing"
 * background job. On success flips the global state to `'running'` and
 * invalidates the `/status` query so the counter updates immediately.
 *
 * @return {import('@tanstack/react-query').UseMutationResult<unknown, Error, void>}
 */
export function useReindex() {
	const { setIndexingState } = useStore();
	const qc = useQueryClient();

	return useMutation({
		mutationFn: async () => {
			const res = await fetch(`${ps_data.rest_url}reindex`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': ps_data.nonce },
			});
			if (!res.ok) throw new Error('Reindex failed');
			return res.json();
		},
		onSuccess: () => {
			setIndexingState('running');
			qc.invalidateQueries({ queryKey: ['status'] });
		},
	});
}
