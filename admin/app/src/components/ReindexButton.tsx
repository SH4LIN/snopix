import { __, sprintf } from '@wordpress/i18n';
import { useStore } from '../store/use-store';
import {
	useReindex,
	useIndexingProgress,
	useResetProgress,
} from '../hooks/use-reindex';
import { ConflictError } from '../lib/api';

interface Status {
	total: number;
	indexed: number;
	pending: number;
}
interface Props {
	status?: Status;
}

/**
 * "Index Remaining" CTA + live progress bar shown on the Dashboard.
 *
 * The button is enabled only while indexing is idle and the status payload
 * reports at least one pending attachment. Once the user clicks it,
 * {@link useReindex} kicks off the bulk job and the row swaps to a progress bar
 * driven by {@link useIndexingProgress}. Stalled and done states are surfaced
 * inline beneath the bar.
 *
 * @param {Props}    props        Component props.
 * @param {Status=}  props.status Latest status payload from `/wp-json/snopix/v1/status`.
 *
 * @return {JSX.Element}
 */
export default function ReindexButton({ status }: Props) {
	const { indexingState } = useStore();
	const { mutate: startReindex, isPending, error: startError } = useReindex();
	const { mutate: resetProgress, isPending: isResetting } =
		useResetProgress();
	const progress = useIndexingProgress();

	const isIdle = indexingState === 'idle';
	const isRunning = indexingState === 'running';
	const isDone = indexingState === 'done';
	const isStalled = indexingState === 'stalled';

	const pct =
		progress && progress.total > 0
			? (progress.done / progress.total) * 100
			: 0;

	const conflictMessage =
		startError instanceof ConflictError ? startError.message : null;

	return (
		<div className="snopix-card mb-4">
			<div className="flex justify-between items-center mb-3">
				<span className="text-sm">
					{status
						? sprintf(
								/* translators: 1: indexed image count, 2: total image count */
								__('%1$s of %2$s images indexed', 'snopix'),
								status.indexed.toLocaleString(),
								status.total.toLocaleString()
							)
						: '—'}
				</span>

				<div className="flex gap-2">
					{isIdle && (
						<button
							className="snopix-btn"
							onClick={() => startReindex()}
							disabled={isPending || !status?.pending}
						>
							{__('Index Remaining', 'snopix')}{' '}
							{status?.pending ?? ''} &rarr;
						</button>
					)}

					{(isRunning || isStalled) && (
						<button
							className="snopix-btn snopix-btn--neutral"
							onClick={() => resetProgress()}
							disabled={isResetting}
							title={__(
								'Cancel the running job and clear its progress so a new one can start.',
								'snopix'
							)}
						>
							{isResetting
								? __('Resetting…', 'snopix')
								: __('Reset', 'snopix')}
						</button>
					)}
				</div>
			</div>

			{(isRunning || isDone || isStalled) && (
				<div className="snopix-progress">
					<div
						className={`h-full transition-all duration-[400ms] rounded-[inherit] ${
							isDone
								? 'bg-snopix-success'
								: isStalled
									? 'bg-snopix-danger'
									: 'bg-snopix-accent'
						}`}
						style={{ width: `${isDone ? 100 : pct}%` }}
					/>
				</div>
			)}

			{isRunning && progress && (
				<div className="text-xs text-snopix-muted mt-1.5">
					{sprintf(
						/* translators: 1: completed batch count, 2: total batch count */
						__('Indexing… %1$d of %2$d', 'snopix'),
						progress.done,
						progress.total
					)}
				</div>
			)}

			{isDone && (
				<div className="text-xs text-snopix-success mt-1.5">
					✓ {__('Indexing complete', 'snopix')}
				</div>
			)}

			{isStalled && (
				<div className="text-xs text-snopix-danger mt-1.5">
					✗{' '}
					{__(
						'Indexing stalled — click Reset to clear the queue and start a new run.',
						'snopix'
					)}
				</div>
			)}

			{conflictMessage && (
				<div className="text-xs text-snopix-danger mt-1.5">
					{conflictMessage}
				</div>
			)}
		</div>
	);
}
