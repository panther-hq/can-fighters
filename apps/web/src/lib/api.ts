import axios from 'axios'

/**
 * Shared HTTP client for the Can Fighters API.
 *
 * Requests go through the Vite dev proxy (`/api` -> Laravel), so this is a
 * same-origin call in development. `withCredentials` + `withXSRFToken` make
 * Sanctum SPA cookie auth work: axios sends the session cookie and mirrors
 * the `XSRF-TOKEN` cookie into the `X-XSRF-TOKEN` header on writes.
 */
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

/**
 * Prime the `XSRF-TOKEN` cookie. Call once before the first write of a session
 * (login / register); Sanctum's endpoint lives outside the `/api` prefix.
 */
export async function ensureCsrfCookie(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}
