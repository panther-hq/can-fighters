import { useHealth } from './features/health/useHealth'
import './App.css'

const CHECK_LABELS: Record<string, string> = {
  database: 'PostgreSQL',
  cache: 'Redis',
}

function App() {
  const { data, isLoading, isError, refetch, isFetching } = useHealth()

  const apiReachable = !isError && !isLoading
  const overall = isLoading
    ? 'checking'
    : isError
      ? 'unreachable'
      : (data?.status ?? 'unknown')

  return (
    <main className="shell">
      <header className="shell__head">
        <span className="shell__logo" aria-hidden="true">
          🥫
        </span>
        <div>
          <h1>Can Fighters</h1>
          <p className="shell__tag">Phase 0 — running skeleton</p>
        </div>
      </header>

      <section className={`status status--${overall}`}>
        <div className="status__row">
          <span className="status__dot" aria-hidden="true" />
          <span className="status__label">
            API {overall === 'ok' ? 'online' : overall}
          </span>
          <button
            type="button"
            className="status__refresh"
            onClick={() => refetch()}
            disabled={isFetching}
          >
            {isFetching ? 'Checking…' : 'Recheck'}
          </button>
        </div>

        <ul className="status__checks">
          <li>
            <span>Frontend (React + Vite)</span>
            <span className="ok">up</span>
          </li>
          <li>
            <span>API (Laravel)</span>
            <span className={apiReachable ? 'ok' : 'bad'}>
              {apiReachable ? 'up' : 'down'}
            </span>
          </li>
          {data &&
            Object.entries(data.checks).map(([key, value]) => (
              <li key={key}>
                <span>{CHECK_LABELS[key] ?? key}</span>
                <span className={value ? 'ok' : 'bad'}>
                  {value ? 'up' : 'down'}
                </span>
              </li>
            ))}
        </ul>

        {data && (
          <p className="status__meta">
            {data.service} · checked {new Date(data.time).toLocaleTimeString()}
          </p>
        )}
        {isError && (
          <p className="status__meta status__meta--bad">
            Could not reach <code>/api/health</code>. Is the API container up?
          </p>
        )}
      </section>

      <p className="hint">
        Next: Phase 1 — auth, player profile and <code>/api/game/bootstrap</code>.
      </p>
    </main>
  )
}

export default App
