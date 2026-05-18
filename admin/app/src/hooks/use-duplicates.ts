import { useEffect, useRef } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStore } from '../store/use-store';

declare const ps_data: { rest_url: string; nonce: string };

export interface DuplicateImage {
	id: number;
	title: string;
	filename: string;
	file_size: number;
	width: number;
	height: number;
	mime_type: string;
	thumbnail_url: string;
	full_url: string;
}

export interface DuplicateGroup {
	match_type: 'exact' | 'perceptual';
	images: DuplicateImage[];
}

export interface DuplicatesData {
	groups: DuplicateGroup[];
	last_scanned: string;
	group_count: number;
}

export interface DuplicateScanProgress {
	done: number;
	total: number;
	status: 'idle' | 'running' | 'done';
}

const DONE_RESET_MS = 3_000;

async function apiFetch(path: string, init?: RequestInit): Promise<Response> {
	return fetch(`${ps_data.rest_url}${path}`, {
		headers: { 'X-WP-Nonce': ps_data.nonce },
		...init,
	});
}

export function useDuplicates() {
	return useQuery<DuplicatesData>({
		queryKey: ['duplicates'],
		queryFn: async () => {
			const res = await apiFetch('duplicates');
			if (!res.ok) throw new Error('Failed to fetch duplicates');
			return res.json();
		},
		staleTime: 60_000,
	});
}

export function useStartDuplicateScan() {
	const { setDuplicateScanState } = useStore();
	const qc = useQueryClient();

	return useMutation({
		mutationFn: async () => {
			const res = await apiFetch('duplicates/scan', { method: 'POST' });
			if (!res.ok) throw new Error('Failed to start scan');
			return res.json();
		},
		onSuccess: () => {
			setDuplicateScanState('running');
			qc.invalidateQueries({ queryKey: ['duplicates-progress'] });
		},
	});
}

export function useDuplicateScanProgress() {
	const { duplicateScanState, setDuplicateScanState } = useStore();
	const isRunning = duplicateScanState === 'running';
	const resetTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
	const qc = useQueryClient();

	useEffect(() => {
		return () => {
			if (resetTimerRef.current) clearTimeout(resetTimerRef.current);
		};
	}, []);

	const { data: progress } = useQuery<DuplicateScanProgress>({
		queryKey: ['duplicates-progress'],
		queryFn: async () => {
			const res = await apiFetch('duplicates/progress');
			if (!res.ok) throw new Error('Failed to fetch scan progress');
			return res.json();
		},
		enabled: isRunning,
		refetchInterval: isRunning ? 2_000 : false,
	});

	useEffect(() => {
		if (!isRunning || !progress) return;

		if (progress.status === 'done') {
			setDuplicateScanState('done');
			resetTimerRef.current = setTimeout(() => {
				setDuplicateScanState('idle');
				qc.invalidateQueries({ queryKey: ['duplicates'] });
			}, DONE_RESET_MS);
		}
	}, [progress, isRunning, setDuplicateScanState, qc]);

	return progress;
}

export function useDeleteAttachment() {
	const qc = useQueryClient();

	return useMutation({
		mutationFn: async (id: number) => {
			const res = await apiFetch(`duplicates/attachment/${id}`, {
				method: 'DELETE',
			});
			if (!res.ok) throw new Error('Failed to delete attachment');
			return res.json();
		},
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: ['duplicates'] });
		},
	});
}
