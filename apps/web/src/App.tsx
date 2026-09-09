import { AuthForm } from './features/auth/AuthForm'
import { useMe } from './features/auth/useAuth'
import { Dashboard } from './features/game/Dashboard'
import './App.css'

function App() {
  const { data: user, isLoading, isError, refetch } = useMe()

  return (
    <main className="shell">
      <header className="shell__head">
        <span className="shell__logo" aria-hidden="true">
          🥫
        </span>
        <div>
          <h1>Can Fighters</h1>
          <p className="shell__tag">Phase 1 — accounts &amp; bootstrap</p>
        </div>
      </header>

      {isLoading && <p className="muted">Checking session…</p>}

      {isError && !isLoading && (
        <div className="panel">
          <p className="muted muted--bad">Could not reach the API.</p>
          <button type="button" className="btn" onClick={() => refetch()}>
            Retry
          </button>
        </div>
      )}

      {!isLoading && !isError && (user ? <Dashboard user={user} /> : <AuthForm />)}
    </main>
  )
}

export default App
