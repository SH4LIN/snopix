import { __ } from '@wordpress/i18n';
import { useStore } from '../store/use-store';
import {
	useReindex,
	useIndexingProgress,
	useResetProgress,
	ConflictError,
} from '../hooks/use-reindex';

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
 * @param {Status=}  props.status Latest status payload from `/wp-json/ps/v1/status`.
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
		<div className="ps-card mb-4">
			<div className="flex justify-between items-center mb-3">
				<span className="text-sm">
					{status
						? __(
								`${status.indexed.toLocaleString()} of ${status.total.toLocaleString()} images indexed`,
								'pixel-scout'
							)
						: '—'}
				</span>

				<div className="flex gap-2">
					{isIdle && (
						<button
							className="ps-btn"
							onClick={() => startReindex()}
							disabled={isPending || !status?.pending}
						>
							{__('Index Remaining', 'pixel-scout')}{' '}
							{status?.pending ?? ''} &rarr;
						</button>
					)}

					{(isRunning || isStalled) && (
						<button
							className="ps-btn ps-btn--neutral"
							onClick={() => resetProgress()}
							disabled={isResetting}
							title={__(
								'Cancel the running job and clear its progress so a new one can start.',
								'pixel-scout'
							)}
						>
							{isResetting
								? __('Resetting…', 'pixel-scout')
								: __('Reset', 'pixel-scout')}
						</button>
					)}
				</div>
			</div>

			{(isRunning || isDone || isStalled) && (
				<div className="ps-progress">
					<div
						className={`h-full transition-all duration-[400ms] rounded-[inherit] ${
							isDone
								? 'bg-ps-success'
								: isStalled
									? 'bg-ps-danger'
									: 'bg-ps-accent'
						}`}
						style={{ width: `${isDone ? 100 : pct}%` }}
					/>
				</div>
			)}

			{isRunning && progress && (
				<div className="text-xs text-ps-muted mt-1.5">
					{__('Indexing…', 'pixel-scout')} {progress.done} of{' '}
					{progress.total}
				</div>
			)}

			{isDone && (
				<div className="text-xs text-ps-success mt-1.5">
					✓ {__('Indexing complete', 'pixel-scout')}
				</div>
			)}

			{isStalled && (
				<div className="text-xs text-ps-danger mt-1.5">
					✗{' '}
					{__(
						'Indexing stalled — click Reset to clear the queue and start a new run.',
						'pixel-scout'
					)}
				</div>
			)}

			{conflictMessage && (
				<div className="text-xs text-ps-danger mt-1.5">
					{conflictMessage}
				</div>
			)}
		</div>
	);
}
