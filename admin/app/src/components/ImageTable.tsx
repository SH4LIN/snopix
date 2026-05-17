import { useState } from 'react'
import { __ } from '@wordpress/i18n'
import { useImages } from '../hooks/use-images'
import ImageRow from './ImageRow'

export default function ImageTable() {
	const [page, setPage] = useState(1)
	const [search, setSearch] = useState('')
	const { data: images, isLoading } = useImages({ page, search })

	return (
		<div className="ps-card">
			<div className="mb-3">
				<input
					className="ps-input w-full"
					placeholder={__( 'Search images…', 'pixel-scout' )}
					value={search}
					onChange={(e) => {
						setSearch(e.target.value)
						setPage(1)
					}}
				/>
			</div>

			<table className="ps-table w-full">
				<thead>
					<tr>
						<th></th>
						<th>{__( 'File Name', 'pixel-scout' )}</th>
						<th>{__( 'Dimensions', 'pixel-scout' )}</th>
						<th>{__( 'Size', 'pixel-scout' )}</th>
						<th>{__( 'Indexed At', 'pixel-scout' )}</th>
						<th>{__( 'Status', 'pixel-scout' )}</th>
					</tr>
				</thead>
				<tbody>
					{isLoading && (
						<tr>
							<td colSpan={6} className="text-center text-ps-muted py-6">
								{__( 'Loading…', 'pixel-scout' )}
							</td>
						</tr>
					)}
					{images?.map((img) => (
						<ImageRow key={img.attachment_id} image={img} />
					))}
					{!isLoading && images?.length === 0 && (
						<tr>
							<td colSpan={6} className="text-center text-ps-muted py-6">
								{__( 'No images found', 'pixel-scout' )}
							</td>
						</tr>
					)}
				</tbody>
			</table>

			<div className="flex justify-between items-center mt-3 text-[13px] text-ps-muted">
				<span>{__( 'Page', 'pixel-scout' )} {page}</span>
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
		</div>
	)
}
