import { useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { PlayerCan } from '../inventory/types'
import type { Team } from '../teams/types'

export interface Bootstrap {
  player: {
    id: number
    displayName: string
    profile: { level: number; xp: number; coins: number; rating: number }
  }
  currencies: { coins: number }
  team: Team | null
  cans: PlayerCan[]
  pve: { regionsCleared: number; regionsTotal: number; onExpedition: boolean }
  arena: { rating: number; league: string; defenseSet: boolean }
  daily: { canClaim: boolean; streak: number }
  notifications: unknown[]
  serverTime: string
}

/**
 * Everything the SPA needs after login / reconnect (spec §43). Call after the
 * user is known to be authenticated.
 */
export function useBootstrap(enabled = true) {
  return useQuery({
    queryKey: ['bootstrap'],
    queryFn: async (): Promise<Bootstrap> => {
      const { data } = await api.get<Bootstrap>('/game/bootstrap')
      return data
    },
    enabled,
  })
}
