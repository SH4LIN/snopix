import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useStore } from '../store/use-store';
import {
	DuplicateGroup,
	useDuplicates,
	useStartDuplicateScan,
	useDuplicateScanProgress,
	useDeleteAttachment,
} from '../hooks/use-duplicates';
import DuplicateGroupCard from './DuplicateGroupCard';

function groupKey(group: DuplicateGroup): string {
	return String(group.images[0].id);
}

export default function Duplicates() {
	const { indexingState, duplicateScanState } = useStore();
	const { data, isLoading } = useDuplicates();
	const { mutate: startScan, isPending } = useStartDuplicateScan();
	const progress = useDuplicateScanProgress();
	const { mutateAsync: deleteAttachment, isPending: isBulkDeleting } =
		useDeleteAttachment();

	const [keepIds, setKeepIds] = useState<Record<string, number>>({});
	const [selectedGroups, setSelectedGroups] = useState<Set<string>>(
		new Set()
	);

	const isIndexing = indexingState === 'running';
	const isScanning =
		duplicateScanState === 'running' || duplicateScanState === 'done';
	const groups = data?.groups ?? [];
	const lastScanned = data?.last_scanned ?? '';

	function getKeepId(group: DuplicateGroup): number {
		return keepIds[groupKey(group)] ?? group.images[0]?.id ?? 0;
	}

	function setKeepId(group: DuplicateGroup, id: number) {
		setKeepIds((prev) => ({ ...prev, [groupKey(group)]: id }));
	}

	function toggleSelect(group: DuplicateGroup) {
		const key = groupKey(group);
		setSelectedGroups((prev) => {
			const next = new Set(prev);
			if (next.has(key)) next.delete(key);
			else next.add(key);
			return next;
		});
	}

	const allSelected =
		groups.length > 0 && selectedGroups.size === groups.length;

	function toggleSelectAll() {
		if (allSelected) {
			setSelectedGroups(new Set());
		} else {
			setSelectedGroups(new Set(groups.map(groupKey)));
		}
	}

	async function handleBulkDelete() {
		try {
			for (const group of groups) {
				if (!selectedGroups.has(groupKey(group))) continue;
				const keep = getKeepId(group);
				for (const img of group.images) {
					if (img.id !== keep) await deleteAttachment(img.id);
				}
			}
			setSelectedGroups(new Set());
		} catch {
			// hook invalidates query on partial success
		}
	}

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
					<div className="flex items-center justify-between">
						<label className="flex items-center gap-2 text-xs text-ps-muted cursor-pointer select-none">
							<input
								type="checkbox"
								checked={allSelected}
								onChange={toggleSelectAll}
								className="w-4 h-4 cursor-pointer accent-[var(--ps-accent,#2271b1)]"
							/>
							{groups.length === 1
								? __('1 duplicate group found.', 'pixel-scout')
								: `${groups.length} ${__('duplicate groups found.', 'pixel-scout')}`}
						</label>

						{selectedGroups.size > 0 && (
							<button
								className="ps-btn bg-ps-danger border-ps-danger text-xs"
								onClick={handleBulkDelete}
								disabled={isBulkDeleting}
							>
								{isBulkDeleting
									? __('Deleting…', 'pixel-scout')
									: sprintf(
											/* translators: %d: number of selected groups */
											__('Delete %d selected', 'pixel-scout'),
											selectedGroups.size
										)}
							</button>
						)}
					</div>

					{groups.map((group) => (
						<DuplicateGroupCard
							key={group.images[0].id}
							group={group}
							keepId={getKeepId(group)}
							onKeepChange={(id) => setKeepId(group, id)}
							selected={selectedGroups.has(groupKey(group))}
							onToggleSelect={() => toggleSelect(group)}
						/>
					))}
				</div>
			)}
		</div>
	);
}
