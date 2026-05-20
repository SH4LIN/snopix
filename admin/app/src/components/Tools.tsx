import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import ConfirmModal from './ConfirmModal';
import {
	useReindexAll,
	useClearIndex,
	useDeleteOrphans,
	useClearCache,
	useOrphanCount,
} from '../hooks/use-tools';
import { useReindex } from '../hooks/use-reindex';

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

	const reindex = useReindex();
	const reindexAll = useReindexAll();
	const clearIndex = useClearIndex();
	const deleteOrphans = useDeleteOrphans();
	const clearCache = useClearCache();
	const orphanCount = useOrphanCount();

	const orphans = orphanCount.data?.orphans ?? 0;

	const cards: Record<Exclude<ToolKey, null>, ToolCard> = {
		reindex: {
			title: __('Index Missing Images', 'pixel-scout'),
			description: __(
				'Generate fingerprints for attachments that are not yet indexed. Existing index rows are kept.',
				'pixel-scout'
			),
			buttonLabel: __('Index Missing', 'pixel-scout'),
			confirmTitle: __('Index missing images?', 'pixel-scout'),
			confirmMessage: __(
				'New attachments will be scheduled for background fingerprint generation.',
				'pixel-scout'
			),
			confirmText: __('Start', 'pixel-scout'),
			danger: false,
		},
		'reindex-all': {
			title: __('Reindex Everything', 'pixel-scout'),
			description: __(
				'Wipe the entire index and regenerate fingerprints for every attachment. Required after algorithm updates.',
				'pixel-scout'
			),
			buttonLabel: __('Reindex All', 'pixel-scout'),
			confirmTitle: __('Reindex all images?', 'pixel-scout'),
			confirmMessage: __(
				'This deletes every existing fingerprint and re-processes every image. It can take a long time on large libraries.',
				'pixel-scout'
			),
			confirmText: __('Wipe and reindex', 'pixel-scout'),
			danger: true,
		},
		'clear-index': {
			title: __('Clear Index', 'pixel-scout'),
			description: __(
				'Delete every row from the fingerprint table. No images will match until you reindex.',
				'pixel-scout'
			),
			buttonLabel: __('Clear Index', 'pixel-scout'),
			confirmTitle: __('Clear the entire index?', 'pixel-scout'),
			confirmMessage: __(
				'All fingerprints will be deleted. Search will return no results until images are reindexed.',
				'pixel-scout'
			),
			confirmText: __('Delete all', 'pixel-scout'),
			danger: true,
		},
		orphans: {
			title: __('Delete Orphans', 'pixel-scout'),
			description: sprintf(
				/* translators: %d: orphan count */
				__(
					'Remove index rows whose attachment no longer exists. Found %d orphan(s).',
					'pixel-scout'
				),
				orphans
			),
			buttonLabel: __('Delete Orphans', 'pixel-scout'),
			confirmTitle: __('Delete orphan index rows?', 'pixel-scout'),
			confirmMessage: __(
				'Removes stale index entries for attachments that were deleted outside the plugin.',
				'pixel-scout'
			),
			confirmText: __('Delete', 'pixel-scout'),
			danger: true,
		},
		cache: {
			title: __('Clear Cache', 'pixel-scout'),
			description: __(
				'Flush plugin caches and progress transients. Useful if counters or progress appear stuck.',
				'pixel-scout'
			),
			buttonLabel: __('Clear Cache', 'pixel-scout'),
			confirmTitle: __('Clear plugin cache?', 'pixel-scout'),
			confirmMessage: __(
				'Discards cached index queries and resets indexing progress transients.',
				'pixel-scout'
			),
			confirmText: __('Clear', 'pixel-scout'),
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
				setResult(__('Indexing started.', 'pixel-scout'));
			} else if (key === 'reindex-all') {
				await reindexAll.mutateAsync();
				setResult(__('Full reindex started.', 'pixel-scout'));
			} else if (key === 'clear-index') {
				const res = await clearIndex.mutateAsync();
				setResult(
					sprintf(
						/* translators: %d: deleted count */
						__('Deleted %d rows.', 'pixel-scout'),
						res.deleted
					)
				);
			} else if (key === 'orphans') {
				const res = await deleteOrphans.mutateAsync();
				setResult(
					sprintf(
						/* translators: %d: deleted count */
						__('Deleted %d orphan(s).', 'pixel-scout'),
						res.deleted
					)
				);
			} else if (key === 'cache') {
				await clearCache.mutateAsync();
				setResult(__('Cache cleared.', 'pixel-scout'));
			}
		} catch {
			setResult(
				__('Action failed. Check console for details.', 'pixel-scout')
			);
		} finally {
			setActive(null);
		}
	}

	return (
		<div className="flex flex-col gap-4">
			{result && (
				<div className="ps-card text-[13px] text-ps-success">
					{result}
				</div>
			)}

			<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
				{(Object.keys(cards) as Array<Exclude<ToolKey, null>>).map(
					(key) => {
						const card = cards[key];
						return (
							<div
								key={key}
								className="ps-card flex flex-col gap-3"
							>
								<div>
									<h3 className="text-[15px] font-semibold text-ps-text mb-1">
										{card.title}
									</h3>
									<p className="text-[13px] text-ps-muted leading-snug">
										{card.description}
									</p>
								</div>
								<div className="mt-auto">
									<button
										className={`ps-btn ${card.danger ? 'bg-ps-danger border-ps-danger' : ''}`}
										onClick={() => setActive(key)}
										disabled={loading}
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
