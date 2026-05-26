import { __, sprintf } from '@wordpress/i18n';
import { useIndexStatus } from '../../hooks/use-index-status';
import { useIndexingProgress } from '../../hooks/use-reindex';
import { useImages } from '../../hooks/use-images';
import { useStore } from '../../store/use-store';
import { formatBytes } from '../../lib/format';
import { IconRefresh } from '../icons';
import SearchPreviewMobile from './SearchPreviewMobile';

// The /images endpoint returns enriched fields the hook's interface omits
// (title, filename, thumbnail_url, etc.). Mirror the wider shape locally so
// we can render thumbnail list cards without touching the shared hook.
interface IndexedImage {
	attachment_id: number;
	mime_type: string;
	file_size: number;
	indexed_at: string;
	phash: string;
	error_code?: string;
	title?: string;
	filename?: string;
	thumbnail_url?: string;
	full_url?: string;
}

/**
 * Mobile dashboard screen.
 *
 * Single-column reflow tuned to match the mobile design:
 *   1. Hero with "LIBRARY · X indexed" hierarchy.
 *   2. Reverse-image search card (dashed border, accent icon, full-width CTA).
 *   3. 2×2 grid of stat tiles (Indexed / Pending / Failed / Total).
 *   4. In-flight progress card when an index job is running.
 *   5. Recently-indexed list with a "See all" link pointing at the WP media
 *      library — re-indexing lives in the header upload button and feature
 *      notifications live in the header bell, so neither is duplicated here.
 *
 * @return {JSX.Element}
 */
