import type { AuthUser } from '../auth/types'
import { useLogout } from '../auth/useAuth'
import { useBootstrap } from './useBootstrap'

export function Dashboard({ user }: { user: AuthUser }) {
  const logout = useLogout()
  const bootstrap = useBootstrap()

  const profile = bootstrap.data?.player.profile ?? user.profile
  const coins = bootstrap.data?.currencies.coins ?? user.profile.coins

  return (
    <div className="dash">
      <header className="dash__head">
        <div>
          <h1>{user.name}</h1>
          <p className="dash__sub">{user.email}</p>
        </div>
        <button
          type="button"
          className="btn"
          onClick={() => logout.mutate()}
          disabled={logout.isPending}
        >
          {logout.isPending ? 'Logging out…' : 'Log out'}
        </button>
      </header>

      <section className="stats">
        <div className="stat">
          <span className="stat__value">{profile.level}</span>
          <span className="stat__label">Level</span>
        </div>
        <div className="stat">
          <span className="stat__value">{profile.xp}</span>
          <span className="stat__label">XP</span>
        </div>
        <div className="stat">
          <span className="stat__value">{coins}</span>
          <span className="stat__label">Coins</span>
        </div>
        <div className="stat">
          <span className="stat__value">{profile.rating}</span>
          <span className="stat__label">Rating</span>
        </div>
      </section>

      <section className="panel">
        <h2>Bootstrap</h2>
        {bootstrap.isLoading && <p className="muted">Loading game state…</p>}
        {bootstrap.isError && (
          <p className="muted muted--bad">Could not load /api/game/bootstrap.</p>
        )}
        {bootstrap.data && (
          <ul className="kv">
            <li>
              <span>Team</span>
              <span>{bootstrap.data.team ? 'set' : 'none yet'}</span>
            </li>
            <li>
              <span>Cans</span>
              <span>{bootstrap.data.cans.length}</span>
            </li>
            <li>
              <span>Notifications</span>
              <span>{bootstrap.data.notifications.length}</span>
            </li>
            <li>
              <span>Server time</span>
              <span>{new Date(bootstrap.data.serverTime).toLocaleTimeString()}</span>
            </li>
          </ul>
        )}
      </section>

      <p className="hint">
        Next: Phase 2 — ingredients, cans and opening them.
      </p>
    </div>
  )
}
