import { create } from 'zustand'

interface PSStore {
  isReindexing: boolean
  setReindexing: (v: boolean) => void
}

export const useStore = create<PSStore>((set) => ({
  isReindexing: false,
  setReindexing: (v) => set({ isReindexing: v }),
}))
