import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { Team, TeamPosition } from './types'

export function useTeam() {
  return useQuery({
    queryKey: ['team'],
    queryFn: async (): Promise<Team | null> => {
      const { data } = await api.get<{ team: Team | null }>('/teams')
      return data.team
    },
  })
}

export function useSaveTeam() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (
      members: { fighterId: number; position: TeamPosition }[],
    ): Promise<Team> => {
      const { data } = await api.put<{ team: Team }>('/teams', { members })
      return data.team
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['team'] })
      queryClient.invalidateQueries({ queryKey: ['bootstrap'] })
    },
  })
}
