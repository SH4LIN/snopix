import { __ } from '@wordpress/i18n'
import { useStore } from '../store/use-store'
import { useReindex, useIndexingProgress } from '../hooks/use-reindex'

interface Status {
	total: number
	indexed: number
	pending: number
}
interface Props {
	status?: Status
}

export default function ReindexButton({ status }: Props) {
	const { indexingState, setIndexingState } = useStore()
	const { mutate: startReindex, isPending } = useReindex()
	const progress = useIndexingProgress()

	const isIdle = indexingState === 'idle'
	const isRunning = indexingState === 'running'
	const isDone = indexingState === 'done'
	const isStalled = indexingState === 'stalled'

	const pct = progress && progress.total > 0 ? (progress.done / progress.total) * 100 : 0

	return (
		<div className="ps-card mb-4">
			<div className="flex justify-between items-center mb-3">
				<span className="text-sm">
					{status
						? __( `${status.indexed.toLocaleString()} of ${status.total.toLocaleString()} images indexed`, 'pixel-scout' )
						: '—'}
				</span>

				{isIdle && (
					<button
						className="ps-btn"
						onClick={() => startReindex()}
						disabled={isPending || !status?.pending}
					>
						{/* translators: %d is the number of images to index */}
						{__( 'Index Remaining', 'pixel-scout' )} {status?.pending ?? ''} &rarr;
					</button>
				)}

				{isRunning && (
					<button className="ps-btn bg-ps-muted" onClick={() => setIndexingState('idle')}>
						{__( 'Cancel', 'pixel-scout' )}
					</button>
				)}
			</div>

			{(isRunning || isDone) && (
				<div className="ps-progress">
					<div
						className={`h-full transition-all duration-[400ms] rounded-[inherit] ${
							isDone ? 'bg-ps-success' : 'bg-ps-accent'
						}`}
						style={{ width: `${isDone ? 100 : pct}%` }}
					/>
				</div>
			)}

			{isRunning && progress && (
				<div className="text-xs text-ps-muted mt-1.5">
					{__( 'Indexing…', 'pixel-scout' )} {progress.done} of {progress.total}
				</div>
			)}

			{isDone && (
				<div className="text-xs text-ps-success mt-1.5">
					✓ {__( 'Indexing complete', 'pixel-scout' )}
				</div>
			)}

			{isStalled && (
				<div className="text-xs text-ps-danger mt-1.5">
					✗ {__( 'Indexing stalled — check server cron', 'pixel-scout' )}
				</div>
			)}
		</div>
	)
}
