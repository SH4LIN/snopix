import { useState, type ComponentType, type ReactNode } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import ConfirmModal from './ConfirmModal';
import Toast from './Toast';
import { useStore } from '../store/use-store';
import { useIndexStatus } from '../hooks/use-index-status';
import { useIndexingProgress, useResetProgress } from '../hooks/use-reindex';
import {
	useToolActions,
	type ToolActionId,
} from '../hooks/use-tool-actions';
import {
	IconBroom,
	IconCheck,
	IconInfo,
	IconRefresh,
	IconTrash,
	IconX,
} from './icons';

interface Action {
	id: ToolActionId;
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
	const { mutate: resetProgress, isPending: isResetting } = useResetProgress();
	const { run, loading, orphanCount, toast, dismissToast } = useToolActions();

	const [confirm, setConfirm] = useState<Action | null>(null);

	const isRunning = indexingState === 'running';
	const isStalled = indexingState === 'stalled';
	const isDone = indexingState === 'done';
	const isJobActive = isRunning || isStalled;
	const scanActive = duplicateScanState === 'running';

	const total = progress?.total ?? status?.total ?? 0;
	const done = progress?.done ?? 0;
	const pct = total > 0 ? Math.round((done / total) * 100) : 0;
	const etaMin = Math.max(1, Math.round(((total - done) * 0.18) / 60));

	const blockingMessage = isJobActive
		? __(
				'Indexing is running. Cancel it above to use the other tools.',
				'snopix'
			)
		: scanActive
			? __(
					'A duplicate scan is running. Wait for it to finish, or cancel it from the Duplicates tab.',
					'snopix'
				)
			: null;

	const actions: Action[] = [
		{
			id: 'reindex',
			Icon: IconRefresh,
			title: __('Reindex everything', 'snopix'),
			description: __(
				'Rebuilds the search index from scratch for every image in your library. Runs in the background, so you can keep working.',
				'snopix'
			),
			btn: __('Reindex all', 'snopix'),
			danger: false,
			confirmBody: __(
				'The search index will be rebuilt for every image. This can take a few minutes; image search may return partial results until it finishes.',
				'snopix'
			),
		},
		{
			id: 'orphans',
			Icon: IconBroom,
			title: __('Remove leftover entries', 'snopix'),
			description: __(
				'Removes index entries for images that were deleted from your media library outside of Snopix. Safe to run.',
				'snopix'
			),
			btn: sprintf(
				/* translators: %d: number of leftover entries */
				__('Remove %d leftover entries', 'snopix'),
				orphanCount
			),
			danger: false,
			confirmBody: __(
				'Index entries pointing to images that no longer exist will be removed. Your media files are not touched.',
				'snopix'
			),
		},
		{
			id: 'cache',
			Icon: IconBroom,
			title: __('Clear the cache', 'snopix'),
			description: __(
				'Clears Snopix’s temporary cached data. Useful if something looks out of date after changing settings.',
				'snopix'
			),
			btn: __('Clear cache', 'snopix'),
			danger: false,
			confirmBody: __(
				'Snopix’s cached data will be cleared. The next search may be slightly slower while the cache rebuilds.',
				'snopix'
			),
		},
		{
			id: 'clear',
			Icon: IconTrash,
			title: __('Empty the search index', 'snopix'),
			description: __(
				'Removes everything from the search index. Image search and duplicate detection will be empty until you reindex.',
				'snopix'
			),
			btn: __('Empty index', 'snopix'),
			danger: true,
			confirmBody: __(
				'The entire search index will be deleted. Until you reindex, image search and the Duplicates tab will be empty. Your media library is not affected.',
				'snopix'
			),
		},
	];

	async function confirmRun(action: Action) {
		setConfirm(null);
		await run(action.id);
	}

	const locked = (key: ToolActionId) =>
		key !== 'orphans' && (isJobActive || scanActive);

	return (
		<>
			<h1 className="text-[26px] font-semibold tracking-[-0.015em] mb-1.5">
				{__('Tools', 'snopix')}
			</h1>
			<p className="text-[14px] text-snopix-muted mb-7">
				{__(
					'Maintenance for the Snopix search index. None of these change or delete your actual images.',
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
									? __('Indexing stuck', 'snopix')
									: isRunning
										? __('Indexing your images', 'snopix')
										: isDone
											? __('Indexing complete', 'snopix')
											: __('Not currently indexing', 'snopix')}
							</div>
							<div className="text-[13px] text-snopix-muted mt-0.5">
								{isJobActive && progress ? (
									<>
										<span className="snopix-mono">
											{done.toLocaleString()} /{' '}
											{total.toLocaleString()}
										</span>{' '}
										· {__('running in the background', 'snopix')}
									</>
								) : isDone ? (
									__('Last run finished successfully.', 'snopix')
								) : (
									__('Nothing running right now.', 'snopix')
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
							{__('Start indexing', 'snopix')}
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
													'Unavailable while indexing is running.',
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
							{__(
								'Snopix keeps its search index in a single database table,',
								'snopix'
							)}{' '}
							<code className="snopix-mono text-snopix-text">
								wp_snopix_index
							</code>
							{__(
								', with one small row per indexed image. Deleting the plugin removes this table and all Snopix settings only if you turned on “Delete all Snopix data” in Settings.',
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
					onConfirm={() => confirmRun(confirm)}
				/>
			)}

			{toast && <Toast message={toast} onDismiss={dismissToast} />}
		</>
	);
}
