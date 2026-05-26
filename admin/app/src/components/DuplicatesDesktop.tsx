import { useMemo, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	groupKey,
	useDuplicatesBoard,
} from '../hooks/use-duplicates-board';
import { type DuplicateGroup } from '../hooks/use-duplicates';
import { ConflictError } from '../lib/api';
import { formatBytes } from '../lib/format';
import DuplicateGroupCard from './DuplicateGroupCard';
import ConfirmModal from './ConfirmModal';
import Toast from './Toast';
import { IconCheck, IconRefresh, IconTrash, IconWarn } from './icons';

type ConfirmTarget = { kind: 'group'; group: DuplicateGroup } | { kind: 'all' };

/**
 * Duplicates tab — scans for and resolves visually identical attachments.
 *
 * Summary bar with group/dup/recoverable counts, a similarity-threshold slider
 * for client-side filtering, then one card per group with a keep-radio tile
 * carousel. Bulk-delete and per-group delete both route through a single
 * confirm modal and emit a toast on completion.
 *
 * View-model state (groups, scan flags, keep selection, raw delete mutation)
 * is shared with `DuplicatesMobile` via {@link useDuplicatesBoard}; this
 * component owns the desktop-only chrome (threshold filter, confirm modal,
 * toast).
 *
 * @return {JSX.Element}
 */
