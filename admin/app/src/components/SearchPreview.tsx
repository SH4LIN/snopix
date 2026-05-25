import { useState, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '../lib/api';

interface SearchResultItem {
	id: number;
	url: string;
	thumbnail: string;
	title: string;
	score: number;
	attachment_url: string;
}

/**
 * Reverse-image search side panel rendered next to the indexed-image table.
 *
 * Accepts a single image via drag-drop or click-to-browse, POSTs it to
 * `/wp-json/snopix/v1/search`, and renders the top six matches with their
 * composite score. Falls back to a "no similar images" message on empty
 * results and a generic error string on any HTTP failure.
 *
 * @return {JSX.Element}
 */
export default function SearchPreview() {
	const [results, setResults] = useState<SearchResultItem[] | null>(null);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState<string | null>(null);
	const inputRef = useRef<HTMLInputElement>(null);

	/**
	 * Upload a single image to the search endpoint and update local state with
	 * the response. Resets `results`, `error` and shows the loading indicator
	 * for the duration of the request.
	 *
	 * @param {File} file Image selected via input or drag-drop.
	 *
	 * @return {Promise<void>}
	 */
	async function handleFile(file: File) {
		setLoading(true);
		setError(null);
		setResults(null);
		const fd = new FormData();
		fd.append('file', file);
		try {
			setResults(
				await apiFetch<SearchResultItem[]>({
					path: 'snopix/v1/search',
					method: 'POST',
					formData: fd,
				})
			);
		} catch {
			setError(
				__(
					'Something went wrong. Try a different image.',
					'snopix'
				)
			);
		} finally {
			setLoading(false);
		}
	}

	return (
		<div className="snopix-card">
			<div className="text-sm font-semibold mb-3 text-snopix-text">
				{__('Search by Image', 'snopix')}
			</div>

			<div
				className="snopix-drop-zone"
				onClick={() => inputRef.current?.click()}
				onDragOver={(e) => e.preventDefault()}
				onDrop={(e) => {
					e.preventDefault();
					const f = e.dataTransfer.files[0];
					if (f) {
						handleFile(f);
					}
				}}
			>
				<div className="text-[13px] text-snopix-muted">
					{__('Drop an image to test search', 'snopix')}
				</div>
				<div className="text-xs text-snopix-muted mt-1">
					{__('or click to browse', 'snopix')}
				</div>
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

			{loading && (
				<div className="mt-3 text-[13px] text-snopix-muted">
					{__('Searching…', 'snopix')}
				</div>
			)}

			{error && (
				<div className="mt-3 text-[13px] text-snopix-danger">{error}</div>
			)}

			{results !== null && results.length === 0 && (
				<div className="mt-3 text-[13px] text-snopix-muted">
					{__(
						'No similar images found. Try a different image.',
						'snopix'
					)}
				</div>
			)}

			{results && results.length > 0 && (
				<div className="mt-4 flex flex-col gap-3">
					{results.slice(0, 6).map((r) => (
						<a
							key={r.id}
							href={r.attachment_url}
							target="_blank"
							rel="noopener noreferrer"
							className="block no-underline rounded-[8px] overflow-hidden bg-snopix-surface border border-snopix-border hover:border-snopix-accent transition-colors"
						>
							<img
								src={r.url}
								alt={r.title}
								className="w-full object-contain block max-h-64"
							/>
							<div className="flex items-center justify-between px-3 py-2">
								<span className="text-[12px] text-snopix-text truncate">
									{r.title}
								</span>
								<span className="text-[11px] font-medium text-snopix-accent ml-2 shrink-0">
									{Math.round(r.score * 100)}%
								</span>
							</div>
						</a>
					))}
				</div>
			)}
		</div>
	);
}
