import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { api } from './api'

type AuthCallback = (error: Error | null, data: { auth: string } | null) => void

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

window.Pusher = Pusher

/**
 * Laravel Echo bound to Reverb. Private-channel auth goes through our axios
 * client (same-origin `/broadcasting/auth` via the Vite proxy) so the Sanctum
 * session cookie + XSRF header come along.
 */
export function createEcho(): Echo<'reverb'> {
  const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http'
  const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080)

  const options = {
    broadcaster: 'reverb' as const,
    key: import.meta.env.VITE_REVERB_APP_KEY ?? 'local-reverb-key',
    wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: AuthCallback) => {
        api
          .post(
            '/broadcasting/auth',
            { socket_id: socketId, channel_name: channel.name },
            { baseURL: '/' },
          )
          .then((response) => callback(null, response.data))
          .catch((error: Error) => callback(error, null))
      },
    }),
  }

  return new Echo(
    options as unknown as ConstructorParameters<typeof Echo>[0],
  ) as unknown as Echo<'reverb'>
}
