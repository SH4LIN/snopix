import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useStore } from '../store/use-store'

declare const ps_data: { rest_url: string; nonce: string }

interface Progress { done: number; total: number }

export function useProgress(enabled: boolean) {
  return useQuery<Progress>({
    queryKey: ['progress'],
    queryFn: async () => {
      const res = await fetch(`${ps_data.rest_url}progress`, {
        headers: { 'X-WP-Nonce': ps_data.nonce },
      })
      if (!res.ok) throw new Error('Failed to fetch progress')
      return res.json()
    },
    enabled,
    refetchInterval: enabled ? 2_000 : false,
  })
}

export function useReindex() {
  const setReindexing = useStore((s) => s.setReindexing)
  const qc = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      const res = await fetch(`${ps_data.rest_url}reindex`, {
        method: 'POST',
        headers: { 'X-WP-Nonce': ps_data.nonce },
      })
      if (!res.ok) throw new Error('Reindex failed')
      return res.json()
    },
    onSuccess: () => {
      setReindexing(true)
      qc.invalidateQueries({ queryKey: ['status'] })
    },
  })
}
