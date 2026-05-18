import { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useQuery } from '@tanstack/react-query';
import { useNavigate, useRouterState, Outlet } from '@tanstack/react-router';
import { useStore } from './store/use-store';

declare const ps_data: { rest_url: string; nonce: string };

interface ProgressResponse {
	status: 'idle' | 'running' | 'done';
}

function useInitProgress() {
	const { setIndexingState, setDuplicateScanState } = useStore();

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
		if (indexProgress?.status === 'running') setIndexingState('running');
	}, [indexProgress, setIndexingState]);

	useEffect(() => {
		if (dupeProgress?.status === 'running') setDuplicateScanState('running');
	}, [dupeProgress, setDuplicateScanState]);
}

export default function App() {
	useInitProgress();

	const navigate = useNavigate();
	const pathname = useRouterState({ select: (s) => s.location.pathname });

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
			</div>

			<Outlet />
		</div>
	);
}
