import { __, sprintf } from '@wordpress/i18n';
import { useIndexingProgress } from '../../hooks/use-reindex';
import {
	useClearCache,
	useClearIndex,
	useDeleteOrphans,
	useOrphanCount,
	useReindexAll,
} from '../../hooks/use-tools';
import { useStore } from '../../store/use-store';
import {
	IconBroom,
	IconChevron,
	IconRefresh,
	IconTrash,
} from '../icons';

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
export default function MobileTools() {
	const { indexingState } = useStore();
	const progress = useIndexingProgress();
	const { mutate: reindexAll, isPending: isReindexing } = useReindexAll();
	const { mutate: deleteOrphans, isPending: isDeletingOrphans } =
		useDeleteOrphans();
	const { mutate: clearCache, isPending: isClearingCache } = useClearCache();
	const { mutate: clearIndex, isPending: isClearingIndex } = useClearIndex();
	const { data: orphans } = useOrphanCount();

	const isRunning = indexingState === 'running' || indexingState === 'stalled';
	const progressPct =
		progress && progress.total > 0
			? Math.min(100, Math.round((progress.done / progress.total) * 100))
			: 0;
	const orphanCount = orphans?.orphans ?? 0;

	const actions: ActionRow[] = [
		{
			key: 'reindex',
			Icon: IconRefresh,
			title: __('Reindex everything', 'snopix'),
			sub: __('Wipe and rebuild every fingerprint.', 'snopix'),
			disabled: isRunning || isReindexing,
			onClick: () => {
				if (
					window.confirm(
						__('Reindex every attachment? This may take a while.', 'snopix')
					)
				) {
					reindexAll();
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
			disabled: orphanCount === 0 || isDeletingOrphans,
			onClick: () => deleteOrphans(),
		},
		{
			key: 'cache',
			Icon: IconBroom,
			title: __('Flush plugin caches', 'snopix'),
			sub: __('Clears progress transients + query cache.', 'snopix'),
			disabled: isClearingCache,
			onClick: () => clearCache(),
		},
		{
			key: 'clear',
			Icon: IconTrash,
			title: __('Clear the index', 'snopix'),
			sub: __('Destructive — drops every fingerprint row.', 'snopix'),
			danger: true,
			disabled: isClearingIndex,
			onClick: () => {
				if (
					window.confirm(
						__(
							'Drop every fingerprint row? You will need to reindex before search works again.',
							'snopix'
						)
					)
				) {
					clearIndex();
				}
			},
		},
	];

	return (
		<div>
			<div className="px-4 pt-5 pb-3">
				<div className="text-[11px] font-medium text-snopix-muted uppercase tracking-[0.05em] mb-1">
					{__('Maintenance', 'snopix')}
				</div>
				<div className="text-[24px] font-semibold tracking-[-0.015em] leading-tight">
					{__('Tools', 'snopix')}
				</div>
			</div>

			{isRunning && progress && (
				<div className="px-4 pb-3">
					<div className="bg-snopix-bg rounded-card p-3.5 border border-snopix-border">
						<div className="flex items-center gap-3 mb-3">
							<div className="w-9 h-9 rounded-input bg-snopix-accent-soft text-snopix-accent grid place-items-center">
								<IconRefresh size={18} className="animate-snopix-spin" />
							</div>
							<div className="flex-1 min-w-0">
								<div className="text-[14px] font-semibold">
									{indexingState === 'stalled'
										? __('Indexing stalled', 'snopix')
										: __('Indexing in progress', 'snopix')}
								</div>
								<div className="text-[11px] text-snopix-muted font-mono mt-0.5">
									{progress.done.toLocaleString()} /{' '}
									{progress.total.toLocaleString()}
								</div>
							</div>
						</div>
						<div className="h-1 bg-snopix-border rounded-full overflow-hidden">
							<div
								className="h-full bg-snopix-accent transition-[width] duration-500"
								style={{ width: `${progressPct}%` }}
							/>
						</div>
					</div>
				</div>
			)}

			<div className="px-4">
				<div className="text-[11px] font-medium text-snopix-muted uppercase tracking-[0.05em] px-1 pb-1.5">
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
		</div>
	);
}
