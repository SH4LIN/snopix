import { useState, useRef } from 'react';
import { __ } from '@wordpress/i18n';

declare const ps_data: { rest_url: string; nonce: string };

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
 * `/wp-json/ps/v1/search`, and renders the top six matches with their
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
			const res = await fetch(`${ps_data.rest_url}search`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': ps_data.nonce },
				body: fd,
			});
			if (!res.ok) throw new Error('Search failed');
			setResults(await res.json());
		} catch {
			setError(
				__(
					'Something went wrong. Try a different image.',
					'pixel-scout'
				)
			);
		} finally {
			setLoading(false);
		}
	}

	return (
		<div className="ps-card">
			<div className="text-sm font-semibold mb-3 text-ps-text">
				{__('Search by Image', 'pixel-scout')}
			</div>

			<div
				className="ps-drop-zone"
				onClick={() => inputRef.current?.click()}
				onDragOver={(e) => e.preventDefault()}
				onDrop={(e) => {
					e.preventDefault();
					const f = e.dataTransfer.files[0];
					if (f) handleFile(f);
				}}
			>
				<div className="text-[13px] text-ps-muted">
					{__('Drop an image to test search', 'pixel-scout')}
				</div>
				<div className="text-xs text-ps-muted mt-1">
					{__('or click to browse', 'pixel-scout')}
				</div>
				<input
					ref={inputRef}
					type="file"
					accept="image/*"
					className="hidden"
					onChange={(e) => {
						const f = e.target.files?.[0];
						if (f) handleFile(f);
					}}
				/>
			</div>

			{loading && (
				<div className="mt-3 text-[13px] text-ps-muted">
					{__('Searching…', 'pixel-scout')}
				</div>
			)}

			{error && (
				<div className="mt-3 text-[13px] text-ps-danger">{error}</div>
			)}

			{results !== null && results.length === 0 && (
				<div className="mt-3 text-[13px] text-ps-muted">
					{__(
						'No similar images found. Try a different image.',
						'pixel-scout'
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
							className="block no-underline rounded-[8px] overflow-hidden bg-ps-surface border border-ps-border hover:border-ps-accent transition-colors"
						>
							<img
								src={r.url}
								alt={r.title}
								className="w-full object-contain block max-h-64"
							/>
							<div className="flex items-center justify-between px-3 py-2">
								<span className="text-[12px] text-ps-text truncate">
									{r.title}
								</span>
								<span className="text-[11px] font-medium text-ps-accent ml-2 shrink-0">
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