export default function Duplicates() {
	const {
		groups,
		lastScanned,
		isLoading,
		isScanning,
		isIndexing,
		progress,
		duplicateScanState,
		startScan,
		isStarting,
		resetScan,
		isResetting,
		startError,
		getKeepId,
		setKeepId,
		deleteAttachmentAsync,
		isDeleting: isBulkDeleting,
	} = useDuplicatesBoard();

	const conflictMessage =
		startError instanceof ConflictError ? startError.message : null;

	const [thresholdPercent, setThresholdPercent] = useState(95);
	const [confirm, setConfirm] = useState<ConfirmTarget | null>(null);
	const [toast, setToast] = useState<string | null>(null);

	const thresholdRatio = thresholdPercent / 100;
	const visibleGroups = useMemo(
		() => groups.filter((g) => g.similarity >= thresholdRatio),
		[groups, thresholdRatio]
	);

	const totalDupCount = visibleGroups.reduce(
		(n, g) => n + Math.max(0, g.images.length - 1),
		0
	);
	const totalWasteBytes = visibleGroups.reduce(
		(n, g) => n + g.wasted_bytes,
		0
	);

	async function performDelete(target: ConfirmTarget) {
		const targets =
			target.kind === 'group' ? [target.group] : visibleGroups;

		const ids: number[] = [];
		for (const g of targets) {
			const keep = getKeepId(g);
			for (const img of g.images) {
				if (img.id !== keep) {
					ids.push(img.id);
				}
			}
		}

		try {
			for (const id of ids) {
				await deleteAttachmentAsync(id);
			}
			setToast(
				target.kind === 'group'
					? __('Group resolved · others deleted', 'snopix')
					: sprintf(
							/* translators: %d: number of attachments deleted */
							__('%d duplicate attachments deleted', 'snopix'),
							ids.length
						)
			);
		} catch {
			setToast(__('Some deletions failed. Try again.', 'snopix'));
		} finally {
			setConfirm(null);
		}
	}

	if (isIndexing) {
		return (
			<div>
				<h1 className="text-[26px] font-semibold tracking-[-0.015em] mb-1.5">
					{__('Duplicates', 'snopix')}
				</h1>
				<div className="snopix-card snopix-card--pad text-center text-snopix-muted">
					{__(
						'Indexing is in progress. Duplicate scan is unavailable while indexing.',
						'snopix'
					)}
				</div>
			</div>
		);
	}

	return (
		<>
			<h1 className="text-[26px] font-semibold tracking-[-0.015em] mb-1.5">
				{__('Duplicates', 'snopix')}
			</h1>
			<p className="text-[14px] text-snopix-muted mb-7">
				{__(
					'Visually-identical attachments clustered for review. Pick which to keep, drop the rest.',
					'snopix'
				)}
			</p>

			<div className="snopix-card px-6 py-[18px] mb-5 flex items-center justify-between gap-5 flex-wrap">
				<div className="flex items-center gap-7">
					<div>
						<div className="text-[11px] font-medium text-snopix-muted uppercase tracking-[0.04em]">
							{__('Groups', 'snopix')}
						</div>
						<div className="text-[24px] font-semibold tracking-[-0.015em]">
							{visibleGroups.length}
						</div>
					</div>
					<div className="w-px self-stretch bg-snopix-border" />
					<div>
						<div className="text-[11px] font-medium text-snopix-muted uppercase tracking-[0.04em]">
							{__('Duplicate attachments', 'snopix')}
						</div>
						<div className="text-[24px] font-semibold tracking-[-0.015em]">
							{totalDupCount}
						</div>
					</div>
					<div className="w-px self-stretch bg-snopix-border" />
					<div>
						<div className="text-[11px] font-medium text-snopix-muted uppercase tracking-[0.04em]">
							{__('Recoverable', 'snopix')}
						</div>
						<div className="text-[24px] font-semibold tracking-[-0.015em]">
							{formatBytes(totalWasteBytes)}
						</div>
					</div>
					{lastScanned && (
						<div className="text-[12px] text-snopix-muted">
							{__('Last scan:', 'snopix')} {lastScanned}
						</div>
					)}
				</div>
				<div className="flex items-center gap-3">
					<button
						className="snopix-btn snopix-btn--neutral snopix-btn--sm"
						onClick={() => startScan()}
						disabled={isStarting || isScanning}
					>
						<IconRefresh size={14} />{' '}
						{isScanning
							? __('Scanning…', 'snopix')
							: __('Rescan', 'snopix')}
					</button>
					{duplicateScanState === 'running' && (
						<button
							className="snopix-btn snopix-btn--ghost snopix-btn--sm"
							onClick={() => resetScan()}
							disabled={isResetting}
						>
							{isResetting
								? __('Resetting…', 'snopix')
								: __('Reset', 'snopix')}
						</button>
					)}
					<button
						className="snopix-btn snopix-btn--danger snopix-btn--sm"
						disabled={!visibleGroups.length || isBulkDeleting}
						onClick={() => setConfirm({ kind: 'all' })}
					>
						<IconTrash size={14} />{' '}
						{__('Delete all duplicates', 'snopix')}
					</button>
				</div>
			</div>

			{isScanning && (
				<div className="snopix-card snopix-card--pad mb-5">
					<div className="snopix-progress">
						<div
							className={`snopix-progress__fill ${
								duplicateScanState === 'done'
									? 'bg-snopix-success'
									: 'bg-snopix-accent'
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
					<div className="text-[12px] text-snopix-muted mt-1.5 snopix-mono">
						{duplicateScanState === 'done'
							? __('Scan complete', 'snopix')
							: __('Scanning for duplicates…', 'snopix')}
					</div>
				</div>
			)}

			{conflictMessage && (
				<div className="snopix-card snopix-card--pad mb-5 text-[13px] text-snopix-danger">
					{conflictMessage}
				</div>
			)}

			<div className="snopix-card snopix-card--pad mb-6">
				<div className="flex items-center gap-6">
					<div className="shrink-0 w-[220px]">
						<div className="text-[13px] font-medium text-snopix-text">
							{__('View threshold', 'snopix')}
						</div>
						<div className="text-[12px] text-snopix-muted mt-0.5">
							{__(
								'Hide groups below this score. Display-only filter - does not re-scan.',
								'snopix'
							)}
						</div>
					</div>
					<input
						className="snopix-range"
						type="range"
						min={80}
						max={100}
						step={1}
						value={thresholdPercent}
						onChange={(e) =>
							setThresholdPercent(parseInt(e.target.value, 10))
						}
					/>
					<div className="snopix-mono text-[14px] font-semibold w-16 text-right">
						{thresholdPercent}%
					</div>
				</div>
			</div>

			{!isLoading && !isScanning && visibleGroups.length === 0 ? (
				<div className="snopix-card snopix-card--pad">
					<div className="text-center text-snopix-muted py-12 px-6">
						<div className="text-snopix-border-strong mb-2 flex justify-center">
							<IconCheck size={32} />
						</div>
						<div className="text-[15px] font-medium text-snopix-text mb-1">
							{lastScanned
								? sprintf(
										/* translators: %s: threshold percentage (e.g. "95%") */
										__(
											'No duplicate clusters above %s',
											'snopix'
										),
										`${thresholdPercent}%`
									)
								: __('No scan run yet.', 'snopix')}
						</div>
						<div className="text-[13px]">
							{lastScanned
								? __(
										'Lower the threshold to surface looser visual matches.',
										'snopix'
									)
								: __(
										'Click Rescan to find duplicate images in your media library.',
										'snopix'
									)}
						</div>
					</div>
				</div>
			) : (
				<div className="flex flex-col gap-4">
					{visibleGroups.map((g) => (
						<DuplicateGroupCard
							key={groupKey(g)}
							group={g}
							keepId={getKeepId(g)}
							onKeepChange={(id) => setKeepId(g, id)}
							onResolve={() => setConfirm({ kind: 'group', group: g })}
							isDeleting={isBulkDeleting}
						/>
					))}
				</div>
			)}

			{confirm && (
				<ConfirmModal
					open
					danger
					icon={<IconWarn size={18} />}
					title={
						confirm.kind === 'all'
							? sprintf(
									/* translators: %d: attachment count */
									__('Delete %d attachments?', 'snopix'),
									totalDupCount
								)
							: sprintf(
									/* translators: %d: attachment count */
									__('Delete %d attachments?', 'snopix'),
									confirm.group.images.length - 1
								)
					}
					subtitle={__(
						'This permanently removes the files from your media library.',
						'snopix'
					)}
					confirmText={__('Delete', 'snopix')}
					loading={isBulkDeleting}
					onCancel={() => setConfirm(null)}
					onConfirm={() => performDelete(confirm)}
					message={
						<>
							{__('The selected "keep" attachments stay.', 'snopix')}{' '}
							{__('Everything else in', 'snopix')}{' '}
							{confirm.kind === 'all'
								? __('all visible groups', 'snopix')
								: __('this group', 'snopix')}{' '}
							{__('will be deleted from', 'snopix')}{' '}
							<code className="snopix-mono">wp_posts</code>{' '}
							{__('and the matching', 'snopix')}{' '}
							<code className="snopix-mono">wp_snopix_index</code>{' '}
							{__('rows dropped.', 'snopix')}
						</>
					}
				/>
			)}

			{toast && (
				<Toast message={toast} onDismiss={() => setToast(null)} />
			)}
		</>
	);
}
