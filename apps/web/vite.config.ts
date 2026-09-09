import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  // Inside Docker the API is reachable as `http://api:8000`; on the host it is
  // `http://localhost:8000`. The compose `web` service sets the former.
  const apiTarget = env.VITE_API_PROXY_TARGET ?? 'http://localhost:8000'

  return {
    plugins: [react()],
    server: {
      host: true,
      port: 5173,
      strictPort: true,
      // Same-origin proxy so the SPA can use cookie auth (Sanctum) later
      // without cross-site cookie headaches.
      proxy: {
        '/api': { target: apiTarget, changeOrigin: true },
        '/sanctum': { target: apiTarget, changeOrigin: true },
        '/up': { target: apiTarget, changeOrigin: true },
      },
    },
  }
})
