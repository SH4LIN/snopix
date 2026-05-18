import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useImages } from '../hooks/use-images';
import ImageRow from './ImageRow';

export default function ImageTable() {
	const [page, setPage] = useState(1);
	const [search, setSearch] = useState('');
	const [lightbox, setLightbox] = useState<string | null>(null);
	const { data: images, isLoading } = useImages({ page, search });

	useEffect(() => {
		if (!lightbox) {
			return;
		}

		const close = (e: KeyboardEvent) =>
			e.key === 'Escape' && setLightbox(null);
		document.addEventListener('keydown', close);
		return () => document.removeEventListener('keydown', close);
	}, [lightbox]);

	return (
		<div className="ps-card">
			<div className="mb-3">
				<input
					className="ps-input w-full"
					placeholder={__('Search images…', 'pixel-scout')}
					value={search}
					onChange={(e) => {
						setSearch(e.target.value);
						setPage(1);
					}}
				/>
			</div>

			<div className="overflow-x-auto">
				<table className="ps-table w-full min-w-[560px]">
					<thead>
						<tr>
							<th></th>
							<th>{__('File Name', 'pixel-scout')}</th>
							<th>{__('Dimensions', 'pixel-scout')}</th>
							<th>{__('Size', 'pixel-scout')}</th>
							<th>{__('Indexed At', 'pixel-scout')}</th>
							<th>{__('Status', 'pixel-scout')}</th>
						</tr>
					</thead>
					<tbody>
						{isLoading && (
							<tr>
								<td
									colSpan={6}
									className="text-center text-ps-muted py-6"
								>
									{__('Loading…', 'pixel-scout')}
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
									className="text-center text-ps-muted py-6"
								>
									{__('No images found', 'pixel-scout')}
								</td>
							</tr>
						)}
					</tbody>
				</table>
			</div>

			<div className="flex justify-between items-center mt-3 text-[13px] text-ps-muted">
				<span>
					{__('Page', 'pixel-scout')} {page}
				</span>
				<div className="flex gap-2">
					<button
						onClick={() => setPage((p) => Math.max(1, p - 1))}
						disabled={page === 1}
						className="ps-btn py-1 px-2.5 text-[13px]"
					>
						&larr;
					</button>
					<button
						onClick={() => setPage((p) => p + 1)}
						disabled={!images || images.length < 25}
						className="ps-btn py-1 px-2.5 text-[13px]"
					>
						&rarr;
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
