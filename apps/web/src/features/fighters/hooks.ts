import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { MixIngredient } from '../mixer/types'
import type { Fighter } from './types'

export function useFighters() {
  return useQuery({
    queryKey: ['fighters'],
    queryFn: async (): Promise<Fighter[]> => {
      const { data } = await api.get<{ data: Fighter[] }>('/fighters')
      return data.data
    },
  })
}

function useFighterMutationSuccess() {
  const queryClient = useQueryClient()
  return () => {
    queryClient.invalidateQueries({ queryKey: ['fighters'] })
    queryClient.invalidateQueries({ queryKey: ['bootstrap'] })
    queryClient.invalidateQueries({ queryKey: ['team'] })
  }
}

export function useUpgradeFighter() {
  const onDone = useFighterMutationSuccess()
  return useMutation({
    mutationFn: async (fighterId: number): Promise<Fighter> => {
      const { data } = await api.post<{ data: Fighter }>(`/fighters/${fighterId}/upgrade`)
      return data.data
    },
    onSuccess: onDone,
  })
}

export function useMutateFighter() {
  const onDone = useFighterMutationSuccess()
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (args: {
      fighterId: number
      ingredients: MixIngredient[]
    }): Promise<Fighter> => {
      const { data } = await api.post<{ data: Fighter }>(
        `/fighters/${args.fighterId}/mutate`,
        { ingredients: args.ingredients },
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      )
      return data.data
    },
    onSuccess: () => {
      onDone()
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
    },
  })
}
