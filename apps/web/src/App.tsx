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
          <p className="shell__tag">Puszkowi wojownicy</p>
        </div>
      </header>

      {isLoading && <p className="muted">Sprawdzanie sesji…</p>}

      {isError && !isLoading && (
        <div className="panel">
          <p className="muted muted--bad">Nie można połączyć się z serwerem.</p>
          <button type="button" className="btn" onClick={() => refetch()}>
            Ponów
          </button>
        </div>
      )}

      {!isLoading && !isError && (user ? <Dashboard user={user} /> : <AuthForm />)}
    </main>
  )
}

export default App
