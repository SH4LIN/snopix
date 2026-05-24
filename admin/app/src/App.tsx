import { useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { useQuery } from '@tanstack/react-query';
import { useNavigate, useRouterState, Outlet } from '@tanstack/react-router';
import { useStore } from './store/use-store';

declare const ps_data: { rest_url: string; nonce: string };

interface ProgressResponse {
	status: 'idle' | 'running' | 'done' | 'stalled';
}

/**
 * Sync the Zustand store with any in-flight indexing or duplicate-scan jobs at
 * boot. Runs two one-shot `/progress` REST fetches and, if either reports a
 * running job, flips the matching state to `'running'` so the rest of the UI
 * picks up where the previous session left off.
 *
 * Guarded against replay: each query's cached result is acted on at most once
 * per distinct status value. Without this guard a local transition (e.g. the
 * user clicks Reset → state = 'idle') would re-fire the effect, find the
 * still-cached server response saying `running`, and flip the state right
 * back, leaving the UI permanently stuck.
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
		queryFn: async () => {
			const res = await fetch(`${ps_data.rest_url}progress`, {
				headers: { 'X-WP-Nonce': ps_data.nonce },
			});
			return res.ok ? res.json() : { status: 'idle' };
		},
		staleTime: Infinity,
		refetchInterval: false,
	});

	const { data: dupeProgress } = useQuery<ProgressResponse>({
		queryKey: ['init-dupe-progress'],
		queryFn: async () => {
			const res = await fetch(`${ps_data.rest_url}duplicates/progress`, {
				headers: { 'X-WP-Nonce': ps_data.nonce },
			});
			return res.ok ? res.json() : { status: 'idle' };
		},
		staleTime: Infinity,
		refetchInterval: false,
	});

	useEffect(() => {
		const status = indexProgress?.status;
		if (!status || handledIndexRef.current === status) return;
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
		if (!status || handledDupeRef.current === status) return;
		handledDupeRef.current = status;
		if (
			status === 'running' &&
			useStore.getState().duplicateScanState === 'idle'
		) {
			setDuplicateScanState('running');
		}
	}, [dupeProgress?.status, setDuplicateScanState]);
}

/**
 * Top-level admin app shell.
 *
 * Renders the Pixel Scout heading and the Dashboard / Duplicates / Tools tab
 * strip. The active route's component is rendered into the router `<Outlet />`.
 * Also bootstraps the global progress store via {@link useInitProgress}.
 *
 * @return {JSX.Element}
 */
export default function App() {
	useInitProgress();

	const navigate = useNavigate();
	const pathname = useRouterState({ select: (s) => s.location.pathname });

	/**
	 * Compute the Tailwind class list for a single tab button.
	 *
	 * @param {string} path Router path the tab navigates to.
	 *
	 * @return {string} Space-separated class string with active-state styling applied.
	 */
	const tabClass = (path: string) => {
		const active = pathname === path;
		return `px-4 py-2 text-[14px] font-medium border-b-2 cursor-pointer transition-colors ${
			active
				? 'text-ps-accent border-ps-accent'
				: 'text-ps-muted border-transparent hover:text-ps-text'
		}`;
	};

	return (
		<div id="pixel-scout-app" className="p-6 w-full">
			<h1 className="text-[28px] font-bold mb-1 text-ps-text">
				{__('Pixel Scout', 'pixel-scout')}
			</h1>
			<p className="text-ps-muted text-sm mb-4">
				{__('Image similarity search', 'pixel-scout')}
			</p>

			<div className="flex gap-1 border-b border-ps-border mb-6">
				<button
					className={tabClass('/dashboard')}
					onClick={() => navigate({ to: '/dashboard' })}
				>
					{__('Dashboard', 'pixel-scout')}
				</button>
				<button
					className={tabClass('/duplicates')}
					onClick={() => navigate({ to: '/duplicates' })}
				>
					{__('Duplicates', 'pixel-scout')}
				</button>
				<button
					className={tabClass('/tools')}
					onClick={() => navigate({ to: '/tools' })}
				>
					{__('Tools', 'pixel-scout')}
				</button>
				<button
					className={tabClass('/settings')}
					onClick={() => navigate({ to: '/settings' })}
				>
					{__('Settings', 'pixel-scout')}
				</button>
			</div>

			<Outlet />
		</div>
	);
}
