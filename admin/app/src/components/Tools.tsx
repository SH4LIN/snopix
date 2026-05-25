import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import ConfirmModal from './ConfirmModal';
import { useStore } from '../store/use-store';
import {
	useReindexAll,
	useClearIndex,
	useDeleteOrphans,
	useClearCache,
	useOrphanCount,
} from '../hooks/use-tools';
import { useReindex } from '../hooks/use-reindex';
import { ConflictError } from '../lib/api';

type ToolKey =
	| 'reindex'
	| 'reindex-all'
	| 'clear-index'
	| 'orphans'
	| 'cache'
	| null;

interface ToolCard {
	title: string;
	description: string;
	buttonLabel: string;
	confirmTitle: string;
	confirmMessage: string;
	confirmText: string;
	danger: boolean;
}

/**
 * "Tools" tab — destructive and maintenance index actions.
 *
 * Renders one card per action (index missing, full reindex, clear index,
 * delete orphans, clear cache). Each card opens a {@link ConfirmModal} before
 * invoking the matching mutation hook; the result toast is shown at the top of
 * the panel.
 *
 * @return {JSX.Element}
 */
export default function Tools() {
	const [active, setActive] = useState<ToolKey>(null);
	const [result, setResult] = useState<string | null>(null);

	const { indexingState, duplicateScanState } = useStore();
	const reindex = useReindex();
	const reindexAll = useReindexAll();
	const clearIndex = useClearIndex();
	const deleteOrphans = useDeleteOrphans();
	const clearCache = useClearCache();
	const orphanCount = useOrphanCount();

	const orphans = orphanCount.data?.orphans ?? 0;

	const indexingActive =
		indexingState === 'running' || indexingState === 'stalled';
	const scanActive = duplicateScanState === 'running';
	const blockingMessage = indexingActive
		? __(
				'A bulk indexing job is currently active. Reset it from the Dashboard to run index tools.',
				'snopix'
			)
		: scanActive
			? __(
					'A duplicate scan is currently active. Wait for it to finish or reset it from the Duplicates tab.',
					'snopix'
				)
			: null;

	// Actions that mutate index rows OR reset progress transients — must not
	// run concurrently with a bulk job. Clear-cache is locked because it
	// resets the progress envelope (server also enforces this with a 409);
	// orphan deletion only touches dead rows and is safe at any time.
	const indexLockedKeys: Array<Exclude<ToolKey, null>> = [
		'reindex',
		'reindex-all',
		'clear-index',
		'cache',
	];

	const cards: Record<Exclude<ToolKey, null>, ToolCard> = {
		reindex: {
			title: __('Index Missing Images', 'snopix'),
			description: __(
				'Generate fingerprints for attachments that are not yet indexed. Existing index rows are kept.',
				'snopix'
			),
			buttonLabel: __('Index Missing', 'snopix'),
			confirmTitle: __('Index missing images?', 'snopix'),
			confirmMessage: __(
				'New attachments will be scheduled for background fingerprint generation.',
				'snopix'
			),
			confirmText: __('Start', 'snopix'),
			danger: false,
		},
		'reindex-all': {
			title: __('Reindex Everything', 'snopix'),
			description: __(
				'Wipe the entire index and regenerate fingerprints for every attachment. Required after algorithm updates.',
				'snopix'
			),
			buttonLabel: __('Reindex All', 'snopix'),
			confirmTitle: __('Reindex all images?', 'snopix'),
			confirmMessage: __(
				'This deletes every existing fingerprint and re-processes every image. It can take a long time on large libraries.',
				'snopix'
			),
			confirmText: __('Wipe and reindex', 'snopix'),
			danger: true,
		},
		'clear-index': {
			title: __('Clear Index', 'snopix'),
			description: __(
				'Delete every row from the fingerprint table. No images will match until you reindex.',
				'snopix'
			),
			buttonLabel: __('Clear Index', 'snopix'),
			confirmTitle: __('Clear the entire index?', 'snopix'),
			confirmMessage: __(
				'All fingerprints will be deleted. Search will return no results until images are reindexed.',
				'snopix'
			),
			confirmText: __('Delete all', 'snopix'),
			danger: true,
		},
		orphans: {
			title: __('Delete Orphans', 'snopix'),
			description: sprintf(
				/* translators: %d: orphan count */
				__(
					'Remove index rows whose attachment no longer exists. Found %d orphan(s).',
					'snopix'
				),
				orphans
			),
			buttonLabel: __('Delete Orphans', 'snopix'),
			confirmTitle: __('Delete orphan index rows?', 'snopix'),
			confirmMessage: __(
				'Removes stale index entries for attachments that were deleted outside the plugin.',
				'snopix'
			),
			confirmText: __('Delete', 'snopix'),
			danger: true,
		},
		cache: {
			title: __('Clear Cache', 'snopix'),
			description: __(
				'Flush plugin caches and progress transients. Useful if counters or progress appear stuck.',
				'snopix'
			),
			buttonLabel: __('Clear Cache', 'snopix'),
			confirmTitle: __('Clear plugin cache?', 'snopix'),
			confirmMessage: __(
				'Discards cached index queries and resets indexing progress transients.',
				'snopix'
			),
			confirmText: __('Clear', 'snopix'),
			danger: false,
		},
	};

	const loading =
		reindex.isPending ||
		reindexAll.isPending ||
		clearIndex.isPending ||
		deleteOrphans.isPending ||
		clearCache.isPending;

	/**
	 * Invoke the mutation hook for the requested tool action and surface a
	 * localized result string. Always closes the confirm modal afterwards.
	 *
	 * @param {Exclude<ToolKey, null>} key Identifier of the tool to execute.
	 *
	 * @return {Promise<void>}
	 */
	async function run(key: Exclude<ToolKey, null>) {
		setResult(null);
		try {
			if (key === 'reindex') {
				await reindex.mutateAsync();
				setResult(__('Indexing started.', 'snopix'));
			} else if (key === 'reindex-all') {
				await reindexAll.mutateAsync();
				setResult(__('Full reindex started.', 'snopix'));
			} else if (key === 'clear-index') {
				const res = await clearIndex.mutateAsync();
				setResult(
					sprintf(
						/* translators: %d: deleted count */
						__('Deleted %d rows.', 'snopix'),
						res.deleted
					)
				);
			} else if (key === 'orphans') {
				const res = await deleteOrphans.mutateAsync();
				setResult(
					sprintf(
						/* translators: %d: deleted count */
						__('Deleted %d orphan(s).', 'snopix'),
						res.deleted
					)
				);
			} else if (key === 'cache') {
				await clearCache.mutateAsync();
				setResult(__('Cache cleared.', 'snopix'));
			}
		} catch (err) {
			if (err instanceof ConflictError) {
				setResult(err.message);
			} else {
				setResult(
					__(
						'Action failed. Check console for details.',
						'snopix'
					)
				);
			}
		} finally {
			setActive(null);
		}
	}

	return (
		<div className="flex flex-col gap-4">
			{result && (
				<div className="snopix-card text-[13px] text-snopix-success">
					{result}
				</div>
			)}

			{blockingMessage && (
				<div className="snopix-card text-[13px] text-snopix-danger">
					{blockingMessage}
				</div>
			)}

			<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
				{(Object.keys(cards) as Array<Exclude<ToolKey, null>>).map(
					(key) => {
						const card = cards[key];
						const locked =
							indexingActive && indexLockedKeys.includes(key);
						return (
							<div
								key={key}
								className="snopix-card flex flex-col gap-3"
							>
								<div>
									<h3 className="text-[15px] font-semibold text-snopix-text mb-1">
										{card.title}
									</h3>
									<p className="text-[13px] text-snopix-muted leading-snug">
										{card.description}
									</p>
								</div>
								<div className="mt-auto">
									<button
										className={`snopix-btn ${card.danger ? 'snopix-btn--danger' : ''}`}
										onClick={() => setActive(key)}
										disabled={loading || locked}
										title={
											locked
												? __(
														'Disabled while a bulk indexing job is active.',
														'snopix'
													)
												: undefined
										}
									>
										{card.buttonLabel}
									</button>
								</div>
							</div>
						);
					}
				)}
			</div>

			{active && (
				<ConfirmModal
					open={true}
					title={cards[active].confirmTitle}
					message={cards[active].confirmMessage}
					confirmText={cards[active].confirmText}
					danger={cards[active].danger}
					loading={loading}
					onConfirm={() => run(active)}
					onCancel={() => setActive(null)}
				/>
			)}
		</div>
	);
}
