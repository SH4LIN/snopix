import { useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '../lib/api';

export interface SearchResultItem {
	id: number;
	url: string;
	thumbnail: string;
	title: string;
	score: number;
	attachment_url: string;
}

export type ImageSearchPhase = 'idle' | 'scanning' | 'results';

export interface ImageSearchProbe {
	url: string;
	name: string;
	size: number;
}

/**
 * State + actions returned by {@link useImageSearch}.
 */
export interface ImageSearchState {
	/** Current state machine phase. */
	phase: ImageSearchPhase;
	/** Object-URL-backed preview of the file being searched, or null when idle. */
	probe: ImageSearchProbe | null;
	/** Ranked match list. Empty until phase === 'results'. */
	results: SearchResultItem[];
	/** Human-readable error string when the search request fails. */
	error: string | null;
	/** Submit a file to `POST /search`, transitioning idle → scanning → results. */
	handleFile: (file: File) => Promise<void>;
	/** Drop the probe, clear results, and return to idle. */
	reset: () => void;
}

/**
 * Reverse-image-search state machine.
 *
 * Owns the same idle → scanning → results flow that both `SearchPreview`
 * (desktop) and `SearchPreviewMobile` render. Components stay focused on
 * layout — the probe object URL bookkeeping, error mapping, and REST call
 * live here.
 *
 * @return {ImageSearchState}
 */
export function useImageSearch(): ImageSearchState {
	const [phase, setPhase] = useState<ImageSearchPhase>('idle');
	const [probe, setProbe] = useState<ImageSearchProbe | null>(null);
	const [results, setResults] = useState<SearchResultItem[]>([]);
	const [error, setError] = useState<string | null>(null);
	const probeUrlRef = useRef<string | null>(null);

	// Revoke the outstanding object URL when the hook unmounts (e.g. the user
	// navigates away mid-search) so the underlying File isn't pinned in memory.
	useEffect(() => {
		return () => {
			if (probeUrlRef.current) {
				URL.revokeObjectURL(probeUrlRef.current);
			}
		};
	}, []);

	async function handleFile(file: File) {
		if (probeUrlRef.current) {
			URL.revokeObjectURL(probeUrlRef.current);
		}
		const url = URL.createObjectURL(file);
		probeUrlRef.current = url;
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
		if (probeUrlRef.current) {
			URL.revokeObjectURL(probeUrlRef.current);
			probeUrlRef.current = null;
		}
		setProbe(null);
		setResults([]);
		setError(null);
		setPhase('idle');
	}

	return { phase, probe, results, error, handleFile, reset };
}
