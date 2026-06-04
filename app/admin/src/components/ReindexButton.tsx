import { __, sprintf } from '@wordpress/i18n';
import { useStore } from '../store/use-store';
import {
	useIndexingProgress,
	useResetProgress,
} from '../hooks/use-reindex';

/**
 * Live indexing progress card shown on the Dashboard.
 *
 * Renders only while a bulk job is running, stalled, or in its post-completion
 * grace window. The "Index remaining" button itself lives in the global app
 * header - this component just visualises the current job.
 *
 * @return {JSX.Element|null}
 */
export default function ReindexButton() {
	const { indexingState } = useStore();
	const { mutate: resetProgress, isPending: isResetting } =
		useResetProgress();
	const progress = useIndexingProgress();

	const isRunning = indexingState === 'running';
	const isDone = indexingState === 'done';
	const isStalled = indexingState === 'stalled';

	if (!isRunning && !isDone && !isStalled) {
		return null;
	}

	const pct =
		progress && progress.total > 0
			? (progress.done / progress.total) * 100
			: 0;

	return (
		<div
			data-tour="reindex-button"
			className="snopix-card snopix-card--pad mb-7"
		>
			<div className="flex items-center justify-between gap-4 mb-3">
				<div>
					<div className="text-[15px] font-semibold">
						{isDone
							? __('Indexing complete', 'snopix')
							: isStalled
								? __('Indexing stuck', 'snopix')
								: __('Indexing your images', 'snopix')}
					</div>
					<div className="text-[13px] text-snopix-muted mt-0.5">
						{progress &&
							sprintf(
								/* translators: 1: done count, 2: total count */
								__('%1$s of %2$s processed', 'snopix'),
								progress.done.toLocaleString(),
								progress.total.toLocaleString()
							)}
					</div>
				</div>
				{(isRunning || isStalled) && (
					<button
						className="snopix-btn snopix-btn--ghost snopix-btn--sm"
						onClick={() => resetProgress()}
						disabled={isResetting}
					>
						{isRunning
							? isResetting
								? __('Stopping…', 'snopix')
								: __('Stop', 'snopix')
							: isResetting
								? __('Resetting…', 'snopix')
								: __('Reset', 'snopix')}
					</button>
				)}
			</div>
			<div className="snopix-progress">
				<div
					className={`snopix-progress__fill ${
						isDone
							? 'bg-snopix-success'
							: isStalled
								? 'bg-snopix-danger'
								: 'bg-snopix-accent'
					}`}
					style={{ width: `${isDone ? 100 : pct}%` }}
				/>
			</div>
			<div className="mt-2 flex justify-between text-[11px] text-snopix-muted snopix-mono">
				<span>{Math.round(isDone ? 100 : pct)}%</span>
			</div>
			<p className="mt-2 text-[12px] leading-snug text-snopix-muted">
				{isDone
					? __(
							'All attachments are indexed. This card closes automatically.',
							'snopix'
						)
					: isStalled
						? __(
								'Indexing stopped before finishing - usually because WordPress cron didn’t run. Click Reset, then start indexing again.',
								'snopix'
							)
						: __(
								'Snopix is indexing your media in the background via WordPress cron. You can leave this page - it keeps running. Counts refresh every few seconds. Click Stop to cancel.',
								'snopix'
							)}
			</p>
		</div>
	);
}
