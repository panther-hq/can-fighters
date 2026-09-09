import { useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'

export interface HealthResponse {
  status: 'ok' | 'degraded'
  service: string
  checks: Record<string, boolean>
  time: string
}

export function useHealth() {
  return useQuery({
    queryKey: ['health'],
    queryFn: async (): Promise<HealthResponse> => {
      const { data } = await api.get<HealthResponse>('/health')
      return data
    },
    refetchInterval: 15_000,
  })
}
