import { __ } from '@wordpress/i18n';
import { formatBytes } from '../lib/format';
import { IconChevron } from './icons';

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

/**
 * Resolve a translated label for an indexer `error_code`.
 *
 * @param {string|undefined} code error_code value from the /images payload.
 *
 * @return {string} Localized label.
 */
function errorLabel(code: string | undefined): string {
	switch (code) {
		case 'unsupported_mime':
			return __('Unsupported format', 'snopix');
		case 'unfingerprintable':
			return __('Corrupt / unreadable', 'snopix');
		default:
			return __('Failed', 'snopix');
	}
}

interface Props {
	image: ImageData;
	onImageClick: (url: string) => void;
}

/**
 * One row of the `ImageTable`.
 *
 * Row click opens the attachment edit screen in a new tab. Thumbnail click
 * invokes `onImageClick` so the parent can mount the lightbox.
 *
 * @param {Props} props              Component props.
 * @param {ImageData} props.image    Attachment row payload from `/images`.
 * @param {(url: string) => void} props.onImageClick Lightbox open handler.
 *
 * @return {JSX.Element}
 */
export default function ImageRow({ image, onImageClick }: Props) {
	const editUrl = `/wp-admin/post.php?post=${image.attachment_id}&action=edit`;
	const isFailed = !!image.error_code;
	const isIndexed = !isFailed && !!image.phash;
	const pillClass = isFailed
		? 'snopix-pill snopix-pill--failed'
		: isIndexed
			? 'snopix-pill snopix-pill--indexed'
			: 'snopix-pill snopix-pill--pending';
	const label = isFailed
		? errorLabel(image.error_code)
		: isIndexed
			? __('indexed', 'snopix')
			: __('pending', 'snopix');
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
			<td className="pl-6">
				{previewUrl ? (
					<img
						src={previewUrl}
						alt={displayName}
						className="snopix-thumb cursor-zoom-in object-cover"
						onClick={(e) => {
							e.stopPropagation();
							onImageClick(previewUrl);
						}}
					/>
				) : (
					<div className="snopix-thumb flex items-center justify-center text-[10px] text-snopix-muted">
						{__('IMG', 'snopix')}
					</div>
				)}
			</td>
			<td>
				<div className="font-medium">{displayName}</div>
				<div className="snopix-mono text-[11px] text-snopix-muted mt-0.5">
					id · {image.attachment_id}
				</div>
			</td>
			<td>
				<span className={pillClass} title={image.error_code}>
					{label}
				</span>
			</td>
			<td className="snopix-mono text-[12px] text-snopix-muted">
				{formatBytes(image.file_size)}
			</td>
			<td className="text-snopix-muted">{date}</td>
			<td className="pr-6 text-right">
				<button
					className="snopix-btn snopix-btn--ghost snopix-btn--sm"
					aria-label={__('Actions', 'snopix')}
					onClick={(e) => {
						e.stopPropagation();
						window.open(editUrl, '_blank');
					}}
				>
					<IconChevron size={14} />
				</button>
			</td>
		</tr>
	);
}
