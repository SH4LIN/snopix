import { __, sprintf } from '@wordpress/i18n';
import { useToolActions } from '../../hooks/use-tool-actions';
import { useStore } from '../../store/use-store';
import {
	IconBroom,
	IconChevron,
	IconRefresh,
	IconTrash,
} from '../icons';
import IndexingProgressCard from './IndexingProgressCard';
import MobileHero from './MobileHero';
import Toast from '../Toast';

type ActionRow = {
	key: string;
	Icon: typeof IconRefresh;
	title: string;
	sub: string;
	danger?: boolean;
	disabled?: boolean;
	onClick: () => void;
};

/**
 * Mobile tools screen.
 *
 * Surfaces the same four maintenance mutations as the desktop Tools tab
 * (reindex everything, delete orphans, flush caches, clear the index) as
 * iOS-style grouped rows. The in-flight indexing job, when running, gets
 * its own pill card at the top.
 *
 * @return {JSX.Element}
 */
export default function ToolsMobile() {
	const { indexingState } = useStore();
	const { run, pending, orphanCount, toast, dismissToast } = useToolActions();
	const isRunning = indexingState === 'running' || indexingState === 'stalled';

	const actions: ActionRow[] = [
		{
			key: 'reindex',
			Icon: IconRefresh,
			title: __('Reindex everything', 'snopix'),
			sub: __('Wipe and rebuild every fingerprint.', 'snopix'),
			disabled: isRunning || pending.reindex,
			onClick: () => {
				if (
					window.confirm(
						__('Reindex every attachment? This may take a while.', 'snopix')
					)
				) {
					void run('reindex');
				}
			},
		},
		{
			key: 'orphans',
			Icon: IconBroom,
			title: __('Delete orphan rows', 'snopix'),
			sub:
				orphanCount > 0
					? sprintf(
							/* translators: %d: orphan row count */
							__('%d found', 'snopix'),
							orphanCount
						)
					: __('Nothing to clean up.', 'snopix'),
			disabled: orphanCount === 0 || pending.orphans,
			onClick: () => void run('orphans'),
		},
		{
			key: 'cache',
			Icon: IconBroom,
			title: __('Flush plugin caches', 'snopix'),
			sub: __('Clears progress transients + query cache.', 'snopix'),
			disabled: pending.cache,
			onClick: () => void run('cache'),
		},
		{
			key: 'clear',
			Icon: IconTrash,
			title: __('Clear the index', 'snopix'),
			sub: __('Destructive — drops every fingerprint row.', 'snopix'),
			danger: true,
			disabled: pending.clear,
			onClick: () => {
				if (
					window.confirm(
						__(
							'Drop every fingerprint row? You will need to reindex before search works again.',
							'snopix'
						)
					)
				) {
					void run('clear');
				}
			},
		},
	];

	return (
		<div>
			<MobileHero
				label={__('Maintenance', 'snopix')}
				title={__('Tools', 'snopix')}
			/>

			<IndexingProgressCard wrapperClassName="px-[18px] pb-3" />

			<div className="px-[18px]">
				<div className="text-[11px] font-medium text-snopix-muted uppercase tracking-[0.05em] px-1 pb-2">
					{__('Actions', 'snopix')}
				</div>
				<div className="bg-snopix-bg rounded-card overflow-hidden border border-snopix-border">
					{actions.map((a, idx) => (
						<button
							key={a.key}
							type="button"
							onClick={a.onClick}
							disabled={a.disabled}
							className={`w-full px-3.5 py-3.5 flex items-center gap-3.5 text-left bg-transparent border-0 ${
								idx === actions.length - 1
									? ''
									: 'border-b border-snopix-border'
							} disabled:opacity-40 disabled:cursor-not-allowed`}
						>
							<div
								className={`w-8 h-8 rounded-input shrink-0 grid place-items-center ${
									a.danger
										? 'bg-snopix-danger/10 text-snopix-danger'
										: 'bg-snopix-surface text-snopix-muted'
								}`}
							>
								<a.Icon size={16} />
							</div>
							<div className="flex-1 min-w-0">
								<div
									className={`text-[14px] font-medium ${
										a.danger ? 'text-snopix-danger' : 'text-snopix-text'
									}`}
								>
									{a.title}
								</div>
								<div className="text-[11px] text-snopix-muted mt-0.5">
									{a.sub}
								</div>
							</div>
							<IconChevron size={14} className="text-snopix-muted" />
						</button>
					))}
				</div>
			</div>
			{toast && <Toast message={toast} onDismiss={dismissToast} />}
		</div>
	);
}
