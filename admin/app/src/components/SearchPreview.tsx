import { useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { apiFetch } from '../lib/api';
import { formatBytes } from '../lib/format';
import { IconUpload, IconX } from './icons';

interface SearchResultItem {
	id: number;
	url: string;
	thumbnail: string;
	title: string;
	score: number;
	attachment_url: string;
}

type Phase = 'idle' | 'scanning' | 'results';

interface ProbePreview {
	url: string;
	name: string;
	size: number;
}

/**
 * Reverse-image search panel rendered in a full-width Dashboard card.
 *
 * Walks through three phases:
 *   - idle    : drop-zone awaiting an image
 *   - scanning: probe preview + indeterminate progress bar
 *   - results : probe preview + ranked match list
 *
 * The probe is uploaded to `POST /wp-json/snopix/v1/search` and never persisted
 * beyond the response (server cleans up the temporary attachment).
 *
 * @return {JSX.Element}
 */
export default function SearchPreview() {
	const inputRef = useRef<HTMLInputElement>(null);
	const [phase, setPhase] = useState<Phase>('idle');
	const [over, setOver] = useState(false);
	const [probe, setProbe] = useState<ProbePreview | null>(null);
	const [results, setResults] = useState<SearchResultItem[]>([]);
	const [error, setError] = useState<string | null>(null);

	async function handleFile(file: File) {
		const url = URL.createObjectURL(file);
		setProbe({ url, name: file.name, size: file.size });
		setPhase('scanning');
		setError(null);
		setResults([]);

		const fd = new FormData();
		fd.append('file', file);
		try {
			const res = await apiFetch<SearchResultItem[]>({
				path: 'snopix/v1/search',
				method: 'POST',
				formData: fd,
			});
			setResults(res);
			setPhase('results');
		} catch {
			setError(
				__('Something went wrong. Try a different image.', 'snopix')
			);
			setPhase('results');
		}
	}

	function reset() {
		if (probe) {
			URL.revokeObjectURL(probe.url);
		}
		setProbe(null);
		setResults([]);
		setError(null);
		setPhase('idle');
	}

	return (
		<div className="snopix-card snopix-card--pad mb-7">
			<div className="flex items-end justify-between mb-4 gap-4">
				<div>
					<h2 className="text-[17px] font-semibold mb-1">
						{__('Search by image', 'snopix')}
					</h2>
					<p className="text-[13px] text-snopix-muted">
						{__(
							'Drop or upload an image — Snopix returns the most visually similar attachments in your library.',
							'snopix'
						)}
					</p>
				</div>
				{phase === 'results' && (
					<button
						className="snopix-btn snopix-btn--ghost snopix-btn--sm"
						onClick={reset}
					>
						<IconX size={14} /> {__('New search', 'snopix')}
					</button>
				)}
			</div>

			<div
				className={`grid gap-6 items-start ${
					phase === 'idle'
						? 'grid-cols-1'
						: 'grid-cols-1 md:grid-cols-[300px_1fr]'
				}`}
			>
				{phase === 'idle' ? (
					<div
						className={`snopix-drop-zone ${over ? 'snopix-drop-zone--over' : ''}`}
						onClick={() => inputRef.current?.click()}
						onDragOver={(e) => {
							e.preventDefault();
							setOver(true);
						}}
						onDragLeave={() => setOver(false)}
						onDrop={(e) => {
							e.preventDefault();
							setOver(false);
							const f = e.dataTransfer.files[0];
							if (f) {
								handleFile(f);
							}
						}}
					>
						<div className="text-snopix-muted">
							<IconUpload size={28} />
						</div>
						<div className="snopix-drop-zone__label">
							{__('Drop an image to search', 'snopix')}
						</div>
						<div className="snopix-drop-zone__hint">
							{__(
								'JPEG · PNG · WebP · GIF · BMP',
								'snopix'
							)}
						</div>
						<button
							className="snopix-btn snopix-btn--ghost snopix-btn--sm"
							onClick={(e) => {
								e.stopPropagation();
								inputRef.current?.click();
							}}
						>
							{__('Or browse files', 'snopix')}
						</button>
						<input
							ref={inputRef}
							type="file"
							accept="image/*"
							className="hidden"
							onChange={(e) => {
								const f = e.target.files?.[0];
								if (f) {
									handleFile(f);
								}
							}}
						/>
					</div>
				) : (
					probe && (
						<div>
							<div className="w-full aspect-square rounded-[var(--snopix-radius-card)] overflow-hidden border border-snopix-border">
								<img
									src={probe.url}
									alt={probe.name}
									className="w-full h-full object-cover block"
								/>
							</div>
							<div className="mt-3 text-[13px] font-medium text-snopix-text truncate">
								{probe.name}
							</div>
							<div className="snopix-mono text-[11px] text-snopix-muted mt-1">
								probe · {formatBytes(probe.size)}
							</div>
							{phase === 'scanning' && (
								<div className="mt-3.5">
									<div className="snopix-progress snopix-progress--indeterminate">
										<div className="snopix-progress__fill" />
									</div>
									<div className="snopix-mono text-[12px] text-snopix-muted mt-1.5">
										{__(
											'fingerprinting · scoring against indexed rows',
											'snopix'
										)}
									</div>
								</div>
							)}
						</div>
					)
				)}

				{phase !== 'idle' && (
					<div>
						<div className="flex items-center justify-between mb-3">
							<div className="text-[13px] font-semibold uppercase tracking-[0.04em]">
								{phase === 'scanning'
									? __('Searching…', 'snopix')
									: sprintf(
											/* translators: %d: match count */
											__('%d matches', 'snopix'),
											results.length
										)}
							</div>
							{phase === 'results' && results.length > 0 && (
								<div className="snopix-mono text-[11px] text-snopix-muted">
									pHash 0.40 · colour 0.35 · edges 0.25
								</div>
							)}
						</div>

						{phase === 'scanning' && (
							<div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
								{[0, 1, 2, 3].map((i) => (
									<div
										key={i}
										className="snopix-match opacity-60"
									>
										<div className="w-14 h-14 rounded-lg bg-snopix-border" />
										<div className="flex-1 flex flex-col gap-1.5">
											<div className="h-2.5 w-4/5 bg-snopix-border rounded" />
											<div className="h-1.5 bg-snopix-border rounded" />
										</div>
									</div>
								))}
							</div>
						)}

						{phase === 'results' && error && (
							<div className="text-[13px] text-snopix-danger">
								{error}
							</div>
						)}

						{phase === 'results' &&
							!error &&
							results.length === 0 && (
								<div className="text-[13px] text-snopix-muted">
									{__(
										'No similar images found. Try a different image.',
										'snopix'
									)}
								</div>
							)}

						{phase === 'results' && results.length > 0 && (
							<div className="flex flex-col gap-2">
								{results.slice(0, 8).map((m, i) => (
									<div key={m.id} className="snopix-match">
										<div className="w-14 h-14 rounded-lg overflow-hidden shrink-0 bg-snopix-surface">
											<img
												src={m.thumbnail || m.url}
												alt={m.title}
												className="w-full h-full object-cover block"
											/>
										</div>
										<div className="flex-1 min-w-0">
											<div className="flex items-center justify-between gap-3">
												<div className="font-medium text-sm truncate">
													{m.title}
												</div>
												<div
													className={`snopix-mono text-[13px] font-semibold ${
														i === 0
															? 'text-snopix-accent-deep'
															: 'text-snopix-text'
													}`}
												>
													{m.score.toFixed(3)}
												</div>
											</div>
											<div className="flex items-center gap-2.5 mt-1.5">
												<div className="snopix-score-bar">
													<div
														className="snopix-score-bar__fill"
														style={{
															width: `${Math.round(m.score * 100)}%`,
														}}
													/>
												</div>
											</div>
										</div>
										<a
											href={m.attachment_url}
											target="_blank"
											rel="noopener noreferrer"
											className="snopix-btn snopix-btn--ghost snopix-btn--sm"
										>
											{__('Open', 'snopix')}
										</a>
									</div>
								))}
							</div>
						)}
					</div>
				)}
			</div>
		</div>
	);
}
