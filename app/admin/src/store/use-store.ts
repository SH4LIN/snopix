/**
 * Global Zustand store for cross-component admin UI state.
 *
 * Holds the indexing-job and duplicate-scan state machines that drive
 * progress indicators, button enablement, and route guards across the app.
 */
import { create } from 'zustand';

export type IndexingState = 'idle' | 'running' | 'done' | 'stalled';
export type DuplicateScanState = 'idle' | 'running' | 'done';

interface PSStore {
	indexingState: IndexingState;
	setIndexingState: (s: IndexingState) => void;
	duplicateScanState: DuplicateScanState;
	setDuplicateScanState: (s: DuplicateScanState) => void;
}

/**
 * Snopix admin Zustand store.
 *
 * Pair of independent state machines:
 *   - `indexingState`      idle → running → done|stalled → idle
 *   - `duplicateScanState` idle → running → done → idle
 *
 * The `set*` helpers are intentionally thin so individual hooks can drive
 * transitions without owning the underlying mechanism.
 */
export const useStore = create<PSStore>((set) => ({
	indexingState: 'idle',
	setIndexingState: (s) => set({ indexingState: s }),
	duplicateScanState: 'idle',
	setDuplicateScanState: (s) => set({ duplicateScanState: s }),
}));
