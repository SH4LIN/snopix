import { useState, type ComponentType, type ReactNode } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import ConfirmModal from './ConfirmModal';
import Toast from './Toast';
import { useStore } from '../store/use-store';
import { useIndexStatus } from '../hooks/use-index-status';
import { useIndexingProgress, useResetProgress } from '../hooks/use-reindex';
import {
	useReindexAll,
	useClearIndex,
	useDeleteOrphans,
	useClearCache,
	useOrphanCount,
} from '../hooks/use-tools';
import { ConflictError } from '../lib/api';
import {
	IconBroom,
	IconCheck,
	IconInfo,
	IconRefresh,
	IconTrash,
	IconX,
} from './icons';

type ActionKey = 'reindex' | 'orphans' | 'cache' | 'clear';

interface Action {
	id: ActionKey;
	Icon: ComponentType<{ size?: number }>;
	title: string;
	description: ReactNode;
	btn: string;
	danger: boolean;
	confirmBody: ReactNode;
}

/**
 * Tools tab — index maintenance actions and the running-job status panel.
 *
 * Renders four action cards (Reindex everything, Delete orphan rows, Flush
 * plugin caches, Clear the index) plus a live progress card for any bulk job
 * driven by the global indexing state machine. Confirms destructive actions
 * via {@link ConfirmModal} and surfaces results in a transient {@link Toast}.
 *
 * @return {JSX.Element}
 */
