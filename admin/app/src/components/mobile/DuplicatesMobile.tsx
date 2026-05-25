import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	DuplicateGroup,
	useDeleteAttachment,
	useDuplicateScanProgress,
	useDuplicates,
	useResetDuplicateScan,
	useStartDuplicateScan,
} from '../../hooks/use-duplicates';
import { useStore } from '../../store/use-store';
import { formatBytes } from '../../lib/format';
import { IconCheck, IconRefresh, IconTrash } from '../icons';

function groupKey(group: DuplicateGroup): string {
	return String(group.images[0]?.id ?? '');
}

/**
 * Mobile duplicates screen.
 *
 * Lists each duplicate cluster as a stacked card with a 3-up thumbnail row.
 * Tapping a thumb selects it as the "keep" image; everything else in the
 * group gets a confirmation prompt before deletion. Matches the desktop
 * Duplicates flow without the table + bulk-action chrome.
 *
 * @return {JSX.Element}
 */
export default function DuplicatesMobile() {
	const { data, isLoading } = useDuplicates();
	const { indexingState, duplicateScanState } = useStore();
	const { mutate: startScan, isPending: isStarting } = useStartDuplicateScan();
	const { mutate: resetScan, isPending: isResetting } = useResetDuplicateScan();
	const progress = useDuplicateScanProgress();
	const { mutateAsync: deleteAttachment, isPending: isDeleting } =
		useDeleteAttachment();

	const [keepIds, setKeepIds] = useState<Record<string, number>>({});

	const groups = data?.groups ?? [];
	const totalWasteBytes = groups.reduce((sum, g) => sum + g.wasted_bytes, 0);
	const isScanning =
		duplicateScanState === 'running' || duplicateScanState === 'done';
	const isIndexing = indexingState === 'running';

	function getKeepId(group: DuplicateGroup): number {
		return keepIds[groupKey(group)] ?? group.images[0]?.id ?? 0;
	}

	function setKeepId(group: DuplicateGroup, id: number) {
		setKeepIds((prev) => ({ ...prev, [groupKey(group)]: id }));
	}

	async function deleteOthers(group: DuplicateGroup) {
		const keep = getKeepId(group);
		const targets = group.images.filter((img) => img.id !== keep);
		const message = sprintf(
			/* translators: %d: number of attachments that will be deleted */
			__(
				'Delete %d duplicate attachment(s)? The kept image stays in your library.',
				'snopix'
			),
			targets.length
		);
		if (!window.confirm(message)) {
			return;
		}

		for (const img of targets) {
			await deleteAttachment(img.id);
		}
	}

	if (isIndexing) {
		return (
			<div className="px-4 pt-5">
				<div className="bg-snopix-bg rounded-card p-5 border border-snopix-border text-center text-snopix-muted text-[13px]">
					{__(
						'Indexing is in progress. Duplicate scan is unavailable while indexing.',
						'snopix'
					)}
				</div>
			</div>
		);
	}

	return (
		<div>
			<div className="px-4 pt-5 pb-3">
				<div className="text-[11px] font-medium text-snopix-muted uppercase tracking-[0.05em] mb-1">
					{__('Cleanup', 'snopix')}
				</div>
				<div className="text-[24px] font-semibold tracking-[-0.015em] leading-tight">
					{sprintf(
						/* translators: %d: number of duplicate groups */
						__('%d duplicate groups', 'snopix'),
						groups.length
					)}
				</div>
				<div className="text-[13px] text-snopix-muted mt-1">
					{totalWasteBytes > 0
						? sprintf(
								/* translators: %s: bytes formatted as human-readable */
								__('%s recoverable. Tap a thumb to keep.', 'snopix'),
								formatBytes(totalWasteBytes)
							)
						: __('Tap a thumb to keep.', 'snopix')}
				</div>
			</div>

			<div className="px-4 pb-3 flex gap-2">
				<button
					type="button"
					className="snopix-btn snopix-btn--neutral snopix-btn--sm flex-1 justify-center"
					onClick={() => startScan()}
					disabled={isStarting || isScanning}
				>
					<IconRefresh size={14} />{' '}
					{isScanning ? __('Scanning…', 'snopix') : __('Rescan', 'snopix')}
				</button>
				{duplicateScanState === 'running' && (
					<button
						type="button"
						className="snopix-btn snopix-btn--ghost snopix-btn--sm flex-1 justify-center"
						onClick={() => resetScan()}
						disabled={isResetting}
					>
						{__('Reset', 'snopix')}
					</button>
				)}
			</div>

			{isScanning && progress && (
				<div className="px-4 pb-3">
					<div className="bg-snopix-bg rounded-card p-3.5 border border-snopix-border">
						<div className="text-[12px] text-snopix-muted font-mono mb-2">
							{progress.done.toLocaleString()} /{' '}
							{progress.total.toLocaleString()}
						</div>
						<div className="h-1 bg-snopix-border rounded-full overflow-hidden">
							<div
								className={`h-full transition-[width] duration-500 ${
									duplicateScanState === 'done'
										? 'bg-snopix-success'
										: 'bg-snopix-accent'
								}`}
								style={{
									width:
										duplicateScanState === 'done'
											? '100%'
											: progress.total > 0
												? `${Math.round((progress.done / progress.total) * 100)}%`
												: '40%',
								}}
							/>
						</div>
					</div>
				</div>
			)}

			{!isLoading && groups.length === 0 ? (
				<div className="px-4 pt-2">
					<div className="bg-snopix-bg rounded-card p-6 border border-snopix-border text-center text-snopix-muted text-[13px]">
						{__('No duplicate clusters found.', 'snopix')}
					</div>
				</div>
			) : (
				<div className="px-4 flex flex-col gap-3">
					{groups.map((g) => {
						const keepId = getKeepId(g);
						const shown = g.images.slice(0, 3);
						const remaining = g.images.length - shown.length;
						const cols = shown.length === 2 ? 'grid-cols-2' : 'grid-cols-3';

						return (
							<div
								key={groupKey(g)}
								className="bg-snopix-bg rounded-card p-3.5 border border-snopix-border"
							>
								<div className="flex items-center justify-between mb-3 gap-2">
									<span className="snopix-pill snopix-pill--accent">
										{(g.similarity * 100).toFixed(1)}% match
									</span>
									<span className="text-[11px] text-snopix-muted">
										{formatBytes(g.wasted_bytes)}
									</span>
								</div>

								<div className={`grid ${cols} gap-2`}>
									{shown.map((img) => {
										const kept = img.id === keepId;
										return (
											<button
												key={img.id}
												type="button"
												onClick={() => setKeepId(g, img.id)}
												className={`relative aspect-square rounded-input overflow-hidden bg-snopix-surface border p-0 cursor-pointer transition-all ${
													kept
														? 'border-snopix-accent border-2 shadow-[0_0_0_3px_rgba(0,113,227,0.15)]'
														: 'border-snopix-border opacity-60'
												}`}
												aria-pressed={kept}
											>
												<img
													src={img.thumbnail_url}
													alt={img.title || img.filename}
													className="w-full h-full object-cover"
													loading="lazy"
												/>
												{kept && (
													<div className="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-snopix-accent text-white grid place-items-center shadow">
														<IconCheck size={11} />
													</div>
												)}
											</button>
										);
									})}
								</div>

								{remaining > 0 && (
									<div className="text-[11px] text-snopix-muted mt-2 text-center">
										{sprintf(
											/* translators: %d: extra group members not shown */
											__('+%d more in group', 'snopix'),
											remaining
										)}
									</div>
								)}

								<div className="flex gap-2 mt-3">
									<button
										type="button"
										className="snopix-btn snopix-btn--danger snopix-btn--sm flex-1 justify-center"
										onClick={() => deleteOthers(g)}
										disabled={isDeleting}
									>
										<IconTrash size={13} />{' '}
										{sprintf(
											/* translators: %d: number of items to delete */
											__('Delete %d', 'snopix'),
											g.images.length - 1
										)}
									</button>
								</div>
							</div>
						);
					})}
				</div>
			)}
		</div>
	);
}
