import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStore } from '../store/use-store';

declare const ps_data: { rest_url: string; nonce: string };

async function post<T>(path: string): Promise<T> {
	const res = await fetch(`${ps_data.rest_url}${path}`, {
		method: 'POST',
		headers: { 'X-WP-Nonce': ps_data.nonce },
	});
	if (!res.ok) throw new Error(`${path} failed`);
	return res.json();
}

async function get<T>(path: string): Promise<T> {
	const res = await fetch(`${ps_data.rest_url}${path}`, {
		headers: { 'X-WP-Nonce': ps_data.nonce },
	});
	if (!res.ok) throw new Error(`${path} failed`);
	return res.json();
}

export function useReindexAll() {
	const { setIndexingState } = useStore();
	const qc = useQueryClient();
	return useMutation({
		mutationFn: () => post<{ scheduled: boolean }>('tools/reindex-all'),
		onSuccess: () => {
			setIndexingState('running');
			qc.invalidateQueries({ queryKey: ['status'] });
			qc.invalidateQueries({ queryKey: ['images'] });
			qc.invalidateQueries({ queryKey: ['orphans'] });
		},
	});
}

export function useClearIndex() {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: () => post<{ deleted: number }>('tools/clear-index'),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: ['status'] });
			qc.invalidateQueries({ queryKey: ['images'] });
			qc.invalidateQueries({ queryKey: ['orphans'] });
		},
	});
}

export function useDeleteOrphans() {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: () => post<{ deleted: number }>('tools/delete-orphans'),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: ['status'] });
			qc.invalidateQueries({ queryKey: ['images'] });
			qc.invalidateQueries({ queryKey: ['orphans'] });
		},
	});
}

export function useClearCache() {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: () => post<{ cleared: boolean }>('tools/clear-cache'),
		onSuccess: () => {
			qc.invalidateQueries();
		},
	});
}

export function useOrphanCount() {
	return useQuery<{ orphans: number }>({
		queryKey: ['orphans'],
		queryFn: () => get<{ orphans: number }>('tools/orphans'),
		refetchInterval: 30_000,
	});
}
