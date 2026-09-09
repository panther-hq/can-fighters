import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { MixConcept, MixIngredient, MixRequestView } from './types'

export function useMixPreview() {
  return useMutation({
    mutationFn: async (ingredients: MixIngredient[]): Promise<MixConcept> => {
      const { data } = await api.post<{ concept: MixConcept }>('/mixer/preview', {
        ingredients,
      })
      return data.concept
    },
  })
}

export function useMix() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (ingredients: MixIngredient[]): Promise<MixRequestView> => {
      const { data } = await api.post<MixRequestView>(
        '/mixer/mix',
        { ingredients },
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      )
      return data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
    },
  })
}

/** Polls a mix request while it is still processing. */
export function useMixStatus(mixId: number | null) {
  const queryClient = useQueryClient()
  return useQuery({
    queryKey: ['mixer', mixId],
    enabled: mixId != null,
    queryFn: async (): Promise<MixRequestView> => {
      const { data } = await api.get<MixRequestView>(`/mixer/${mixId}`)
      if (data.status !== 'processing') {
        queryClient.invalidateQueries({ queryKey: ['fighters'] })
        queryClient.invalidateQueries({ queryKey: ['bootstrap'] })
      }
      return data
    },
    refetchInterval: (query) => {
      const status = query.state.data?.status
      return status == null || status === 'processing' ? 1500 : false
    },
  })
}
