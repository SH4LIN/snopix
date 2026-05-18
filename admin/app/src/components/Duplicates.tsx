import { __ } from '@wordpress/i18n';
import { useStore } from '../store/use-store';
import {
	useDuplicates,
	useStartDuplicateScan,
	useDuplicateScanProgress,
} from '../hooks/use-duplicates';
import DuplicateGroupCard from './DuplicateGroupCard';

export default function Duplicates() {
	const { indexingState, duplicateScanState } = useStore();
	const { data, isLoading } = useDuplicates();
	const { mutate: startScan, isPending } = useStartDuplicateScan();
	const progress = useDuplicateScanProgress();

	const isIndexing = indexingState === 'running';
	const isScanning =
		duplicateScanState === 'running' || duplicateScanState === 'done';
	const groups = data?.groups ?? [];
	const lastScanned = data?.last_scanned ?? '';

	if (isIndexing) {
		return (
			<div className="ps-card text-center py-8">
				<p className="text-ps-muted text-sm">
					{__(
						'Indexing is in progress. Duplicate scan is unavailable while indexing.',
						'pixel-scout'
					)}
				</p>
			</div>
		);
	}

	return (
		<div className="flex flex-col gap-4">
			<div className="ps-card">
				<div className="flex justify-between items-center">
					<div>
						<h2 className="text-[15px] font-semibold text-ps-text mb-1">
							{__('Duplicate Images', 'pixel-scout')}
						</h2>
						{lastScanned && (
							<p className="text-xs text-ps-muted">
								{__('Last scanned:', 'pixel-scout')}{' '}
								{lastScanned}
							</p>
						)}
					</div>

					<button
						className="ps-btn"
						onClick={() => startScan()}
						disabled={isPending || isScanning}
					>
						{isScanning
							? __('Scanning…', 'pixel-scout')
							: __('Scan Now', 'pixel-scout')}
					</button>
				</div>

				{isScanning && (
					<div className="mt-3">
						<div className="ps-progress">
							<div
								className={`h-full transition-all duration-500 rounded-[inherit] ${
									duplicateScanState === 'done'
										? 'bg-ps-success'
										: 'bg-ps-accent'
								}`}
								style={{
									width:
										duplicateScanState === 'done'
											? '100%'
											: progress && progress.total > 0
											? `${Math.round((progress.done / progress.total) * 100)}%`
											: '40%',
								}}
							/>
						</div>
						<div className="text-xs text-ps-muted mt-1.5">
							{duplicateScanState === 'done'
								? `✓ ${__('Scan complete', 'pixel-scout')}`
								: __('Scanning for duplicates…', 'pixel-scout')}
						</div>
					</div>
				)}
			</div>

			{!isScanning && !isLoading && groups.length === 0 && (
				<div className="ps-card text-center py-8">
					<p className="text-ps-text font-medium mb-1">
						{lastScanned
							? __('No duplicate images found.', 'pixel-scout')
							: __('No scan run yet.', 'pixel-scout')}
					</p>
					<p className="text-ps-muted text-sm">
						{__(
							'Click "Scan Now" to find duplicate images in your media library.',
							'pixel-scout'
						)}
					</p>
				</div>
			)}

			{!isScanning && groups.length > 0 && (
				<div className="flex flex-col gap-4">
					<p className="text-xs text-ps-muted">
						{groups.length === 1
							? __('1 duplicate group found.', 'pixel-scout')
							: `${groups.length} ${__('duplicate groups found.', 'pixel-scout')}`}
					</p>
					{groups.map((group) => (
						<DuplicateGroupCard key={group.images[0].id} group={group} />
					))}
				</div>
			)}
		</div>
	);
}
