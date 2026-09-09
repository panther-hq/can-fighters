import { useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'
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
