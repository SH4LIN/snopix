import { __, sprintf } from '@wordpress/i18n';
import { DuplicateGroup, DuplicateImage } from '../hooks/use-duplicates';
import { formatBytes } from '../lib/format';

interface Props {
	group: DuplicateGroup;
	keepId: number;
	onKeepChange: (id: number) => void;
	selected: boolean;
	onToggleSelect: () => void;
	onDelete: (ids: number[]) => void;
	isDeleting: boolean;
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
	onDelete,
	isDeleting,
}: Props) {
	const toDelete = group.images.filter((img) => img.id !== keepId);

	function handleDelete() {
		onDelete(toDelete.map((img) => img.id));
	}

	return (
		<div
			className={`snopix-card transition-colors ${selected ? 'ring-2 ring-snopix-accent' : ''}`}
		>
			<div className="flex justify-between items-center mb-3">
				<div className="flex items-center gap-2">
					<input
						type="checkbox"
						checked={selected}
						onChange={onToggleSelect}
						className="w-4 h-4 cursor-pointer accent-[var(--snopix-accent,#2271b1)]"
					/>
					<span
						className={`text-xs font-medium px-2 py-0.5 rounded-full ${
							group.match_type === 'exact'
								? 'bg-snopix-accent/10 text-snopix-accent'
								: 'bg-snopix-muted/10 text-snopix-muted'
						}`}
					>
						{group.match_type === 'exact'
							? __('Exact duplicate', 'snopix')
							: __('Similar image', 'snopix')}
					</span>
				</div>

				{toDelete.length > 0 && (
					<button
						className="snopix-btn snopix-btn--danger text-xs"
						onClick={handleDelete}
						disabled={isDeleting}
					>
						{sprintf(
							/* translators: %d: number of images to delete */
							__('Delete %d other(s)', 'snopix'),
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
					? 'border-snopix-accent'
					: 'border-snopix-border hover:border-snopix-muted'
			}`}
			onClick={onKeep}
			title={image.filename}
		>
			<div className="w-full aspect-square overflow-hidden rounded-t-md bg-snopix-surface">
				{image.thumbnail_url ? (
					<img
						src={image.thumbnail_url}
						alt={image.title}
						className="w-full h-full object-cover"
					/>
				) : (
					<div className="w-full h-full flex items-center justify-center text-snopix-muted text-[11px]">
						{__('No preview', 'snopix')}
					</div>
				)}
			</div>

			<div className="p-2">
				{isKeep && (
					<div className="text-[11px] font-semibold text-snopix-accent mb-0.5">
						{__('Keep', 'snopix')}
					</div>
				)}
				<div
					className="text-[11px] text-snopix-text truncate"
					title={image.filename}
				>
					{image.filename || image.title}
				</div>
				<div className="text-[11px] text-snopix-muted mt-0.5">
					{formatBytes(image.file_size)}
				</div>
				{image.width > 0 && (
					<div className="text-[11px] text-snopix-muted">
						{image.width}×{image.height}
					</div>
				)}
			</div>
		</div>
	);
}
