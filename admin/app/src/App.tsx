import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import Dashboard from './components/Dashboard';
import Tools from './components/Tools';
import Duplicates from './components/Duplicates';
import { useStore } from './store/use-store';

declare const ps_data: { rest_url: string; nonce: string };

type Tab = 'dashboard' | 'duplicates' | 'tools';

export default function App() {
	const [tab, setTab] = useState<Tab>('dashboard');
	const { setIndexingState, setDuplicateScanState } = useStore();

	useEffect(() => {
		const headers = { 'X-WP-Nonce': ps_data.nonce };
		fetch(`${ps_data.rest_url}progress`, { headers })
			.then((r) => r.json())
			.then((p) => { if (p?.status === 'running') setIndexingState('running'); })
			.catch(() => {});
		fetch(`${ps_data.rest_url}duplicates/progress`, { headers })
			.then((r) => r.json())
			.then((p) => { if (p?.status === 'running') setDuplicateScanState('running'); })
			.catch(() => {});
	}, [setIndexingState, setDuplicateScanState]);

	const tabClass = (key: Tab) =>
		`px-4 py-2 text-[14px] font-medium border-b-2 cursor-pointer transition-colors ${
			tab === key
				? 'text-ps-accent border-ps-accent'
				: 'text-ps-muted border-transparent hover:text-ps-text'
		}`;

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
					className={tabClass('dashboard')}
					onClick={() => setTab('dashboard')}
				>
					{__('Dashboard', 'pixel-scout')}
				</button>
				<button
					className={tabClass('duplicates')}
					onClick={() => setTab('duplicates')}
				>
					{__('Duplicates', 'pixel-scout')}
				</button>
				<button
					className={tabClass('tools')}
					onClick={() => setTab('tools')}
				>
					{__('Tools', 'pixel-scout')}
				</button>
			</div>

			{tab === 'dashboard' && <Dashboard />}
			{tab === 'duplicates' && <Duplicates />}
			{tab === 'tools' && <Tools />}
		</div>
	);
}
