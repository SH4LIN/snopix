import { useQuery } from '@tanstack/react-query';

declare const ps_data: { rest_url: string; nonce: string };

interface IndexStatus {
	total: number;
	indexed: number;
	pending: number;
}

export function useIndexStatus() {
	return useQuery<IndexStatus>({
		queryKey: ['status'],
		queryFn: async () => {
			const res = await fetch(`${ps_data.rest_url}status`, {
				headers: { 'X-WP-Nonce': ps_data.nonce },
			});
			if (!res.ok) {
				throw new Error('Failed to fetch status');
			}
			return res.json();
		},
		refetchInterval: 30_000,
	});
}
