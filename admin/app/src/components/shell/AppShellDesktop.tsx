import { __ } from '@wordpress/i18n';
import { useNavigate, useRouterState, Outlet } from '@tanstack/react-router';
import { useStore } from '../../store/use-store';
import { useIndexStatus } from '../../hooks/use-index-status';
import { useReindex } from '../../hooks/use-reindex';
import { BrandMark, IconUpload } from '../icons';

const TABS = [
	{ id: 'dashboard', path: '/dashboard', label: __('Dashboard', 'snopix') },
	{ id: 'duplicates', path: '/duplicates', label: __('Duplicates', 'snopix') },
	{ id: 'tools', path: '/tools', label: __('Tools', 'snopix') },
	{ id: 'settings', path: '/settings', label: __('Settings', 'snopix') },
] as const;

/**
 * Desktop admin shell.
 *
 * Plugin brand header (logo, title, "Index remaining" CTA), the four-tab
 * strip, and the route outlet. Pairs with `AppShellMobile` via
 * `withResponsive` so the same router tree drives both viewports.
 *
 * @return {JSX.Element}
 */
export default function AppShellDesktop() {
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
										? __('No pending attachments.', 'snopix')
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
