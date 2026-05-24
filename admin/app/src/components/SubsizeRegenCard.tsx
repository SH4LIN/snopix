import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import ConfirmModal from './ConfirmModal';
import {
	useSubsizeDiff,
	useRegenMissing,
	useRegenAll,
	useAcknowledgeSubsizeDiff,
	useSubsizeProgress,
} from '../hooks/use-tools';

type ConfirmKey = 'missing' | 'all' | 'dismiss' | null;

/**
 * Tools-tab card: registered-subsize change detector + regen/dismiss actions.
 *
 * @return {JSX.Element}
 */
export default function SubsizeRegenCard() {
	const [confirm, setConfirm] = useState<ConfirmKey>(null);
	const [result, setResult] = useState<string | null>(null);

	const diffQuery = useSubsizeDiff();
	const progress = useSubsizeProgress();
	const regenMissing = useRegenMissing();
	const regenAll = useRegenAll();
	const acknowledge = useAcknowledgeSubsizeDiff();

	const diff = diffQuery.data;
	const loading =
		regenMissing.isPending ||
		regenAll.isPending ||
		acknowledge.isPending;
	const hasChanges = !!diff?.has_changes;
	const running = progress.data?.status === 'running';

	async function run(key: Exclude<ConfirmKey, null>) {
		setResult(null);
		try {
			if (key === 'all') {
				const res = await regenAll.mutateAsync();
				setResult(
					sprintf(
						/* translators: %d: attachment count */
						__('Scheduled regen for %d image(s).', 'pixel-scout'),
						res.count
					)
				);
			} else if (key === 'missing') {
				const res = await regenMissing.mutateAsync();
				setResult(
					sprintf(
						/* translators: %d: attachment count */
						__('Scheduled regen for %d image(s).', 'pixel-scout'),
						res.count
					)
				);
			} else {
				await acknowledge.mutateAsync();
				setResult(__('Dismissed size change notice.', 'pixel-scout'));
			}
		} catch {
			setResult(
				__('Action failed. Check console.', 'pixel-scout')
			);
		} finally {
			setConfirm(null);
		}
	}

	const summary = diff
		? sprintf(
				/* translators: 1: new count, 2: removed count, 3: changed count */
				__(
					'Detected %1$d new, %2$d removed, %3$d changed image size(s).',
					'pixel-scout'
				),
				diff.new.length,
				diff.removed.length,
				diff.changed.length
			)
		: __('Loading registered sizes…', 'pixel-scout');

	return (
		<div className="ps-card flex flex-col gap-3">
			<div>
				<h3 className="text-[15px] font-semibold text-ps-text mb-1">
					{__('Image Subsize Regeneration', 'pixel-scout')}
				</h3>
				<p className="text-[13px] text-ps-muted leading-snug">
					{diffQuery.isError
						? __(
								'Could not load size info. Refresh to retry.',
								'pixel-scout'
							)
						: summary}
				</p>
				{running && progress.data && (
					<p className="text-[12px] text-ps-muted mt-1">
						{sprintf(
							/* translators: 1: done, 2: total */
							__('Regenerating… %1$d / %2$d', 'pixel-scout'),
							progress.data.done,
							progress.data.total
						)}
					</p>
				)}
				{progress.data?.status === 'stalled' && (
					<p className="text-[12px] text-ps-danger mt-1">
						{__('Regeneration stalled. Check error log.', 'pixel-scout')}
					</p>
				)}
				{result && (
					<p className="text-[12px] text-ps-success mt-1">{result}</p>
				)}
			</div>
			<div className="mt-auto flex flex-wrap gap-2">
				<button
					className="ps-btn"
					onClick={() => setConfirm('missing')}
					disabled={loading || diffQuery.isError}
				>
					{__('Regen Missing', 'pixel-scout')}
				</button>
				<button
					className="ps-btn ps-btn--danger"
					onClick={() => setConfirm('all')}
					disabled={loading || !hasChanges || diffQuery.isError}
					title={
						hasChanges
							? undefined
							: __(
									'No new sizes detected since last regen',
									'pixel-scout'
								)
					}
				>
					{__('Regen All', 'pixel-scout')}
				</button>
				<button
					className="ps-btn"
					onClick={() => setConfirm('dismiss')}
					disabled={loading || !hasChanges || diffQuery.isError}
					title={
						hasChanges
							? __(
									'Acknowledge the size change without rebuilding',
									'pixel-scout'
								)
							: undefined
					}
				>
					{__('Dismiss', 'pixel-scout')}
				</button>
			</div>

			{confirm && (
				<ConfirmModal
					open={true}
					title={
						confirm === 'all'
							? __('Rebuild every subsize?', 'pixel-scout')
							: confirm === 'missing'
								? __('Fill missing subsizes?', 'pixel-scout')
								: __('Dismiss size change notice?', 'pixel-scout')
					}
					message={
						confirm === 'all'
							? __(
									'Rebuilds every registered subsize for every image attachment. Takes a long time on large libraries.',
									'pixel-scout'
								)
							: confirm === 'missing'
								? __(
										'Only generates subsizes that are missing or whose file is absent on disk.',
										'pixel-scout'
									)
								: __(
										'Accepts the current registered sizes as the new baseline without regenerating anything.',
										'pixel-scout'
									)
					}
					confirmText={
						confirm === 'all'
							? __('Rebuild all', 'pixel-scout')
							: confirm === 'missing'
								? __('Fill missing', 'pixel-scout')
								: __('Dismiss', 'pixel-scout')
					}
					danger={confirm === 'all'}
					loading={loading}
					onConfirm={() => run(confirm)}
					onCancel={() => setConfirm(null)}
				/>
			)}
		</div>
	);
}
