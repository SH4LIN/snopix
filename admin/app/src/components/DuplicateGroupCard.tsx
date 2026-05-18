import { __, sprintf } from '@wordpress/i18n';
import {
	DuplicateGroup,
	DuplicateImage,
	useDeleteAttachment,
} from '../hooks/use-duplicates';

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

export default function DuplicateGroupCard({
	group,
	keepId,
	onKeepChange,
	selected,
	onToggleSelect,
}: Props) {
	const { mutateAsync: deleteAttachment, isPending } = useDeleteAttachment();

	const toDelete = group.images.filter((img) => img.id !== keepId);

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
