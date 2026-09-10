import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { MoveResult, OverworldView, Region } from './types'

export function useRegions() {
  return useQuery({
    queryKey: ['pve', 'regions'],
    queryFn: async (): Promise<{ regions: Region[]; run: OverworldView | null }> => {
      const { data } = await api.get<{ regions: Region[]; run: OverworldView | null }>(
        '/pve/regions',
      )
      return data
    },
  })
}

function useRunSuccess() {
  const queryClient = useQueryClient()
  return () => {
    queryClient.invalidateQueries({ queryKey: ['pve', 'regions'] })
    queryClient.invalidateQueries({ queryKey: ['bootstrap'] })
    queryClient.invalidateQueries({ queryKey: ['inventory'] })
    queryClient.invalidateQueries({ queryKey: ['fighters'] })
    queryClient.invalidateQueries({ queryKey: ['equipment'] })
  }
}

export function useStartRun() {
  const onDone = useRunSuccess()
  return useMutation({
    mutationFn: async (regionSlug: string): Promise<OverworldView> => {
      const { data } = await api.post<{ run: OverworldView }>(`/pve/regions/${regionSlug}/run`)
      return data.run
    },
    onSuccess: onDone,
  })
}

export function useMoveHero() {
  const onDone = useRunSuccess()
  return useMutation({
    mutationFn: async (target: { x: number; y: number }): Promise<MoveResult> => {
      const { data } = await api.post<MoveResult>('/pve/run/move', target, {
        headers: { 'Idempotency-Key': crypto.randomUUID() },
      })
      return data
    },
    onSuccess: onDone,
  })
}

export function useEndDay() {
  const onDone = useRunSuccess()
  return useMutation({
    mutationFn: async (): Promise<MoveResult> => {
      const { data } = await api.post<MoveResult>('/pve/run/end-day')
      return data
    },
    onSuccess: onDone,
  })
}

export function useMerchantBuy() {
  const onDone = useRunSuccess()
  return useMutation({
    mutationFn: async (offerId: string): Promise<{ run: OverworldView }> => {
      const { data } = await api.post<{ run: OverworldView }>('/pve/run/buy', { offerId })
      return data
    },
    onSuccess: onDone,
  })
}

export function useLeaveMerchant() {
  const onDone = useRunSuccess()
  return useMutation({
    mutationFn: async (): Promise<{ run: OverworldView }> => {
      const { data } = await api.post<{ run: OverworldView }>('/pve/run/leave-merchant')
      return data
    },
    onSuccess: onDone,
  })
}

export function useAbandonRun() {
  const onDone = useRunSuccess()
  return useMutation({
    mutationFn: async (): Promise<void> => {
      await api.post('/pve/run/abandon')
    },
    onSuccess: onDone,
  })
}
