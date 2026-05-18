import { __ } from '@wordpress/i18n';

interface ImageData {
	attachment_id: number;
	mime_type: string;
	file_size: number;
	width: number;
	height: number;
	indexed_at: string;
	phash: string;
	title?: string;
	filename?: string;
	thumbnail_url?: string;
	full_url?: string;
}

interface Props {
	image: ImageData;
	onImageClick: (url: string) => void;
}

function formatBytes(bytes: number): string {
	if (bytes < 1024) return `${bytes} B`;
	if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
	return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function ImageRow({ image, onImageClick }: Props) {
	const editUrl = `/wp-admin/post.php?post=${image.attachment_id}&action=edit`;
	const isIndexed = !!image.phash;
	const pillClass = isIndexed
		? 'ps-pill ps-pill--indexed'
		: 'ps-pill ps-pill--pending';
	const label = isIndexed
		? __('Indexed', 'pixel-scout')
		: __('Pending', 'pixel-scout');
	const date = image.indexed_at
		? new Date(image.indexed_at).toLocaleDateString()
		: '—';
	const displayName =
		image.filename || image.title || `ID ${image.attachment_id}`;
	const previewUrl = image.full_url || image.thumbnail_url || '';

	return (
		<tr
			onClick={() => window.open(editUrl, '_blank')}
			className="cursor-pointer"
		>
			<td className="w-14 min-w-[3.5rem]">
				{previewUrl ? (
					<img
						src={previewUrl}
						alt={displayName}
						className="w-12 h-12 object-contain rounded-[6px] block cursor-zoom-in bg-ps-surface"
						onClick={(e) => {
							e.stopPropagation();
							onImageClick(previewUrl);
						}}
					/>
				) : (
					<div className="w-12 h-12 bg-ps-surface rounded-[6px] flex items-center justify-center text-[10px] text-ps-muted">
						{__('IMG', 'pixel-scout')}
					</div>
				)}
			</td>
			<td className="text-[13px]">
				{displayName}
				<br />
				<span className="text-ps-muted text-[11px]">
					{image.mime_type}
				</span>
			</td>
			<td className="text-[13px]">
				{image.width} &times; {image.height}
			</td>
			<td className="text-[13px]">{formatBytes(image.file_size)}</td>
			<td className="text-[13px]">{date}</td>
			<td>
				<span className={pillClass}>{label}</span>
			</td>
		</tr>
	);
}
