import { useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { useQuery } from '@tanstack/react-query';
import { useNavigate, useRouterState, Outlet } from '@tanstack/react-router';
import { useStore } from './store/use-store';
import { apiFetch } from './lib/api';
import { useIndexStatus } from './hooks/use-index-status';
import { useReindex } from './hooks/use-reindex';
import { BrandMark, IconUpload } from './components/icons';

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
 * boot. See note on the original implementation for the replay-guard rationale.
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

const TABS = [
	{ id: 'dashboard', path: '/dashboard', label: __( 'Dashboard', 'snopix' ) },
	{ id: 'duplicates', path: '/duplicates', label: __( 'Duplicates', 'snopix' ) },
	{ id: 'tools', path: '/tools', label: __( 'Tools', 'snopix' ) },
	{ id: 'settings', path: '/settings', label: __( 'Settings', 'snopix' ) },
] as const;

/**
 * Top-level admin app shell.
 *
 * Renders the plugin brand header (logo, title, doc link, "Index remaining"
 * CTA), the Dashboard / Duplicates / Tools / Settings tab strip, and the
 * router outlet for the active route's component.
 *
 * @return {JSX.Element}
 */
export default function App() {
	useInitProgress();
	const navigate = useNavigate();
	const pathname = useRouterState({ select: (s) => s.location.pathname });
	const { data: status } = useIndexStatus();
	const { indexingState } = useStore();
	const { mutate: startReindex, isPending } = useReindex();

	const pending = status?.pending ?? 0;
	const canReindex = pending > 0 && indexingState === 'idle';

	return (
		<div id="snopix-app" className="w-full">
			<div className="bg-snopix-bg border-b border-snopix-border">
				<div className="mx-auto w-full max-w-[1240px] px-10 pt-5">
					<div className="flex items-center justify-between mb-3.5">
						<div className="flex items-center gap-3">
							<BrandMark size={32} />
							<div>
								<div className="text-[18px] font-semibold tracking-[-0.015em] leading-none">
									{__('Snopix', 'snopix')}
								</div>
								<div className="text-[12px] text-snopix-muted mt-1">
									{__(
										'Reverse-image search & duplicate detection',
										'snopix'
									)}
								</div>
							</div>
						</div>
						<div className="flex items-center gap-2">
							<button
								className="snopix-btn snopix-btn--sm"
								onClick={() => startReindex()}
								disabled={!canReindex || isPending}
								title={
									!canReindex
										? __(
												'No pending attachments.',
												'snopix'
											)
										: undefined
								}
							>
								<IconUpload size={14} />{' '}
								{__('Index remaining', 'snopix')}
								{pending > 0 ? ` (${pending})` : ''}
							</button>
						</div>
					</div>
					<div
						className="flex gap-5 border-b border-transparent"
						role="tablist"
					>
						{TABS.map((t) => (
							<button
								key={t.id}
								className="snopix-tab"
								role="tab"
								aria-current={pathname === t.path}
								onClick={() => navigate({ to: t.path })}
							>
								{__(t.label, 'snopix')}
							</button>
						))}
					</div>
				</div>
			</div>

			<div className="mx-auto w-full max-w-[1240px] px-10 py-8">
				<Outlet />
			</div>
		</div>
	);
}
