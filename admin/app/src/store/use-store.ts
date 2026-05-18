import { create } from 'zustand';

export type IndexingState = 'idle' | 'running' | 'done' | 'stalled';
export type DuplicateScanState = 'idle' | 'running' | 'done';

interface PSStore {
	indexingState: IndexingState;
	setIndexingState: (s: IndexingState) => void;
	duplicateScanState: DuplicateScanState;
	setDuplicateScanState: (s: DuplicateScanState) => void;
}

export const useStore = create<PSStore>((set) => ({
	indexingState: 'idle',
	setIndexingState: (s) => set({ indexingState: s }),
	duplicateScanState: 'idle',
	setDuplicateScanState: (s) => set({ duplicateScanState: s }),
}));
