import { __ } from '@wordpress/i18n';
import { useStore } from '../../store/use-store';
import { useIndexingProgress } from '../../hooks/use-reindex';
import { IconRefresh } from '../icons';

interface Props {
	/**
	 * Tailwind padding wrapper applied around the card. Lets callers tune
	 * the horizontal/vertical rhythm against the surrounding screen (e.g.
	 * `px-[18px] pt-4` vs `px-[18px] pb-3`). Defaults to no wrapper padding
	 * — caller can position the card directly.
	 */
	wrapperClassName?: string;
}

/**
 * Mobile indexing-progress card.
 *
 * Reads the indexing state machine from the global store and renders a
 * "Indexing in progress" / "Indexing stalled" tile with an animated icon,
 * `done / total` counter, and a progress bar. Returns null when no job is
 * active so callers can drop the component in unconditionally without
 * producing layout gaps.
 *
 * Shared between `DashboardMobile` and `ToolsMobile` so any future change
 * to the visual treatment lives in one place.
 *
 * @param {Props}  props                  Component props.
 * @param {string} props.wrapperClassName Tailwind padding wrapper.
 *
 * @return {JSX.Element|null}
 */
export default function IndexingProgressCard({
	wrapperClassName = '',
}: Props): JSX.Element | null {
	const { indexingState } = useStore();
	const progress = useIndexingProgress();

	const isRunning = indexingState === 'running' || indexingState === 'stalled';
	if (!isRunning || !progress) {
		return null;
	}

	const progressPct =
		progress.total > 0
			? Math.min(100, Math.round((progress.done / progress.total) * 100))
			: 0;

	return (
		<div className={wrapperClassName}>
			<div className="bg-snopix-bg rounded-[14px] p-3.5 border border-snopix-border">
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
	);
}
