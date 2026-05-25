import { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useImages } from '../hooks/use-images';
import { useIndexStatus } from '../hooks/use-index-status';
import ImageRow from './ImageRow';
import { IconSearch } from './icons';

/**
 * Recently-indexed attachment table card.
 *
 * Header carries a filter input that searches across title and filename.
 * Footer shows total-row context ("Showing N of M rows") and a Prev/Next
 * cursor-stack pagination. Clicking a thumbnail opens a lightbox overlay.
 *
 * @return {JSX.Element}
 */
export default function ImageTable() {
	const [cursors, setCursors] = useState<number[]>([0]);
	const [search, setSearch] = useState('');
	const [lightbox, setLightbox] = useState<string | null>(null);
	const afterId = cursors[cursors.length - 1];
	const { data: images, isLoading } = useImages({ afterId, search });
	const { data: status } = useIndexStatus();

	function goNext() {
		if (!images || images.length === 0) {
			return;
		}
		const next = images[images.length - 1].attachment_id;
		setCursors((prev) => [...prev, next]);
	}

	function goPrev() {
		setCursors((prev) => (prev.length > 1 ? prev.slice(0, -1) : prev));
	}

	useEffect(() => {
		if (!lightbox) {
			return;
		}
		const close = (e: KeyboardEvent) =>
			e.key === 'Escape' && setLightbox(null);
		document.addEventListener('keydown', close);
		return () => document.removeEventListener('keydown', close);
	}, [lightbox]);

	const totalLabel = status
		? sprintf(
				/* translators: 1: indexed count, 2: total count */
				__('Showing %1$d of %2$s rows', 'snopix'),
				images?.length ?? 0,
				status.total.toLocaleString()
			)
		: '';

	return (
		<div className="snopix-card overflow-hidden">
			<div className="px-6 py-[18px] flex items-center justify-between border-b border-snopix-border gap-4 flex-wrap">
				<div>
					<h2 className="text-[17px] font-semibold">
						{__('Recently indexed', 'snopix')}
					</h2>
					<p className="text-[13px] text-snopix-muted mt-1">
						{__('The most recent rows in', 'snopix')}{' '}
						<span className="snopix-mono">wp_snopix_index</span>.
					</p>
				</div>
				<div className="snopix-input-wrap w-[260px]">
					<span className="snopix-input-wrap__icon">
						<IconSearch size={14} />
					</span>
					<input
						className="snopix-input"
						placeholder={__('Filter by filename', 'snopix')}
						value={search}
						onChange={(e) => {
							setSearch(e.target.value);
							setCursors([0]);
						}}
					/>
				</div>
			</div>

			<div className="overflow-x-auto">
				<table className="snopix-table min-w-[640px]">
					<thead>
						<tr>
							<th style={{ width: 96 }}></th>
							<th>{__('File', 'snopix')}</th>
							<th>{__('Status', 'snopix')}</th>
							<th>{__('Size', 'snopix')}</th>
							<th>{__('Indexed', 'snopix')}</th>
							<th style={{ width: 60 }}></th>
						</tr>
					</thead>
					<tbody>
						{isLoading && (
							<tr>
								<td
									colSpan={6}
									className="text-center text-snopix-muted py-6"
								>
									{__('Loading…', 'snopix')}
								</td>
							</tr>
						)}
						{images?.map((img) => (
							<ImageRow
								key={img.attachment_id}
								image={img}
								onImageClick={setLightbox}
							/>
						))}
						{!isLoading && images?.length === 0 && (
							<tr>
								<td
									colSpan={6}
									className="text-center text-snopix-muted py-6"
								>
									{__('No images found', 'snopix')}
								</td>
							</tr>
						)}
					</tbody>
				</table>
			</div>

			<div className="px-6 py-3.5 flex items-center justify-between border-t border-snopix-border">
				<div className="text-[13px] text-snopix-muted">{totalLabel}</div>
				<div className="flex gap-1.5">
					<button
						className="snopix-btn snopix-btn--neutral snopix-btn--sm"
						onClick={goPrev}
						disabled={cursors.length <= 1}
					>
						{__('Prev', 'snopix')}
					</button>
					<button
						className="snopix-btn snopix-btn--neutral snopix-btn--sm"
						onClick={goNext}
						disabled={!images || images.length < 25}
					>
						{__('Next', 'snopix')}
					</button>
				</div>
			</div>

			{lightbox && (
				<div
					className="fixed inset-0 z-[99999] bg-black/80 flex items-center justify-center"
					onClick={() => setLightbox(null)}
				>
					<button
						className="absolute top-4 right-4 text-white text-2xl leading-none bg-black/40 rounded-full w-9 h-9 flex items-center justify-center hover:bg-black/70"
						onClick={() => setLightbox(null)}
					>
						&times;
					</button>
					<img
						src={lightbox}
						className="max-w-[90vw] max-h-[90vh] object-contain rounded-[8px] shadow-xl"
						onClick={(e) => e.stopPropagation()}
					/>
				</div>
			)}
		</div>
	);
}
