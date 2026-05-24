import { useEffect } from 'react';
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
	const { indexingState, setIndexingState } = useStore();

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
		refetchInterval: 30_000,
	});

	useEffect(() => {
		const serverStatus = query.data?.progress?.status;
		if (!serverStatus) return;

		// Only promote idle → running/stalled; never demote a locally-tracked
		// running/done back to idle here — the polling state machine in
		// useIndexingProgress owns that transition.
		if (
			(serverStatus === 'running' || serverStatus === 'stalled') &&
			indexingState === 'idle'
		) {
			setIndexingState(serverStatus);
		}
	}, [query.data?.progress?.status, indexingState, setIndexingState]);

	return query;
}
