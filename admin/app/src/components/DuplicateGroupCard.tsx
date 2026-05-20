import { __, sprintf } from '@wordpress/i18n';
import {
	DuplicateGroup,
	DuplicateImage,
	useDeleteAttachment,
} from '../hooks/use-duplicates';

/**
 * Render a byte count using 1024-based units (B / KB / MB), 1-decimal precision.
 *
 * @param {number} bytes Raw byte count from the indexer.
 *
 * @return {string} Human-friendly string such as `"512 B"`, `"3.4 KB"`, `"12.1 MB"`.
 */
function formatBytes(bytes: number): string {
	if (bytes < 1024) return `${bytes} B`;
	if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
	return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

interface Props {
	group: DuplicateGroup;
	keepId: number;
	onKeepChange: (id: number) => void;
	selected: boolean;
	onToggleSelect: () => void;
}

/**
 * One card per duplicate group: header pill + bulk-delete CTA + horizontal
 * carousel of {@link ImageCard} tiles. The user clicks any tile to mark it the
 * "keep" image; the bulk-delete button removes the others.
 *
 * @param {Props}                       props                Component props.
 * @param {DuplicateGroup}              props.group          Group payload from `/duplicates`.
 * @param {number}                      props.keepId         Currently-selected keep attachment id.
 * @param {(id: number) => void}        props.onKeepChange   Fired when a different tile is chosen as keep.
 * @param {boolean}                     props.selected       Whether this group is in the bulk-delete selection.
 * @param {() => void}                  props.onToggleSelect Toggle selection from the bulk row.
 *
 * @return {JSX.Element}
 */
export default function DuplicateGroupCard({
	group,
	keepId,
	onKeepChange,
	selected,
	onToggleSelect,
}: Props) {
	const { mutateAsync: deleteAttachment, isPending } = useDeleteAttachment();

	const toDelete = group.images.filter((img) => img.id !== keepId);

	/**
	 * Delete every non-keep attachment in this group, one at a time. Errors
	 * from individual deletions are swallowed because the hook re-fetches the
	 * duplicate list and any residue is shown on re-render.
	 *
	 * @return {Promise<void>}
	 */
	async function handleDelete() {
		try {
			for (const img of toDelete) {
				await deleteAttachment(img.id);
			}
		} catch {
			// hook invalidates query on partial success
		}
	}

	return (
		<div
			className={`ps-card transition-colors ${selected ? 'ring-2 ring-ps-accent' : ''}`}
		>
			<div className="flex justify-between items-center mb-3">
				<div className="flex items-center gap-2">
					<input
						type="checkbox"
						checked={selected}
						onChange={onToggleSelect}
						className="w-4 h-4 cursor-pointer accent-[var(--ps-accent,#2271b1)]"
					/>
					<span
						className={`text-xs font-medium px-2 py-0.5 rounded-full ${
							group.match_type === 'exact'
								? 'bg-ps-accent/10 text-ps-accent'
								: 'bg-ps-muted/10 text-ps-muted'
						}`}
					>
						{group.match_type === 'exact'
							? __('Exact duplicate', 'pixel-scout')
							: __('Similar image', 'pixel-scout')}
					</span>
				</div>

				{toDelete.length > 0 && (
					<button
						className="ps-btn bg-ps-danger border-ps-danger text-xs"
						onClick={handleDelete}
						disabled={isPending}
					>
						{sprintf(
							/* translators: %d: number of images to delete */
							__('Delete %d other(s)', 'pixel-scout'),
							toDelete.length
						)}
					</button>
				)}
			</div>

			<div className="flex gap-3 overflow-x-auto pb-1">
				{group.images.map((img) => (
					<ImageCard
						key={img.id}
						image={img}
						isKeep={img.id === keepId}
						onKeep={() => onKeepChange(img.id)}
					/>
				))}
			</div>
		</div>
	);
}

interface ImageCardProps {
	image: DuplicateImage;
	isKeep: boolean;
	onKeep: () => void;
}

/**
 * Single image tile inside a duplicate group's carousel.
 *
 * Clicking the tile promotes it to the "Keep" image for the parent group. The
 * selected tile is outlined in the accent colour and labelled "Keep".
 *
 * @param {ImageCardProps} props        Component props.
 * @param {DuplicateImage} props.image  Attachment metadata for the tile.
 * @param {boolean}        props.isKeep Whether this tile is the current keep choice.
 * @param {() => void}     props.onKeep Fired when the tile is clicked.
 *
 * @return {JSX.Element}
 */
function ImageCard({ image, isKeep, onKeep }: ImageCardProps) {
	return (
		<div
			className={`flex-shrink-0 w-[140px] rounded-lg border-2 cursor-pointer transition-colors ${
				isKeep
					? 'border-ps-accent'
					: 'border-ps-border hover:border-ps-muted'
			}`}
			onClick={onKeep}
			title={image.filename}
		>
			<div className="w-full aspect-square overflow-hidden rounded-t-md bg-ps-surface">
				{image.thumbnail_url ? (
					<img
						src={image.thumbnail_url}
						alt={image.title}
						className="w-full h-full object-cover"
					/>
				) : (
					<div className="w-full h-full flex items-center justify-center text-ps-muted text-[11px]">
						{__('No preview', 'pixel-scout')}
					</div>
				)}
			</div>

			<div className="p-2">
				{isKeep && (
					<div className="text-[11px] font-semibold text-ps-accent mb-0.5">
						{__('Keep', 'pixel-scout')}
					</div>
				)}
				<div
					className="text-[11px] text-ps-text truncate"
					title={image.filename}
				>
					{image.filename || image.title}
				</div>
				<div className="text-[11px] text-ps-muted mt-0.5">
					{formatBytes(image.file_size)}
				</div>
				{image.width > 0 && (
					<div className="text-[11px] text-ps-muted">
						{image.width}×{image.height}
					</div>
				)}
			</div>
		</div>
	);
}
