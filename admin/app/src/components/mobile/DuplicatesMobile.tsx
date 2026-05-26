import { __, sprintf } from '@wordpress/i18n';
import {
	groupKey,
	useDuplicatesBoard,
} from '../../hooks/use-duplicates-board';
import { formatBytes } from '../../lib/format';
import { IconCheck, IconRefresh, IconTrash } from '../icons';
import MobileHero from './MobileHero';

/**
 * Mobile duplicates screen.
 *
 * Hero hierarchy matches the mobile design ("CLEANUP · N duplicate groups"
 * with a recoverable-bytes hint). Each cluster is a stacked card with a
 * similarity pill, a 2- or 3-up thumbnail row, a "+N more" note for larger
 * groups, and a Delete action. Tapping a thumbnail selects it as the "keep"
 * image; the kept thumb gets full opacity + accent border + a checkmark,
 * the others fade to make the choice unambiguous.
 *
 * View-model state (groups, scan flags, keep selection, bulk delete) is
 * shared with `DuplicatesDesktop` via {@link useDuplicatesBoard}.
 *
 * @return {JSX.Element}
 */
export default function DuplicatesMobile() {
	const {
		groups,
		totalWasteBytes,
		isLoading,
		isScanning,
		isIndexing,
		progress,
		duplicateScanState,
		startScan,
		isStarting,
		resetScan,
		isResetting,
		getKeepId,
		setKeepId,
		deleteOthers,
		isDeleting,
	} = useDuplicatesBoard();

	if (isIndexing) {
		return (
			<div className="px-[18px] pt-5">
				<div className="bg-snopix-bg rounded-[14px] p-5 border border-snopix-border text-center text-snopix-muted text-[13px]">
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
			<MobileHero
				label={__('Cleanup', 'snopix')}
				title={sprintf(
					/* translators: %d: number of duplicate groups */
					__('%d duplicate groups', 'snopix'),
					groups.length
				)}
				subtitle={
					totalWasteBytes > 0
						? sprintf(
								/* translators: %s: bytes formatted as human-readable */
								__('%s recoverable. Tap a card to keep.', 'snopix'),
								formatBytes(totalWasteBytes)
							)
						: __('Tap a card to keep.', 'snopix')
				}
			/>

			<div className="px-[18px] pb-3.5 flex gap-2">
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
				<div className="px-[18px] pb-3.5">
					<div className="bg-snopix-bg rounded-[14px] p-3.5 border border-snopix-border">
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
				<div className="px-[18px] pt-2">
					<div className="bg-snopix-bg rounded-[14px] p-6 border border-snopix-border text-center text-snopix-muted text-[13px]">
						{__('No duplicate clusters found.', 'snopix')}
					</div>
				</div>
			) : (
				<div className="px-[18px] flex flex-col gap-3.5">
					{groups.map((g) => {
						const keepId = getKeepId(g);
						const shown = g.images.slice(0, 3);
						const remaining = g.images.length - shown.length;
						const cols = shown.length === 2 ? 'grid-cols-2' : 'grid-cols-3';
						const matchPercent = Math.round(g.similarity * 100);

						return (
							<div
								key={groupKey(g)}
								className="bg-snopix-bg rounded-[14px] p-3.5 border border-snopix-border"
							>
								<div className="flex items-center justify-between mb-3 gap-2">
									<span className="snopix-pill snopix-pill--accent">
										{sprintf(
											/* translators: %d: match percentage */
											__('%d%% match', 'snopix'),
											matchPercent
										)}
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
												className={`relative aspect-square rounded-[10px] overflow-hidden bg-snopix-surface p-0 cursor-pointer transition-all ${
													kept
														? 'border-2 border-snopix-accent shadow-[0_0_0_3px_rgba(0,113,227,0.15)] opacity-100'
														: 'border border-snopix-border opacity-[0.55]'
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
													<div className="absolute top-1.5 right-1.5 w-[22px] h-[22px] rounded-full bg-snopix-accent text-white grid place-items-center shadow-[0_2px_6px_rgba(0,113,227,0.30)]">
														<IconCheck size={12} />
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
