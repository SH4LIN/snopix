import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '../lib/api';

interface ImageRow {
	attachment_id: number;
	mime_type: string;
	file_size: number;
	width: number;
	height: number;
	indexed_at: string;
	phash: string;
}

interface UseImagesParams {
	afterId: number;
	search: string;
}

/**
 * Fetch a keyset-paginated page of indexed image attachments.
 *
 * Backed by `GET /wp-json/ps/v1/images`. The cache key is keyed on `afterId`
 * and `search`, so changing either issues a fresh request and the previous
 * page stays cached for an instant "previous" navigation.
 *
 * @param {UseImagesParams} params         Query parameters.
 * @param {number}          params.afterId Keyset cursor — return images with id > this value (0 for first page).
 * @param {string}          params.search  Substring search across title and `_wp_attached_file`.
 *
 * @return {import('@tanstack/react-query').UseQueryResult<ImageRow[]>}
 */
export function useImages({ afterId, search }: UseImagesParams) {
	return useQuery<ImageRow[]>({
		queryKey: ['images', afterId, search],
		queryFn: () => {
			const params = new URLSearchParams({
				after_id: String(afterId),
				per_page: '25',
				search,
			});
			return apiFetch<ImageRow[]>(`images?${params}`);
		},
		staleTime: 30_000,
	});
}
