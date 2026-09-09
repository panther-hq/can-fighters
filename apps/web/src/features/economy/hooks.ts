import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type {
  BuyResult,
  Collection,
  DailyClaimResult,
  DailyStatus,
  ShopState,
} from './types'

export function useCollection() {
  return useQuery({
    queryKey: ['collection'],
    queryFn: async (): Promise<Collection> => {
      const { data } = await api.get<Collection>('/collection')
      return data
    },
  })
}

export function useDaily() {
  return useQuery({
    queryKey: ['daily'],
    queryFn: async (): Promise<DailyStatus> => {
      const { data } = await api.get<DailyStatus>('/daily')
      return data
    },
  })
}

export function useClaimDaily() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (): Promise<DailyClaimResult> => {
      const { data } = await api.post<DailyClaimResult>('/daily/claim')
      return data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['daily'] })
      queryClient.invalidateQueries({ queryKey: ['bootstrap'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
    },
  })
}

export function useShop() {
  return useQuery({
    queryKey: ['shop'],
    queryFn: async (): Promise<ShopState> => {
      const { data } = await api.get<ShopState>('/shop')
      return data
    },
  })
}

export function useBuyOffer() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (offerId: string): Promise<BuyResult> => {
      const { data } = await api.post<BuyResult>(`/shop/${offerId}/buy`, null, {
        headers: { 'Idempotency-Key': crypto.randomUUID() },
      })
      return data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['shop'] })
      queryClient.invalidateQueries({ queryKey: ['bootstrap'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['equipment'] })
    },
  })
}
