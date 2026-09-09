import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { Region, RunView, VisitResult } from './types'

export function useRegions() {
  return useQuery({
    queryKey: ['pve', 'regions'],
    queryFn: async (): Promise<{ regions: Region[]; run: RunView | null }> => {
      const { data } = await api.get<{ regions: Region[]; run: RunView | null }>(
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
    mutationFn: async (regionSlug: string): Promise<RunView> => {
      const { data } = await api.post<{ run: RunView }>(`/pve/regions/${regionSlug}/run`)
      return data.run
    },
    onSuccess: onDone,
  })
}

export function useVisitNode() {
  const onDone = useRunSuccess()
  return useMutation({
    mutationFn: async (nodeId: string): Promise<VisitResult> => {
      const { data } = await api.post<VisitResult>(`/pve/run/visit/${nodeId}`, null, {
        headers: { 'Idempotency-Key': crypto.randomUUID() },
      })
      return data
    },
    onSuccess: onDone,
  })
}

export function useMerchantBuy() {
  const onDone = useRunSuccess()
  return useMutation({
    mutationFn: async (offerId: string): Promise<{ run: RunView }> => {
      const { data } = await api.post<{ run: RunView }>('/pve/run/buy', {
        offerId,
      })
      return data
    },
    onSuccess: onDone,
  })
}

export function useAdvanceRun() {
  const onDone = useRunSuccess()
  return useMutation({
    mutationFn: async (): Promise<{ run: RunView }> => {
      const { data } = await api.post<{ run: RunView }>('/pve/run/advance')
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
