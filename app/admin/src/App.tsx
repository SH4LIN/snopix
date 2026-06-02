import { useEffect, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useStore } from './store/use-store';
import { apiFetch } from './lib/api';
import { withResponsive } from './lib/with-responsive';
import AppShellDesktop from './components/shell/AppShellDesktop';
import AppShellMobile from './components/shell/AppShellMobile';
import TourProvider from './components/tour/TourProvider';

interface ProgressResponse {
	status: 'idle' | 'running' | 'done' | 'stalled';
}

const safeProgress = async (path: string): Promise<ProgressResponse> => {
	try {
		return await apiFetch<ProgressResponse>(`snopix/v1/${path}`);
	} catch {
		return { status: 'idle' };
	}
};

/**
 * Sync the Zustand store with any in-flight indexing or duplicate-scan jobs at
 * boot. See the replay-guard rationale on each effect — both shells inherit
 * the same hydration logic so a hard reload during an active job leaves the
 * UI in the correct running/stalled state.
 *
 * @return {void}
 */
function useInitProgress() {
	const setIndexingState = useStore((s) => s.setIndexingState);
	const setDuplicateScanState = useStore((s) => s.setDuplicateScanState);
	const handledIndexRef = useRef<string | null>(null);
	const handledDupeRef = useRef<string | null>(null);

	const { data: indexProgress } = useQuery<ProgressResponse>({
		queryKey: ['init-index-progress'],
		queryFn: () => safeProgress('progress'),
		staleTime: Infinity,
		refetchInterval: false,
	});

	const { data: dupeProgress } = useQuery<ProgressResponse>({
		queryKey: ['init-dupe-progress'],
		queryFn: () => safeProgress('duplicates/progress'),
		staleTime: Infinity,
		refetchInterval: false,
	});

	useEffect(() => {
		const status = indexProgress?.status;
		if (!status || handledIndexRef.current === status) {
			return;
		}
		handledIndexRef.current = status;
		if (
			(status === 'running' || status === 'stalled') &&
			useStore.getState().indexingState === 'idle'
		) {
			setIndexingState(status);
		}
	}, [indexProgress?.status, setIndexingState]);

	useEffect(() => {
		const status = dupeProgress?.status;
		if (!status || handledDupeRef.current === status) {
			return;
		}
		handledDupeRef.current = status;
		if (
			status === 'running' &&
			useStore.getState().duplicateScanState === 'idle'
		) {
			setDuplicateScanState('running');
		}
	}, [dupeProgress?.status, setDuplicateScanState]);
}

const AppShell = withResponsive(AppShellDesktop, AppShellMobile, 'AppShell');

/**
 * Root admin app component.
 *
 * Runs progress hydration once, then delegates to the responsive shell —
 * `AppShellDesktop` above the mobile breakpoint, `AppShellMobile` below it.
 *
 * @return {JSX.Element}
 */
export default function App() {
	useInitProgress();
	return (
		<TourProvider>
			<AppShell />
		</TourProvider>
	);
}
