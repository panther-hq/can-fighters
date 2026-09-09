import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type {
  ArenaSummary,
  ChallengeOutcome,
  HistoryRow,
  Opponent,
  RankRow,
} from './types'

export function useArena() {
  return useQuery({
    queryKey: ['arena'],
    queryFn: async (): Promise<ArenaSummary> => {
      const { data } = await api.get<ArenaSummary>('/arena')
      return data
    },
  })
}

export function useOpponents() {
  return useQuery({
    queryKey: ['arena', 'opponents'],
    queryFn: async (): Promise<Opponent[]> => {
      const { data } = await api.get<{ opponents: Opponent[] }>('/arena/opponents')
      return data.opponents
    },
  })
}

export function useRanking() {
  return useQuery({
    queryKey: ['arena', 'ranking'],
    queryFn: async (): Promise<{ top: RankRow[]; me: RankRow }> => {
      const { data } = await api.get<{ top: RankRow[]; me: RankRow }>('/arena/ranking')
      return data
    },
  })
}

export function useArenaHistory() {
  return useQuery({
    queryKey: ['arena', 'history'],
    queryFn: async (): Promise<HistoryRow[]> => {
      const { data } = await api.get<{ history: HistoryRow[] }>('/arena/history')
      return data.history
    },
  })
}

export function useChallenge() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (playerId: number): Promise<ChallengeOutcome> => {
      const { data } = await api.post<ChallengeOutcome>(
        `/arena/challenge/${playerId}`,
        null,
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      )
      return data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['arena'] })
      queryClient.invalidateQueries({ queryKey: ['bootstrap'] })
    },
  })
}
