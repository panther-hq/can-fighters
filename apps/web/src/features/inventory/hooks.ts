import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { Ingredient, Inventory, OpenCanResult } from './types'

export function useIngredients() {
  return useQuery({
    queryKey: ['ingredients'],
    queryFn: async (): Promise<Ingredient[]> => {
      const { data } = await api.get<{ data: Ingredient[] }>('/ingredients')
      return data.data
    },
    staleTime: Infinity,
  })
}

export function useInventory() {
  return useQuery({
    queryKey: ['inventory'],
    queryFn: async (): Promise<Inventory> => {
      const { data } = await api.get<Inventory>('/player/inventory')
      return data
    },
  })
}

export function useOpenCan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (canId: number): Promise<OpenCanResult> => {
      const { data } = await api.post<OpenCanResult>(
        `/cans/${canId}/open`,
        null,
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      )
      return data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['bootstrap'] })
    },
  })
}
