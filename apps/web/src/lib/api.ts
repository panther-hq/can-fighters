import axios from 'axios'

/**
 * Shared HTTP client for the Can Fighters API.
 *
 * Requests go through the Vite dev proxy (`/api` -> Laravel), so this is a
 * same-origin call in development. `withCredentials` is on so Sanctum session
 * cookies work once auth lands in Phase 1.
 */
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
  },
})
