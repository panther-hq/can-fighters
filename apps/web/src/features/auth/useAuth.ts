import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { api, ensureCsrfCookie } from '../../lib/api'
import type {
  AuthUser,
  LoginCredentials,
  RegisterCredentials,
  ValidationErrorBody,
} from './types'

export const authKeys = {
  me: ['auth', 'me'] as const,
}

/** Current user, or `null` when not authenticated. */
export function useMe() {
  return useQuery({
    queryKey: authKeys.me,
    queryFn: async (): Promise<AuthUser | null> => {
      try {
        const { data } = await api.get<{ data: AuthUser }>('/auth/me')
        return data.data
      } catch (error) {
        if (isAxiosError(error) && error.response?.status === 401) {
          return null
        }
        throw error
      }
    },
    retry: false,
    staleTime: Infinity,
  })
}

function useAuthSuccess() {
  const queryClient = useQueryClient()
  return (user: AuthUser) => {
    queryClient.setQueryData(authKeys.me, user)
    queryClient.invalidateQueries({ queryKey: ['bootstrap'] })
  }
}

export function useLogin() {
  const onAuthed = useAuthSuccess()
  return useMutation({
    mutationFn: async (credentials: LoginCredentials): Promise<AuthUser> => {
      await ensureCsrfCookie()
      const { data } = await api.post<{ data: AuthUser }>('/auth/login', credentials)
      return data.data
    },
    onSuccess: onAuthed,
  })
}

export function useRegister() {
  const onAuthed = useAuthSuccess()
  return useMutation({
    mutationFn: async (credentials: RegisterCredentials): Promise<AuthUser> => {
      await ensureCsrfCookie()
      const { data } = await api.post<{ data: AuthUser }>('/auth/register', credentials)
      return data.data
    },
    onSuccess: onAuthed,
  })
}

export function useLogout() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (): Promise<void> => {
      await api.post('/auth/logout')
    },
    onSuccess: () => {
      queryClient.setQueryData(authKeys.me, null)
      queryClient.removeQueries({ queryKey: ['bootstrap'] })
    },
  })
}

/** Pull field errors + message out of a Laravel 422 response. */
export function parseValidationError(error: unknown): {
  message: string
  fields: Record<string, string>
} {
  if (isAxiosError<ValidationErrorBody>(error) && error.response?.status === 422) {
    const body = error.response.data
    const fields: Record<string, string> = {}
    for (const [key, messages] of Object.entries(body.errors ?? {})) {
      fields[key] = messages[0]
    }
    return { message: body.message, fields }
  }
  return { message: 'Something went wrong. Try again.', fields: {} }
}
