import { useMutation, useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type {
  LiveAction,
  LiveActionResult,
  LiveBattleView,
  QueueResult,
} from './types'

export function useJoinQueue() {
  return useMutation({
    mutationFn: async (): Promise<QueueResult> => {
      const { data } = await api.post<QueueResult>('/arena/live/queue')
      return data
    },
  })
}

export function useLeaveQueue() {
  return useMutation({
    mutationFn: async (): Promise<void> => {
      await api.delete('/arena/live/queue')
    },
  })
}

export function useLiveBattle(battleId: number | null) {
  return useQuery({
    queryKey: ['live', battleId],
    enabled: battleId != null,
    queryFn: async (): Promise<LiveBattleView> => {
      const { data } = await api.get<{ battle: LiveBattleView }>(`/battles/${battleId}`)
      return data.battle
    },
    refetchInterval: (query) =>
      query.state.data?.status === 'active' ? 2500 : false,
  })
}

export function useSubmitAction(battleId: number) {
  return useMutation({
    mutationFn: async (action: LiveAction): Promise<LiveActionResult> => {
      const { data } = await api.post<LiveActionResult>(
        `/battles/${battleId}/actions`,
        action,
      )
      return data
    },
  })
}

export function usePollResolve(battleId: number) {
  return useMutation({
    mutationFn: async (): Promise<LiveActionResult> => {
      const { data } = await api.post<LiveActionResult>(`/battles/${battleId}/resolve`)
      return data
    },
  })
}
