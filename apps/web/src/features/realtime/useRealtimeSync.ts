import { useQueryClient } from '@tanstack/react-query'
import type Echo from 'laravel-echo'
import { useEffect, useRef, useState } from 'react'
import { createEcho } from '../../lib/echo'

export type ConnectionState = 'connecting' | 'connected' | 'disconnected'

/**
 * Subscribes to the player's private channel and turns realtime events into
 * TanStack Query invalidations (spec §41 — REST stays the source of truth,
 * WebSockets just say "something changed"). Best-effort: if Reverb is
 * unreachable the app still works, it just won't auto-refresh.
 */
export function useRealtimeSync(userId: number | undefined): ConnectionState {
  const queryClient = useQueryClient()
  const echoRef = useRef<Echo<'reverb'> | null>(null)
  const [state, setState] = useState<ConnectionState>('connecting')

  useEffect(() => {
    if (userId == null) return

    let echo: Echo<'reverb'>
    try {
      echo = createEcho()
    } catch {
      queueMicrotask(() => setState('disconnected'))
      return
    }
    echoRef.current = echo

    const connection = (
      echo.connector as unknown as {
        pusher?: { connection: { bind: (e: string, cb: (s: unknown) => void) => void } }
      }
    ).pusher?.connection

    connection?.bind('state_change', (change: unknown) => {
      const next = (change as { current?: string }).current
      if (next === 'connected') setState('connected')
      else if (next === 'connecting' || next === 'initialized') setState('connecting')
      else setState('disconnected')
    })

    const invalidate = (...keys: string[][]) => {
      for (const key of keys) queryClient.invalidateQueries({ queryKey: key })
    }

    echo
      .private(`player.${userId}`)
      .listen('.mixer.completed', () =>
        invalidate(['fighters'], ['inventory'], ['bootstrap']),
      )
      .listen('.mixer.failed', () => invalidate(['bootstrap']))
      .listen('.arena.rating_updated', () =>
        invalidate(['arena'], ['arena', 'ranking'], ['bootstrap']),
      )
      .listen('.arena.defense_attacked', () =>
        invalidate(['arena'], ['arena', 'history'], ['bootstrap']),
      )

    return () => {
      echo.leave(`player.${userId}`)
      echo.disconnect()
      echoRef.current = null
    }
  }, [userId, queryClient])

  return state
}
