import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { FightOutcome, PveStage } from './types'

export function useStages() {
  return useQuery({
    queryKey: ['pve', 'stages'],
    queryFn: async (): Promise<PveStage[]> => {
      const { data } = await api.get<{ stages: PveStage[] }>('/pve/stages')
      return data.stages
    },
  })
}

export function useFightStage() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (slug: string): Promise<FightOutcome> => {
      const { data } = await api.post<FightOutcome>(
        `/pve/stages/${slug}/battle`,
        null,
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      )
      return data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['pve', 'stages'] })
      queryClient.invalidateQueries({ queryKey: ['bootstrap'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['fighters'] })
    },
  })
}
