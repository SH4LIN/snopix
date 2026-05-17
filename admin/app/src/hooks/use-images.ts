import { useQuery } from '@tanstack/react-query'

declare const ps_data: { rest_url: string; nonce: string }

interface ImageRow {
	attachment_id: number
	mime_type: string
	file_size: number
	width: number
	height: number
	indexed_at: string
	phash: string
}

interface UseImagesParams {
	page: number
	search: string
}

export function useImages({ page, search }: UseImagesParams) {
	return useQuery<ImageRow[]>({
		queryKey: ['images', page, search],
		queryFn: async () => {
			const params = new URLSearchParams({ page: String(page), per_page: '25', search })
			const res = await fetch(`${ps_data.rest_url}images?${params}`, {
				headers: { 'X-WP-Nonce': ps_data.nonce },
			});
			if (!res.ok) throw new Error('Failed to fetch images');
			return res.json();
		},
	})
}