export default function DashboardMobile() {
	const { data: status } = useIndexStatus();
	const { indexingState } = useStore();
	const progress = useIndexingProgress();
	const { data: rawImages, isLoading: imagesLoading } = useImages({
		afterId: 0,
		search: '',
	});
	const recentImages = (rawImages ?? []) as unknown as IndexedImage[];

	const total = status?.total ?? 0;
	const indexed = status?.indexed ?? 0;
	const pending = status?.pending ?? 0;
	const failed = status?.failed ?? 0;

	const isRunning = indexingState === 'running' || indexingState === 'stalled';
	const progressPct =
		progress && progress.total > 0
			? Math.min(100, Math.round((progress.done / progress.total) * 100))
			: 0;

	const tiles = [
		{ label: __('Indexed', 'snopix'), value: indexed, accent: 'text-snopix-text' },
		{ label: __('Pending', 'snopix'), value: pending, accent: 'text-snopix-warning' },
		{ label: __('Failed', 'snopix'), value: failed, accent: 'text-snopix-danger' },
		{ label: __('Total', 'snopix'), value: total, accent: 'text-snopix-text' },
	];

	return (
		<div>
			<div className="px-[18px] pt-5 pb-3.5">
				<div className="text-[12px] font-medium text-snopix-muted uppercase tracking-[0.05em] mb-1">
					{__('Library', 'snopix')}
				</div>
				<div className="text-[26px] font-semibold tracking-[-0.015em] leading-[1.1]">
					{sprintf(
						/* translators: %d: number of indexed images */
						__('%d indexed', 'snopix'),
						indexed
					)}
				</div>
				<div className="text-[13px] text-snopix-muted mt-1">
					{total > 0
						? sprintf(
								/* translators: %d: total images in library */
								__('%d images in your library', 'snopix'),
								total
							)
						: __('Reverse-image search across your indexed media.', 'snopix')}
				</div>
			</div>

			<div className="px-[18px] pb-3.5">
				<SearchPreviewMobile />
			</div>

			<div className="grid grid-cols-2 gap-2.5 px-[18px]">
				{tiles.map((tile) => (
					<div
						key={tile.label}
						className="bg-snopix-bg rounded-[14px] p-3.5 border border-snopix-border"
					>
						<div className="text-[10px] font-medium text-snopix-muted uppercase tracking-[0.04em]">
							{tile.label}
						</div>
						<div
							className={`text-[22px] font-semibold tracking-[-0.015em] mt-1 ${tile.accent}`}
						>
							{tile.value.toLocaleString()}
						</div>
					</div>
				))}
			</div>

			{isRunning && progress && (
				<div className="px-[18px] pt-4">
					<div className="bg-snopix-bg rounded-[14px] p-3.5 border border-snopix-border">
						<div className="flex items-center gap-3 mb-3">
							<div className="w-9 h-9 rounded-input bg-snopix-accent-soft text-snopix-accent grid place-items-center">
								<IconRefresh size={18} className="animate-snopix-spin" />
							</div>
							<div className="min-w-0 flex-1">
								<div className="text-[14px] font-semibold">
									{indexingState === 'stalled'
										? __('Indexing stalled', 'snopix')
										: __('Indexing in progress', 'snopix')}
								</div>
								<div className="text-[11px] text-snopix-muted font-mono mt-0.5">
									{progress.done.toLocaleString()} /{' '}
									{progress.total.toLocaleString()}
								</div>
							</div>
						</div>
						<div className="h-1 bg-snopix-border rounded-full overflow-hidden">
							<div
								className="h-full bg-snopix-accent transition-[width] duration-500"
								style={{ width: `${progressPct}%` }}
							/>
						</div>
					</div>
				</div>
			)}

			<div className="px-[18px] pt-[18px] pb-2 flex items-baseline justify-between">
				<div className="text-[13px] font-semibold text-snopix-text">
					{__('Recently indexed', 'snopix')}
				</div>
				<a
					href="/wp-admin/upload.php"
					className="text-[12px] text-snopix-accent"
				>
					{__('See all', 'snopix')}
				</a>
			</div>

			<div className="px-[18px] pb-3 flex flex-col gap-2">
				{imagesLoading && (
					<div className="text-[12px] text-snopix-muted text-center py-3">
						{__('Loading…', 'snopix')}
					</div>
				)}
				{!imagesLoading && recentImages.length === 0 && pending === 0 && failed === 0 && (
					<div className="text-[12px] text-snopix-muted text-center py-3">
						{__('No indexed images yet.', 'snopix')}
					</div>
				)}
				{recentImages.slice(0, 8).map((img) => {
					const displayName =
						img.filename || img.title || `ID ${img.attachment_id}`;
					const previewUrl = img.thumbnail_url || img.full_url || '';
					const date = img.indexed_at
						? new Date(img.indexed_at).toLocaleDateString()
						: '';
					const failedRow = !!img.error_code;
					const pillClass = failedRow
						? 'snopix-pill snopix-pill--failed'
						: img.phash
							? 'snopix-pill snopix-pill--indexed'
							: 'snopix-pill snopix-pill--pending';
					const pillLabel = failedRow
						? __('failed', 'snopix')
						: img.phash
							? __('indexed', 'snopix')
							: __('pending', 'snopix');
					const editUrl = `/wp-admin/post.php?post=${img.attachment_id}&action=edit`;

					return (
						<a
							key={img.attachment_id}
							href={editUrl}
							target="_blank"
							rel="noopener noreferrer"
							className="flex items-center gap-3 bg-snopix-bg rounded-[12px] p-2.5 border border-snopix-border"
						>
							<div className="w-11 h-11 rounded-[8px] overflow-hidden bg-snopix-surface shrink-0 grid place-items-center">
								{previewUrl ? (
									<img
										src={previewUrl}
										alt={displayName}
										loading="lazy"
										className="w-full h-full object-cover"
									/>
								) : (
									<span className="text-[10px] text-snopix-muted">IMG</span>
								)}
							</div>
							<div className="flex-1 min-w-0">
								<div className="text-[13px] font-medium overflow-hidden text-ellipsis whitespace-nowrap">
									{displayName}
								</div>
								<div className="text-[11px] text-snopix-muted mt-0.5 flex items-center gap-2">
									{date && <span>{date}</span>}
									{date && <span aria-hidden="true">·</span>}
									<span className="font-mono">
										{formatBytes(img.file_size)}
									</span>
								</div>
							</div>
							<span className={pillClass} title={img.error_code}>
								{pillLabel}
							</span>
						</a>
					);
				})}
			</div>
		</div>
	);
}
