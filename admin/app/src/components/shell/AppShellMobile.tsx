import { __ } from '@wordpress/i18n';
import { useNavigate, useRouterState, Outlet } from '@tanstack/react-router';
import { useStore } from '../../store/use-store';
import { useIndexStatus } from '../../hooks/use-index-status';
import { useReindex } from '../../hooks/use-reindex';
import {
	BrandMark,
	IconLayers,
	IconSettings,
	IconTool,
	IconUpload,
} from '../icons';
import NotificationBell from '../notifications/NotificationBell';

type TabId = 'dashboard' | 'duplicates' | 'tools' | 'settings';

const TABS: ReadonlyArray<{
	id: TabId;
	path: string;
	label: string;
	Icon: (props: { size?: number }) => JSX.Element;
}> = [
	{
		id: 'dashboard',
		path: '/dashboard',
		label: __('Dashboard', 'snopix'),
		Icon: ({ size = 20 }) => <IconUpload size={size} />,
	},
	{
		id: 'duplicates',
		path: '/duplicates',
		label: __('Duplicates', 'snopix'),
		Icon: ({ size = 20 }) => <IconLayers size={size} />,
	},
	{
		id: 'tools',
		path: '/tools',
		label: __('Tools', 'snopix'),
		Icon: ({ size = 20 }) => <IconTool size={size} />,
	},
	{
		id: 'settings',
		path: '/settings',
		label: __('Settings', 'snopix'),
		Icon: ({ size = 20 }) => <IconSettings size={size} />,
	},
];

function routeToTab(pathname: string): TabId {
	if (pathname.startsWith('/duplicates')) {
		return 'duplicates';
	}
	if (pathname.startsWith('/tools')) {
		return 'tools';
	}
	if (pathname.startsWith('/settings')) {
		return 'settings';
	}
	return 'dashboard';
}

/**
 * Mobile / tablet admin shell.
 *
 * Phone-style top app bar (brand + index button) plus a thumb-reachable
 * bottom tab bar. The route `<Outlet />` renders the active screen's
 * mobile variant (each screen is HOC-wrapped via `withResponsive`).
 *
 * @return {JSX.Element}
 */
export default function AppShellMobile() {
	const navigate = useNavigate();
	const pathname = useRouterState({ select: (s) => s.location.pathname });
	const activeTab = routeToTab(pathname);

	const { data: status } = useIndexStatus();
	const { indexingState } = useStore();
	const { mutate: startReindex, isPending } = useReindex();

	const pending = status?.pending ?? 0;
	const canReindex = pending > 0 && indexingState === 'idle';

	return (
		<div
			id="snopix-app"
			className="flex flex-col min-h-[calc(100vh-32px)] bg-snopix-surface"
		>
			<div className="snopix-mobile-topbar bg-snopix-bg border-b border-snopix-border">
				<div className="px-4 py-2.5 flex items-center justify-between gap-3">
					<div className="flex items-center gap-2.5 min-w-0">
						<BrandMark size={26} />
						<div className="min-w-0">
							<div className="text-[16px] font-semibold tracking-[-0.01em] leading-none">
								{__('Snopix', 'snopix')}
							</div>
							<div className="text-[11px] text-snopix-muted mt-1 overflow-hidden text-ellipsis whitespace-nowrap">
								{__('Reverse-image search', 'snopix')}
							</div>
						</div>
					</div>
					<div className="flex items-center gap-2">
						<NotificationBell variant="mobile" />
						<button
							type="button"
							onClick={() => startReindex()}
							disabled={!canReindex || isPending}
							aria-label={__('Index remaining', 'snopix')}
							title={
								!canReindex
									? __('No pending attachments.', 'snopix')
									: __('Index remaining', 'snopix')
							}
							className="w-9 h-9 rounded-full bg-snopix-surface text-snopix-text grid place-items-center disabled:opacity-40 disabled:cursor-not-allowed"
						>
							<IconUpload size={16} />
						</button>
					</div>
				</div>
			</div>

			<main className="flex-1 pb-24">
				<Outlet />
			</main>

			<nav
				className="fixed left-0 right-0 bottom-0 z-30 bg-snopix-bg/90 backdrop-blur border-t border-snopix-border flex justify-around px-1 pt-2 pb-[calc(env(safe-area-inset-bottom,0px)+12px)]"
				aria-label={__('Snopix sections', 'snopix')}
			>
				{TABS.map((t) => {
					const active = activeTab === t.id;
					return (
						<button
							key={t.id}
							type="button"
							onClick={() => navigate({ to: t.path })}
							className={`flex flex-col items-center gap-1 px-3 py-1.5 min-w-[64px] transition-colors ${
								active ? 'text-snopix-accent' : 'text-snopix-muted'
							}`}
							aria-current={active ? 'page' : undefined}
						>
							<t.Icon size={20} />
							<span className="text-[10px] font-medium tracking-wide">
								{t.label}
							</span>
						</button>
					);
				})}
			</nav>
		</div>
	);
}
