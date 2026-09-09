import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { Team, TeamPosition } from './types'

export type TeamType = 'campaign' | 'defense'

export function useTeam(type: TeamType = 'campaign') {
  return useQuery({
    queryKey: ['team', type],
    queryFn: async (): Promise<Team | null> => {
      if (type === 'campaign') {
        const { data } = await api.get<{ team: Team | null }>('/teams')
        return data.team
      }
      const { data } = await api.get<{ defenseTeam: Team | null }>('/arena')
      return data.defenseTeam
    },
  })
}

export function useSaveTeam(type: TeamType = 'campaign') {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (
      members: { fighterId: number; position: TeamPosition }[],
    ): Promise<Team> => {
      const url = type === 'campaign' ? '/teams' : '/arena/defense-team'
      const { data } = await api.put<{ team: Team }>(url, { members })
      return data.team
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['team', type] })
      queryClient.invalidateQueries({ queryKey: ['bootstrap'] })
      queryClient.invalidateQueries({ queryKey: ['arena'] })
    },
  })
}
