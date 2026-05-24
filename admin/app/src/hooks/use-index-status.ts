import { useEffect, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useStore } from '../store/use-store';

declare const ps_data: { rest_url: string; nonce: string };

export interface IndexProgress {
	done: number;
	total: number;
	status: 'idle' | 'running' | 'done' | 'stalled';
}

interface IndexStatus {
	total: number;
	indexed: number;
	pending: number;
	failed: number;
	progress?: IndexProgress;
}

/**
 * Poll the indexing counters every 30 s from `GET /wp-json/ps/v1/status`.
 *
 * Also hydrates the global `indexingState` from the server-side progress
 * envelope so a hard reload during an active bulk job leaves the UI in the
 * correct running/stalled state instead of resetting to idle (which would
 * let the user double-schedule a job).
 *
 * @return {import('@tanstack/react-query').UseQueryResult<IndexStatus>}
 */
export function useIndexStatus() {
	const setIndexingState = useStore((s) => s.setIndexingState);
	const indexingState = useStore((s) => s.indexingState);
	const lastHandledRef = useRef<string | null>(null);

	// Poll fast while a bulk job is in flight so the "X of Y indexed"
	// counter stays in sync with the progress bar that updates every 2 s;
	// fall back to the cheap 30 s cadence when nothing is happening.
	const refetchInterval =
		indexingState === 'running' || indexingState === 'stalled'
			? 2_000
			: 30_000;

	const query = useQuery<IndexStatus>({
		queryKey: ['status'],
		queryFn: async () => {
			const res = await fetch(`${ps_data.rest_url}status`, {
				headers: { 'X-WP-Nonce': ps_data.nonce },
			});
			if (!res.ok) {
				throw new Error('Failed to fetch status');
			}
			return res.json();
		},
		refetchInterval,
	});

	useEffect(() => {
		const serverStatus = query.data?.progress?.status;
		if (!serverStatus) return;

		// Fire once per distinct server-reported status so a local state flip
		// (e.g. Reset → 'idle') cannot replay a stale 'running'/'stalled'
		// payload and undo the user's action. The polling state machine in
		// useIndexingProgress owns transitions out of running/stalled.
		if (lastHandledRef.current === serverStatus) return;
		lastHandledRef.current = serverStatus;

		if (serverStatus === 'running' || serverStatus === 'stalled') {
			// Read the current state at the moment of the server signal so we
			// don't fight a concurrent local transition.
			if (useStore.getState().indexingState === 'idle') {
				setIndexingState(serverStatus);
			}
		}
	}, [query.data?.progress?.status, setIndexingState]);

	return query;
}
