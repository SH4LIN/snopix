import { useRef } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { formatBytes } from '../../lib/format';
import { useImageSearch } from '../../hooks/use-image-search';
import { IconUpload, IconX } from '../icons';

/**
 * Mobile reverse-image search card.
 *
 * Slim, tap-first variant of `SearchPreview` shaped to match the mobile
 * design: a single dashed-border card with a circular accent icon, the
 * "Search by image" headline, a "Tap to choose a photo" hint, and a
 * full-width "Choose photo" button. On selection the card swaps to a probe
 * preview + result list without leaving the dashboard. Talks to the same
 * `POST /wp-json/snopix/v1/search` endpoint as the desktop variant via
 * {@link useImageSearch}.
 *
 * @return {JSX.Element}
 */
export default function SearchPreviewMobile(): JSX.Element {
	const inputRef = useRef<HTMLInputElement>(null);
	const { phase, probe, results, error, handleFile, reset } = useImageSearch();

	if (phase === 'idle') {
		return (
			<div data-tour="search" className="snopix-mobile-search">
				<div className="snopix-mobile-search__icon" aria-hidden="true">
					<IconUpload size={20} />
				</div>
				<div className="snopix-mobile-search__title">
					{__('Search by image', 'snopix')}
				</div>
				<div className="snopix-mobile-search__hint">
					{__('Tap to choose a photo from your library', 'snopix')}
				</div>
				<button
					type="button"
					className="snopix-btn snopix-mobile-search__cta"
					onClick={() => inputRef.current?.click()}
				>
					{__('Choose photo', 'snopix')}
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
		);
	}

	return (
		<div className="snopix-mobile-search snopix-mobile-search--active">
			<div className="flex items-start gap-3">
				{probe && (
					<div className="w-16 h-16 rounded-[10px] overflow-hidden bg-snopix-surface border border-snopix-border shrink-0">
						<img
							src={probe.url}
							alt={probe.name}
							className="w-full h-full object-cover block"
						/>
					</div>
				)}
				<div className="flex-1 min-w-0">
					<div className="text-[13px] font-semibold truncate">
						{probe?.name ?? ''}
					</div>
					<div className="text-[11px] font-mono text-snopix-muted mt-0.5">
						{probe ? formatBytes(probe.size) : ''}
					</div>
					{phase === 'scanning' && (
						<div className="mt-2">
							<div className="snopix-progress snopix-progress--indeterminate">
								<div className="snopix-progress__fill" />
							</div>
							<div className="text-[11px] text-snopix-muted mt-1.5">
								{__('Scoring against your library…', 'snopix')}
							</div>
						</div>
					)}
				</div>
				<button
					type="button"
					className="w-7 h-7 grid place-items-center rounded-full bg-snopix-surface text-snopix-muted border-0"
					onClick={reset}
					aria-label={__('New search', 'snopix')}
				>
					<IconX size={14} />
				</button>
			</div>

			{phase === 'results' && (
				<div className="mt-3">
					{error && (
						<div className="text-[12px] text-snopix-danger">
							{error}
						</div>
					)}
					{!error && results.length === 0 && (
						<div className="text-[12px] text-snopix-muted">
							{__('No similar images found.', 'snopix')}
						</div>
					)}
					{!error && results.length > 0 && (
						<>
							<div className="text-[11px] font-medium text-snopix-muted uppercase tracking-[0.04em] mb-2">
								{sprintf(
									/* translators: %d: match count */
									__('%d matches', 'snopix'),
									results.length
								)}
							</div>
							<div className="flex flex-col gap-2">
								{results.slice(0, 6).map((m, i) => (
									<a
										key={m.id}
										href={m.attachment_url}
										target="_blank"
										rel="noopener noreferrer"
										className="flex items-center gap-3 p-2 rounded-[10px] border border-snopix-border bg-snopix-bg"
									>
										<div className="w-11 h-11 rounded-[8px] overflow-hidden bg-snopix-surface shrink-0">
											<img
												src={m.thumbnail || m.url}
												alt={m.title}
												className="w-full h-full object-cover block"
												loading="lazy"
											/>
										</div>
										<div className="flex-1 min-w-0">
											<div className="text-[13px] font-medium truncate">
												{m.title}
											</div>
											<div className="flex items-center gap-2 mt-1">
												<div className="flex-1 h-1 rounded-full bg-snopix-border overflow-hidden">
													<div
														className="h-full bg-snopix-accent"
														style={{
															width: `${Math.round(m.score * 100)}%`,
														}}
													/>
												</div>
												<span
													className={`font-mono text-[11px] font-semibold ${
														i === 0
															? 'text-snopix-accent-deep'
															: 'text-snopix-muted'
													}`}
												>
													{Math.round(m.score * 100)}%
												</span>
											</div>
										</div>
									</a>
								))}
							</div>
						</>
					)}
				</div>
			)}
		</div>
	);
}
