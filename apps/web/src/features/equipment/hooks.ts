import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { Fighter } from '../fighters/types'
import type { EquipSlot, PlayerEquipment } from './types'

export function useEquipment() {
  return useQuery({
    queryKey: ['equipment'],
    queryFn: async (): Promise<PlayerEquipment[]> => {
      const { data } = await api.get<{ equipment: PlayerEquipment[] }>('/equipment')
      return data.equipment
    },
  })
}

function useEquipSuccess() {
  const queryClient = useQueryClient()
  return () => {
    queryClient.invalidateQueries({ queryKey: ['equipment'] })
    queryClient.invalidateQueries({ queryKey: ['fighters'] })
    queryClient.invalidateQueries({ queryKey: ['team'] })
  }
}

export function useEquipFighter() {
  const onDone = useEquipSuccess()
  return useMutation({
    mutationFn: async (args: {
      fighterId: number
      playerEquipmentId: number
    }): Promise<Fighter> => {
      const { data } = await api.post<{ data: Fighter }>(
        `/fighters/${args.fighterId}/equip`,
        { playerEquipmentId: args.playerEquipmentId },
      )
      return data.data
    },
    onSuccess: onDone,
  })
}

export function useUnequipFighter() {
  const onDone = useEquipSuccess()
  return useMutation({
    mutationFn: async (args: {
      fighterId: number
      slot: EquipSlot
    }): Promise<Fighter> => {
      const { data } = await api.post<{ data: Fighter }>(
        `/fighters/${args.fighterId}/unequip`,
        { slot: args.slot },
      )
      return data.data
    },
    onSuccess: onDone,
  })
}
