import { __, sprintf } from '@wordpress/i18n';
import { DuplicateGroup, DuplicateImage } from '../hooks/use-duplicates';
import { formatBytes } from '../lib/format';
import { IconCheck, IconTrash } from './icons';

interface Props {
	group: DuplicateGroup;
	keepId: number;
	onKeepChange: (id: number) => void;
	onResolve: () => void;
	isDeleting: boolean;
}

/**
 * One card per duplicate group: header (count, match pill, savings), bulk
 * delete CTA, and a grid of {@link ImageCard} tiles. Clicking a tile makes it
 * the "keep" image; the resolve button drops everything else in the group.
 *
 * @param {Props} props Component props (see field-level JSDoc).
 *
 * @return {JSX.Element}
 */
export default function DuplicateGroupCard({
	group,
	keepId,
	onKeepChange,
	onResolve,
	isDeleting,
}: Props) {
	const keptItem = group.images.find((i) => i.id === keepId);
	const dropCount = group.images.length - 1;

	return (
		<div className="snopix-card p-5">
			<div className="flex items-center justify-between mb-3.5 gap-3 flex-wrap">
				<div className="flex items-center gap-3 flex-wrap">
					<div className="text-[13px] font-semibold">
						{sprintf(
							/* translators: %d: image count */
							__('Group · %d attachments', 'snopix'),
							group.images.length
						)}
					</div>
					<span className="snopix-pill snopix-pill--accent">
						{(group.similarity * 100).toFixed(1)}%{' '}
						{__('match', 'snopix')}
					</span>
					<span className="text-[12px] text-snopix-muted">
						{__('Saves', 'snopix')}{' '}
						<strong>{formatBytes(group.wasted_bytes)}</strong>
						{keptItem && (
							<>
								{' '}
								· {__('keep', 'snopix')}{' '}
								<span className="snopix-mono text-snopix-text">
									{keptItem.filename || keptItem.title}
								</span>
							</>
						)}
					</span>
				</div>
				<button
					className="snopix-btn snopix-btn--danger snopix-btn--sm"
					onClick={onResolve}
					disabled={isDeleting || dropCount === 0}
				>
					<IconTrash size={13} />{' '}
					{sprintf(
						/* translators: %d: drop count */
						__('Delete %d others', 'snopix'),
						dropCount
					)}
				</button>
			</div>

			<div className="grid gap-3 grid-cols-[repeat(auto-fill,minmax(160px,1fr))]">
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
		<button
			className="snopix-dup-tile"
			data-kept={isKeep ? 'true' : 'false'}
			onClick={onKeep}
			aria-label={`Keep ${image.filename || image.title}`}
		>
			<div className="aspect-square overflow-hidden bg-snopix-surface">
				{image.thumbnail_url ? (
					<img
						src={image.thumbnail_url}
						alt={image.title}
						className="w-full h-full object-cover block"
					/>
				) : (
					<div className="w-full h-full flex items-center justify-center text-[11px] text-snopix-muted">
						{__('No preview', 'snopix')}
					</div>
				)}
			</div>
			<div className="snopix-dup-tile__check">
				{isKeep ? <IconCheck size={12} /> : null}
			</div>
			<div className="snopix-dup-tile__meta">
				<span className="overflow-hidden text-ellipsis whitespace-nowrap max-w-[100px]">
					{image.filename || image.title}
				</span>
				<span className="snopix-mono">{formatBytes(image.file_size)}</span>
			</div>
			{isKeep && (
				<div className="absolute top-2 left-2">
					<span
						className="snopix-pill text-white"
						style={{
							background: 'var(--snopix-accent)',
							boxShadow: '0 2px 6px rgba(0,113,227,0.30)',
						}}
					>
						{__('KEEP', 'snopix')}
					</span>
				</div>
			)}
		</button>
	);
}