export default function Tools() {
	const { indexingState, duplicateScanState } = useStore();
	const { data: status } = useIndexStatus();
	const progress = useIndexingProgress();
	const reindexAll = useReindexAll();
	const clearIndex = useClearIndex();
	const deleteOrphans = useDeleteOrphans();
	const clearCache = useClearCache();
	const orphans = useOrphanCount();
	const { mutate: resetProgress, isPending: isResetting } = useResetProgress();

	const [confirm, setConfirm] = useState<Action | null>(null);
	const [toast, setToast] = useState<string | null>(null);

	const isRunning = indexingState === 'running';
	const isStalled = indexingState === 'stalled';
	const isDone = indexingState === 'done';
	const isJobActive = isRunning || isStalled;
	const scanActive = duplicateScanState === 'running';
	const orphanCount = orphans.data?.orphans ?? 0;

	const total = progress?.total ?? status?.total ?? 0;
	const done = progress?.done ?? 0;
	const pct = total > 0 ? Math.round((done / total) * 100) : 0;
	const etaMin = Math.max(1, Math.round(((total - done) * 0.18) / 60));

	const loading =
		reindexAll.isPending ||
		clearIndex.isPending ||
		deleteOrphans.isPending ||
		clearCache.isPending;

	const blockingMessage = isJobActive
		? __(
				'A bulk indexing job is active. Reset it above to run other index tools.',
				'snopix'
			)
		: scanActive
			? __(
					'A duplicate scan is active. Wait for it to finish or reset it from the Duplicates tab.',
					'snopix'
				)
			: null;

	const actions: Action[] = [
		{
			id: 'reindex',
			Icon: IconRefresh,
			title: __('Reindex everything', 'snopix'),
			description: __(
				"Drops the existing fingerprints and re-fingerprints every attachment in your library. Runs in chained WP-Cron batches — won't block the request.",
				'snopix'
			),
			btn: __('Reindex all', 'snopix'),
			danger: false,
			confirmBody: __(
				'All fingerprints will be recomputed. This takes a few minutes for a library this size; reverse-image search returns approximate results until it finishes.',
				'snopix'
			),
		},
		{
			id: 'orphans',
			Icon: IconBroom,
			title: __('Delete orphan rows', 'snopix'),
			description: (
				<>
					<span className="snopix-mono">wp_snopix_index</span>{' '}
					{__(
						'rows whose attachment was deleted outside the plugin. Safe to run.',
						'snopix'
					)}
				</>
			),
			btn: sprintf(
				/* translators: %d: orphan count */
				__('Delete %d orphans', 'snopix'),
				orphanCount
			),
			danger: false,
			confirmBody: __(
				'Rows in wp_snopix_index pointing to attachments that no longer exist will be removed. No media files are touched.',
				'snopix'
			),
		},
		{
			id: 'cache',
			Icon: IconBroom,
			title: __('Flush plugin caches', 'snopix'),
			description: __(
				'Clears every Snopix transient — useful after schema or threshold changes.',
				'snopix'
			),
			btn: __('Flush caches', 'snopix'),
			danger: false,
			confirmBody: __(
				'All cached search results and progress transients will be cleared. The next search request will be slightly slower.',
				'snopix'
			),
		},
		{
			id: 'clear',
			Icon: IconTrash,
			title: __('Clear the index', 'snopix'),
			description: __(
				'Empties wp_snopix_index entirely. Search and duplicate detection will return nothing until reindexed.',
				'snopix'
			),
			btn: __('Clear index', 'snopix'),
			danger: true,
			confirmBody: __(
				'Every fingerprint will be deleted from wp_snopix_index. Until you reindex, the search dropzone and the Duplicates tab will be empty. Your media library is not affected.',
				'snopix'
			),
		},
	];

	async function run(action: Action) {
		setConfirm(null);
		try {
			if (action.id === 'reindex') {
				await reindexAll.mutateAsync();
				setToast(__('Reindex started · running in background', 'snopix'));
			} else if (action.id === 'orphans') {
				const res = await deleteOrphans.mutateAsync();
				setToast(
					sprintf(
						/* translators: %d: deleted count */
						__('%d orphan rows deleted', 'snopix'),
						res.deleted
					)
				);
			} else if (action.id === 'cache') {
				await clearCache.mutateAsync();
				setToast(__('Transients flushed', 'snopix'));
			} else if (action.id === 'clear') {
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
				setToast(__('Action failed. Check console for details.', 'snopix'));
			}
		}
	}

	const locked = (key: ActionKey) =>
		key !== 'orphans' && (isJobActive || scanActive);

	return (
		<>
			<h1 className="text-[26px] font-semibold tracking-[-0.015em] mb-1.5">
				{__('Tools', 'snopix')}
			</h1>
			<p className="text-[14px] text-snopix-muted mb-7">
				{__(
					'Maintenance actions for the fingerprint index. None of these touch your media library files.',
					'snopix'
				)}
			</p>

			<div className="snopix-card snopix-card--pad mb-6">
				<div
					className={`flex items-center justify-between gap-4 ${isJobActive ? 'mb-3.5' : ''}`}
				>
					<div className="flex items-center gap-3 min-w-0">
						<div
							className={`w-9 h-9 rounded-lg grid place-items-center shrink-0 ${
								isJobActive
									? 'bg-snopix-accent-soft text-snopix-accent'
									: 'bg-[rgba(52,199,89,0.12)] text-snopix-success'
							}`}
						>
							{isJobActive ? (
								<IconRefresh size={18} />
							) : (
								<IconCheck size={18} />
							)}
						</div>
						<div className="min-w-0">
							<div className="text-[15px] font-semibold">
								{isStalled
									? __('Indexer stalled', 'snopix')
									: isRunning
										? __('Indexing attachments', 'snopix')
										: isDone
											? __('Indexing complete', 'snopix')
											: __('Background indexer idle', 'snopix')}
							</div>
							<div className="text-[13px] text-snopix-muted mt-0.5">
								{isJobActive && progress ? (
									<>
										<span className="snopix-mono">
											{done.toLocaleString()} /{' '}
											{total.toLocaleString()}
										</span>{' '}
										· {__('chained WP-Cron batches', 'snopix')}
									</>
								) : (
									__('Last run idle.', 'snopix')
								)}
							</div>
						</div>
					</div>
					{isJobActive ? (
						<button
							className="snopix-btn snopix-btn--ghost snopix-btn--sm"
							onClick={() => resetProgress()}
							disabled={isResetting}
						>
							<IconX size={14} />{' '}
							{isResetting
								? __('Resetting…', 'snopix')
								: __('Cancel', 'snopix')}
						</button>
					) : (
						<button
							className="snopix-btn snopix-btn--ghost snopix-btn--sm"
							onClick={() =>
								setConfirm(
									actions.find((a) => a.id === 'reindex')!
								)
							}
							disabled={loading}
						>
							<IconRefresh size={14} />{' '}
							{__('Start indexer', 'snopix')}
						</button>
					)}
				</div>
				{isJobActive && (
					<>
						<div className="snopix-progress">
							<div
								className="snopix-progress__fill"
								style={{ width: `${pct}%` }}
							/>
						</div>
						<div className="flex justify-between mt-2 snopix-mono text-[11px] text-snopix-muted">
							<span>{pct}%</span>
							<span>
								{sprintf(
									/* translators: %d: minutes remaining */
									__('est. %d min remaining', 'snopix'),
									etaMin
								)}
							</span>
						</div>
					</>
				)}
			</div>

			{blockingMessage && (
				<div className="snopix-card snopix-card--pad mb-6 text-[13px] text-snopix-muted">
					{blockingMessage}
				</div>
			)}

			<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
				{actions.map((a) => {
					const isLocked = locked(a.id);
					return (
						<div
							key={a.id}
							className="snopix-card snopix-card--pad flex flex-col"
						>
							<div className="flex items-start gap-3 mb-3">
								<div
									className={`w-8 h-8 rounded-lg shrink-0 grid place-items-center ${
										a.danger
											? 'bg-[rgba(255,59,48,0.08)] text-snopix-danger'
											: 'bg-snopix-surface text-snopix-muted'
									}`}
								>
									<a.Icon size={16} />
								</div>
								<div className="flex-1 min-w-0">
									<div className="text-[15px] font-semibold">
										{a.title}
									</div>
								</div>
							</div>
							<div className="text-[13px] text-snopix-muted leading-[1.55] mb-4">
								{a.description}
							</div>
							<div className="mt-auto">
								<button
									className={
										a.danger
											? 'snopix-btn snopix-btn--danger snopix-btn--sm'
											: 'snopix-btn snopix-btn--neutral snopix-btn--sm'
									}
									onClick={() => setConfirm(a)}
									disabled={
										loading ||
										isLocked ||
										(a.id === 'orphans' && orphanCount === 0)
									}
									title={
										isLocked
											? __(
													'Disabled while a bulk job is active.',
													'snopix'
												)
											: undefined
									}
								>
									{a.danger ? (
										<IconTrash size={14} />
									) : (
										<a.Icon size={14} />
									)}
									{a.btn}
								</button>
							</div>
						</div>
					);
				})}
			</div>

			<div className="snopix-card snopix-card--pad mt-6">
				<div className="flex items-start gap-3">
					<div className="text-snopix-muted">
						<IconInfo size={18} />
					</div>
					<div>
						<div className="text-[14px] font-semibold mb-1">
							{__('Where Snopix stores data', 'snopix')}
						</div>
						<div className="text-[13px] text-snopix-muted leading-[1.6]">
							{__('One custom table —', 'snopix')}{' '}
							<code className="snopix-mono text-snopix-text">
								wp_snopix_index
							</code>{' '}
							{__(
								'— with one compact row per indexed attachment. Uninstalling the plugin drops the table and removes every Snopix option and transient (when uninstall cleanup is enabled).',
								'snopix'
							)}
						</div>
					</div>
				</div>
			</div>

			{confirm && (
				<ConfirmModal
					open
					title={`${confirm.title}?`}
					subtitle={
						confirm.danger
							? __('Destructive · this cannot be undone', 'snopix')
							: __('Safe to run', 'snopix')
					}
					confirmText={confirm.btn}
					danger={confirm.danger}
					loading={loading}
					icon={<confirm.Icon size={18} />}
					message={confirm.confirmBody}
					onCancel={() => setConfirm(null)}
					onConfirm={() => run(confirm)}
				/>
			)}

			{toast && <Toast message={toast} onDismiss={() => setToast(null)} />}
		</>
	);
}
