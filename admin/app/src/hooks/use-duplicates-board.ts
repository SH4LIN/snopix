import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	useDeleteAttachment,
	useDuplicateScanProgress,
	useDuplicates,
	useResetDuplicateScan,
	useStartDuplicateScan,
	type DuplicateGroup,
} from './use-duplicates';
import { useStore } from '../store/use-store';

/**
 * Stable React key + dictionary lookup key for a duplicate group. Derived
 * from the first image id so re-rendering across cache invalidations keeps
 * the same group anchored to the same DOM node.
 *
 * @param {DuplicateGroup} group Duplicate group from `/duplicates`.
 *
 * @return {string}
 */
export function groupKey(group: DuplicateGroup): string {
	return String(group.images[0]?.id ?? '');
}

/**
 * Aggregate state + actions consumed by `DuplicatesDesktop` and
 * `DuplicatesMobile`.
 */
export interface DuplicatesBoard {
	/** Currently loaded groups (already filtered to ≥ 2 images by the server). */
	groups: DuplicateGroup[];
	/** Sum of recoverable bytes across all groups. */
	totalWasteBytes: number;
	/** Timestamp of the last scan. Empty string when never scanned. */
	lastScanned: string;
	/** Per-group "keep" selection map keyed by {@link groupKey}. */
	keepIds: Record<string, number>;
	/** Return the kept id for a group, defaulting to the first image. */
	getKeepId: (group: DuplicateGroup) => number;
	/** Mark an attachment as the kept image for a group. */
	setKeepId: (group: DuplicateGroup, id: number) => void;
	/** True while the initial group payload is loading. */
	isLoading: boolean;
	/** True while a background scan is running OR a "done" toast is shown. */
	isScanning: boolean;
	/** True while a foreground bulk index job blocks duplicate scans. */
	isIndexing: boolean;
	/** Latest scan progress while running, or undefined. */
	progress: ReturnType<typeof useDuplicateScanProgress>;
	/** Live scan state machine value from the store. */
	duplicateScanState: 'idle' | 'running' | 'done';
	/** Kick off a new background scan. */
	startScan: () => void;
	/** True while the scan POST is in flight. */
	isStarting: boolean;
	/** Abort a running scan and reset progress. */
	resetScan: () => void;
	/** True while the reset POST is in flight. */
	isResetting: boolean;
	/** Conflict error from the last `startScan` attempt, if any. */
	startError: unknown;
	/** Delete every image in a group except the kept one. Confirms via window.confirm. */
	deleteOthers: (group: DuplicateGroup) => Promise<void>;
	/** Raw per-id delete mutation, exposed for desktop's custom confirm-modal flow. */
	deleteAttachmentAsync: (id: number) => Promise<unknown>;
	/** True while any bulk delete is in flight. */
	isDeleting: boolean;
}

/**
 * View-model for the Duplicates screens.
 *
 * Pulls the duplicates query, scan mutations, progress polling, and delete
 * mutation together and exposes the "keep selection + delete others" UI
 * affordance both viewport variants share. Components consume this and only
 * worry about layout.
 *
 * @return {DuplicatesBoard}
 */
export function useDuplicatesBoard(): DuplicatesBoard {
	const { data, isLoading } = useDuplicates();
	const { indexingState, duplicateScanState } = useStore();
	const {
		mutate: startScan,
		isPending: isStarting,
		error: startError,
	} = useStartDuplicateScan();
	const { mutate: resetScan, isPending: isResetting } = useResetDuplicateScan();
	const progress = useDuplicateScanProgress();
	const { mutateAsync: deleteAttachment, isPending: isDeleting } =
		useDeleteAttachment();

	const [keepIds, setKeepIds] = useState<Record<string, number>>({});

	const groups = data?.groups ?? [];
	const lastScanned = data?.last_scanned ?? '';
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
		if (targets.length === 0) {
			return;
		}

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

	return {
		groups,
		totalWasteBytes,
		lastScanned,
		keepIds,
		getKeepId,
		setKeepId,
		isLoading,
		isScanning,
		isIndexing,
		progress,
		duplicateScanState,
		startScan: () => startScan(),
		isStarting,
		resetScan: () => resetScan(),
		isResetting,
		startError,
		deleteOthers,
		deleteAttachmentAsync: deleteAttachment,
		isDeleting,
	};
}
