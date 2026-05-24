import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useStore } from '../store/use-store';
import {
	DuplicateGroup,
	useDuplicates,
	useStartDuplicateScan,
	useDuplicateScanProgress,
	useDeleteAttachment,
	useResetDuplicateScan,
} from '../hooks/use-duplicates';
import { ConflictError } from '../lib/api';
import DuplicateGroupCard from './DuplicateGroupCard';

/**
 * Stable React key for a duplicate group derived from the first image's id.
 *
 * @param {DuplicateGroup} group Duplicate group from `/duplicates`.
 *
 * @return {string} Group identifier suitable for use as a `key` prop.
 */
function groupKey(group: DuplicateGroup): string {
	return String(group.images[0].id);
}

/**
 * Duplicates tab — scans for and resolves visually identical attachments.
 *
 * Shows the latest scan status (with a progress bar while running) and a list
 * of groups returned by the scanner. The user picks one image per group to
 * keep and can bulk-delete the rest. Disabled while an indexing job is active.
 *
 * @return {JSX.Element}
 */
export default function Duplicates() {
	const { indexingState, duplicateScanState } = useStore();
	const { data, isLoading } = useDuplicates();
	const {
		mutate: startScan,
		isPending,
		error: startError,
	} = useStartDuplicateScan();
	const { mutate: resetScan, isPending: isResetting } =
		useResetDuplicateScan();
	const progress = useDuplicateScanProgress();
	const { mutateAsync: deleteAttachment, isPending: isBulkDeleting } =
		useDeleteAttachment();

	const conflictMessage =
		startError instanceof ConflictError ? startError.message : null;

	const [keepIds, setKeepIds] = useState<Record<string, number>>({});
	const [selectedGroups, setSelectedGroups] = useState<Set<string>>(
		new Set()
	);

	const isIndexing = indexingState === 'running';
	const isScanning =
		duplicateScanState === 'running' || duplicateScanState === 'done';
	const groups = data?.groups ?? [];
	const lastScanned = data?.last_scanned ?? '';

	/**
	 * Resolve the attachment id the user currently wants to keep for a group.
	 * Falls back to the first image in the group when no explicit selection has
	 * been made yet.
	 *
	 * @param {DuplicateGroup} group Group to inspect.
	 *
	 * @return {number} Attachment id of the keep target (0 when group is empty).
	 */
	function getKeepId(group: DuplicateGroup): number {
		return keepIds[groupKey(group)] ?? group.images[0]?.id ?? 0;
	}

	/**
	 * Record the chosen keep-id for a duplicate group so the bulk-delete pass
	 * knows which attachment to skip.
	 *
	 * @param {DuplicateGroup} group Group being updated.
	 * @param {number}         id    Attachment id to keep.
	 *
	 * @return {void}
	 */
	function setKeepId(group: DuplicateGroup, id: number) {
		setKeepIds((prev) => ({ ...prev, [groupKey(group)]: id }));
	}

	/**
	 * Toggle whether a group is part of the bulk-delete selection set.
	 *
	 * @param {DuplicateGroup} group Group to add or remove from selection.
	 *
	 * @return {void}
	 */
	function toggleSelect(group: DuplicateGroup) {
		const key = groupKey(group);
		setSelectedGroups((prev) => {
			const next = new Set(prev);
			if (next.has(key)) {
				next.delete(key);
			} else {
				next.add(key);
			}
			return next;
		});
	}

	const allSelected =
		groups.length > 0 && selectedGroups.size === groups.length;

	/**
	 * Select every group or clear the selection, depending on the current state
	 * of the "select all" checkbox.
	 *
	 * @return {void}
	 */
	function toggleSelectAll() {
		if (allSelected) {
			setSelectedGroups(new Set());
		} else {
			setSelectedGroups(new Set(groups.map(groupKey)));
		}
	}

	/**
	 * Delete every non-keep attachment across the currently selected groups,
	 * then clear the selection. The delete plan is snapshotted into a flat
	 * `idsToDelete` array up-front so that `onSuccess` invalidations mutating
	 * the live `groups` list mid-loop cannot drop work or revisit IDs that
	 * have already been deleted.
	 *
	 * @return {Promise<void>}
	 */
	async function handleBulkDelete() {
		const idsToDelete: number[] = [];
		for (const group of groups) {
			if (!selectedGroups.has(groupKey(group))) {
				continue;
			}

			const keep = getKeepId(group);
			for (const img of group.images) {
				if (img.id !== keep) {
					idsToDelete.push(img.id);
				}
			}
		}

		try {
			for (const id of idsToDelete) {
				await deleteAttachment(id);
			}
		} catch {
			// hook invalidates query on partial success
		} finally {
			setSelectedGroups(new Set());
		}
	}

	/**
	 * Per-group delete handler passed to {@link DuplicateGroupCard}. Routes
	 * through the parent's single shared mutation so concurrent deletes from
	 * multiple cards are serialised behind one `isBulkDeleting` flag.
	 *
	 * @param {number[]} ids Attachment ids in this group to delete.
	 *
	 * @return {Promise<void>}
	 */
	async function handleGroupDelete(ids: number[]) {
		try {
			for (const id of ids) {
				await deleteAttachment(id);
			}
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

					<div className="flex gap-2">
						<button
							className="ps-btn"
							onClick={() => startScan()}
							disabled={isPending || isScanning}
						>
							{isScanning
								? __('Scanning…', 'pixel-scout')
								: __('Scan Now', 'pixel-scout')}
						</button>

						{duplicateScanState === 'running' && (
							<button
								className="ps-btn ps-btn--neutral"
								onClick={() => resetScan()}
								disabled={isResetting}
								title={__(
									'Cancel the running scan and clear its progress so a new one can start.',
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

				{conflictMessage && (
					<div className="text-xs text-ps-danger mt-3">
						{conflictMessage}
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
								: sprintf(
										/* translators: %d: number of duplicate groups */
										__(
											'%d duplicate groups found.',
											'pixel-scout'
										),
										groups.length
									)}
						</label>

						{selectedGroups.size > 0 && (
							<button
								className="ps-btn ps-btn--danger text-xs"
								onClick={handleBulkDelete}
								disabled={isBulkDeleting}
							>
								{isBulkDeleting
									? __('Deleting…', 'pixel-scout')
									: sprintf(
											/* translators: %d: number of selected groups */
											__(
												'Delete %d selected',
												'pixel-scout'
											),
											selectedGroups.size
										)}
							</button>
						)}
					</div>

					{groups.map((group) => (
						<DuplicateGroupCard
							key={groupKey(group)}
							group={group}
							keepId={getKeepId(group)}
							onKeepChange={(id) => setKeepId(group, id)}
							selected={selectedGroups.has(groupKey(group))}
							onToggleSelect={() => toggleSelect(group)}
							onDelete={handleGroupDelete}
							isDeleting={isBulkDeleting}
						/>
					))}
				</div>
			)}
		</div>
	);
}
