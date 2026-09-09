import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import type { ConnectionState } from './useRealtimeSync'

/**
 * Thin banner shown when the browser is offline or the realtime link is down.
 * On coming back online it refetches everything so the UI catches up
 * (spec §70 — recover state from the API).
 */
export function ConnectionBanner({ realtime }: { realtime: ConnectionState }) {
  const queryClient = useQueryClient()
  const [online, setOnline] = useState(
    typeof navigator === 'undefined' ? true : navigator.onLine,
  )

  useEffect(() => {
    const goOnline = () => {
      setOnline(true)
      queryClient.invalidateQueries()
    }
    const goOffline = () => setOnline(false)
    window.addEventListener('online', goOnline)
    window.addEventListener('offline', goOffline)
    return () => {
      window.removeEventListener('online', goOnline)
      window.removeEventListener('offline', goOffline)
    }
  }, [queryClient])

  if (!online) {
    return <div className="netbar netbar--bad">Brak połączenia z internetem</div>
  }
  if (realtime === 'disconnected') {
    return (
      <div className="netbar">Powiadomienia na żywo niedostępne — odświeżanie ręczne</div>
    )
  }
  return null
}
