import { __ } from '@wordpress/i18n';
import { formatBytes } from '../lib/format';

interface ImageData {
	attachment_id: number;
	mime_type: string;
	file_size: number;
	width: number;
	height: number;
	indexed_at: string;
	phash: string;
	error_code?: string;
	title?: string;
	filename?: string;
	thumbnail_url?: string;
	full_url?: string;
}

const ERROR_LABELS: Record<string, string> = {
	unsupported_mime: 'Unsupported format',
	unfingerprintable: 'Corrupt / unreadable',
};

interface Props {
	image: ImageData;
	onImageClick: (url: string) => void;
}

/**
 * Render one row of the `ImageTable` grid for a single attachment.
 *
 * Clicking the row opens the attachment's WP edit screen in a new tab; clicking
 * the thumbnail invokes `onImageClick` so the parent can mount its lightbox.
 *
 * @param {Props} props              Component props.
 * @param {ImageData} props.image    Attachment row payload from the `/images` REST endpoint.
 * @param {(url: string) => void} props.onImageClick Callback fired with the full-size URL when the thumbnail is clicked.
 *
 * @return {JSX.Element}
 */
export default function ImageRow({ image, onImageClick }: Props) {
	const editUrl = `/wp-admin/post.php?post=${image.attachment_id}&action=edit`;
	const isFailed = !!image.error_code;
	const isIndexed = !isFailed && !!image.phash;
	const pillClass = isFailed
		? 'ps-pill ps-pill--failed'
		: isIndexed
			? 'ps-pill ps-pill--indexed'
			: 'ps-pill ps-pill--pending';
	const label = isFailed
		? __(ERROR_LABELS[image.error_code ?? ''] ?? 'Failed', 'pixel-scout')
		: isIndexed
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
