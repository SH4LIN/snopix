import { __, sprintf } from '@wordpress/i18n';
import { useIndexStatus } from '../../hooks/use-index-status';
import { useIndexingProgress, useReindex } from '../../hooks/use-reindex';
import { useStore } from '../../store/use-store';
import { IconRefresh, IconUpload } from '../icons';

/**
 * Mobile dashboard screen.
 *
 * Single-column reflow of the desktop Dashboard: a hero "X indexed" stat at
 * the top, a 2×2 grid of secondary tiles, the same in-flight progress card
 * the desktop view shows, and a call-to-action that funnels users to the
 * reverse-image search (kept on the desktop view — on mobile we only nudge
 * users toward it rather than reproduce the full SearchPreview component).
 *
 * @return {JSX.Element}
 */
export default function MobileDashboard() {
	const { data: status } = useIndexStatus();
	const { indexingState } = useStore();
	const progress = useIndexingProgress();
	const { mutate: startReindex, isPending } = useReindex();

	const total = status?.total ?? 0;
	const indexed = status?.indexed ?? 0;
	const pending = status?.pending ?? 0;
	const failed = status?.failed ?? 0;

	const isRunning = indexingState === 'running' || indexingState === 'stalled';
	const progressPct =
		progress && progress.total > 0
			? Math.min(100, Math.round((progress.done / progress.total) * 100))
			: 0;

	const tiles = [
		{ label: __('Indexed', 'snopix'), value: indexed, accent: 'text-snopix-text' },
		{ label: __('Pending', 'snopix'), value: pending, accent: 'text-snopix-warning' },
		{ label: __('Failed', 'snopix'), value: failed, accent: 'text-snopix-danger' },
		{ label: __('Total', 'snopix'), value: total, accent: 'text-snopix-text' },
	];

	return (
		<div>
			<div className="px-4 pt-5 pb-3">
				<div className="text-[11px] font-medium text-snopix-muted uppercase tracking-[0.05em] mb-1">
					{__('Library', 'snopix')}
				</div>
				<div className="text-[26px] font-semibold tracking-[-0.015em] leading-tight">
					{sprintf(
						/* translators: %d: number of indexed images */
						__('%d indexed', 'snopix'),
						indexed
					)}
				</div>
				<div className="text-[13px] text-snopix-muted mt-1">
					{total > 0
						? sprintf(
								/* translators: %d: total images in library */
								__('%d images in your library', 'snopix'),
								total
							)
						: __('Reverse-image search across your indexed media.', 'snopix')}
				</div>
			</div>

			<div className="grid grid-cols-2 gap-2.5 px-4">
				{tiles.map((tile) => (
					<div
						key={tile.label}
						className="bg-snopix-bg rounded-card p-3.5 border border-snopix-border"
					>
						<div className="text-[10px] font-medium text-snopix-muted uppercase tracking-wider">
							{tile.label}
						</div>
						<div
							className={`text-[22px] font-semibold tracking-[-0.015em] mt-1 ${tile.accent}`}
						>
							{tile.value.toLocaleString()}
						</div>
					</div>
				))}
			</div>

			{isRunning && progress && (
				<div className="px-4 pt-5">
					<div className="bg-snopix-bg rounded-card p-3.5 border border-snopix-border">
						<div className="flex items-center gap-3 mb-3">
							<div className="w-9 h-9 rounded-input bg-snopix-accent-soft text-snopix-accent grid place-items-center">
								<IconRefresh size={18} className="animate-snopix-spin" />
							</div>
							<div className="min-w-0 flex-1">
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

			<div className="px-4 pt-5">
				<div className="bg-snopix-bg rounded-card px-5 py-6 text-center border-[1.5px] border-dashed border-snopix-border-strong">
					<div className="w-12 h-12 mx-auto mb-3 rounded-full bg-snopix-accent-soft text-snopix-accent grid place-items-center">
						<IconUpload size={20} />
					</div>
					<div className="text-[15px] font-medium mb-1">
						{pending > 0
							? __('Index remaining attachments', 'snopix')
							: __('Library fully indexed', 'snopix')}
					</div>
					<div className="text-[12px] text-snopix-muted mb-3.5">
						{pending > 0
							? sprintf(
									/* translators: %d: pending attachment count */
									__('%d attachments waiting', 'snopix'),
									pending
								)
							: __('Nothing pending right now.', 'snopix')}
					</div>
					<button
						type="button"
						className="snopix-btn snopix-btn--sm w-full justify-center"
						onClick={() => startReindex()}
						disabled={!pending || isRunning || isPending}
					>
						<IconUpload size={14} /> {__('Index now', 'snopix')}
					</button>
				</div>
			</div>
		</div>
	);
}
