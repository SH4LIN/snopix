import { create } from 'zustand'

export type IndexingState = 'idle' | 'running' | 'done' | 'stalled'

interface PSStore {
	indexingState: IndexingState
	setIndexingState: (s: IndexingState) => void
}

export const useStore = create<PSStore>((set) => ({
	indexingState: 'idle',
	setIndexingState: (s) => set({ indexingState: s }),
}))
