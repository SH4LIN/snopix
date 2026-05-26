import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	useClearCache,
	useClearIndex,
	useDeleteOrphans,
	useOrphanCount,
	useReindexAll,
} from './use-tools';
import { ConflictError } from '../lib/api';

/**
 * Identifier for one of the four supported maintenance actions.
 */
export type ToolActionId = 'reindex' | 'orphans' | 'cache' | 'clear';

/**
 * Aggregated state + dispatch exposed to Tools screens.
 */
export interface ToolActions {
	/** Execute a single action, mapping its result to a toast message. */
	run: (id: ToolActionId) => Promise<void>;
	/** True while any of the four mutations is in flight. */
	loading: boolean;
	/** Individual per-mutation pending flags, in case the UI needs them. */
	pending: Record<ToolActionId, boolean>;
	/** Latest orphan row count from the server (0 while loading). */
	orphanCount: number;
	/** Current toast message (or null). */
	toast: string | null;
	/** Dismiss the active toast. */
	dismissToast: () => void;
	/** Manually push a toast message — used by desktop chrome (e.g. after Cancel). */
	setToast: (message: string | null) => void;
}

/**
 * View-model for the Tools screens.
 *
 * Wraps the four maintenance mutations with a single `run(id)` dispatch that
 * normalises their result payloads into user-facing toast copy. Both the
 * desktop Tools tab (with its ConfirmModal flow) and the mobile Tools sheet
 * (with inline `window.confirm` per action) call into the same `run`, so a
 * future change to action semantics — e.g. swapping in a different deletion
 * pipeline — happens in exactly one place.
 *
 * @return {ToolActions}
 */
export function useToolActions(): ToolActions {
	const reindexAll = useReindexAll();
	const clearIndex = useClearIndex();
	const deleteOrphans = useDeleteOrphans();
	const clearCache = useClearCache();
	const orphans = useOrphanCount();

	const [toast, setToast] = useState<string | null>(null);

	const orphanCount = orphans.data?.orphans ?? 0;
	const pending: Record<ToolActionId, boolean> = {
		reindex: reindexAll.isPending,
		orphans: deleteOrphans.isPending,
		cache: clearCache.isPending,
		clear: clearIndex.isPending,
	};
	const loading =
		pending.reindex || pending.orphans || pending.cache || pending.clear;

	async function run(id: ToolActionId): Promise<void> {
		try {
			if (id === 'reindex') {
				await reindexAll.mutateAsync();
				setToast(__('Reindex started · running in background', 'snopix'));
			} else if (id === 'orphans') {
				const res = await deleteOrphans.mutateAsync();
				setToast(
					sprintf(
						/* translators: %d: deleted count */
						__('%d orphan rows deleted', 'snopix'),
						res.deleted
					)
				);
			} else if (id === 'cache') {
				await clearCache.mutateAsync();
				setToast(__('Transients flushed', 'snopix'));
			} else if (id === 'clear') {
				const res = await clearIndex.mutateAsync();
				setToast(
					sprintf(
						/* translators: %d: deleted count */
						__('Index cleared · %d rows removed', 'snopix'),
						res.deleted
					)
				);
			}
		} catch (err) {
			if (err instanceof ConflictError) {
				setToast(err.message);
			} else {
				setToast(
					__('Action failed. Check console for details.', 'snopix')
				);
			}
		}
	}

	return {
		run,
		loading,
		pending,
		orphanCount,
		toast,
		dismissToast: () => setToast(null),
		setToast,
	};
}
