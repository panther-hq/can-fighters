import { useState } from 'react'
import { ArenaView } from '../arena/ArenaView'
import type { AuthUser } from '../auth/types'
import { useLogout } from '../auth/useAuth'
import { DailyCard } from '../economy/DailyCard'
import { ShopView } from '../economy/ShopView'
import { FightersView } from '../fighters/FightersView'
import { CansView } from '../inventory/CansView'
import { InventoryView } from '../inventory/InventoryView'
import { MixerView } from '../mixer/MixerView'
import { CampaignView } from '../pve/CampaignView'
import { ConnectionBanner } from '../realtime/ConnectionBanner'
import { useRealtimeSync } from '../realtime/useRealtimeSync'
import { TeamView } from '../teams/TeamView'
import { useBootstrap } from './useBootstrap'

type Tab =
  | 'panel'
  | 'cans'
  | 'inventory'
  | 'mixer'
  | 'fighters'
  | 'team'
  | 'campaign'
  | 'arena'
  | 'shop'

const TABS: { id: Tab; label: string }[] = [
  { id: 'panel', label: 'Panel' },
  { id: 'cans', label: 'Puszki' },
  { id: 'inventory', label: 'Plecak' },
  { id: 'mixer', label: 'Mikser' },
  { id: 'fighters', label: 'Wojownicy' },
  { id: 'team', label: 'Drużyna' },
  { id: 'campaign', label: 'Kampania' },
  { id: 'arena', label: 'Arena' },
  { id: 'shop', label: 'Sklep' },
]

export function Dashboard({ user }: { user: AuthUser }) {
  const logout = useLogout()
  const [tab, setTab] = useState<Tab>('panel')
  const realtime = useRealtimeSync(user.id)

  return (
    <div className="dash">
      <ConnectionBanner realtime={realtime} />

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
          {logout.isPending ? 'Wylogowywanie…' : 'Wyloguj'}
        </button>
      </header>

      <nav className="tabs" role="tablist">
        {TABS.map((entry) => (
          <button
            key={entry.id}
            type="button"
            role="tab"
            aria-selected={tab === entry.id}
            className={tab === entry.id ? 'is-active' : ''}
            onClick={() => setTab(entry.id)}
          >
            {entry.label}
          </button>
        ))}
      </nav>

      {tab === 'panel' && <PanelTab user={user} />}
      {tab === 'cans' && <CansView />}
      {tab === 'inventory' && <InventoryView />}
      {tab === 'mixer' && <MixerView />}
      {tab === 'fighters' && <FightersView />}
      {tab === 'team' && <TeamView />}
      {tab === 'campaign' && <CampaignView />}
      {tab === 'arena' && <ArenaView />}
      {tab === 'shop' && <ShopView />}
    </div>
  )
}

function PanelTab({ user }: { user: AuthUser }) {
  const bootstrap = useBootstrap()
  const profile = bootstrap.data?.player.profile ?? user.profile
  const coins = bootstrap.data?.currencies.coins ?? user.profile.coins

  return (
    <>
      <DailyCard />

      <section className="stats">
        <div className="stat">
          <span className="stat__value">{profile.level}</span>
          <span className="stat__label">Poziom</span>
        </div>
        <div className="stat">
          <span className="stat__value">{profile.xp}</span>
          <span className="stat__label">Dośw.</span>
        </div>
        <div className="stat">
          <span className="stat__value">{coins}</span>
          <span className="stat__label">Monety</span>
        </div>
        <div className="stat">
          <span className="stat__value">{profile.rating}</span>
          <span className="stat__label">Ranking</span>
        </div>
      </section>

      <section className="panel">
        <h2>Stan gry</h2>
        {bootstrap.isLoading && <p className="muted">Wczytywanie stanu gry…</p>}
        {bootstrap.isError && (
          <p className="muted muted--bad">Nie udało się wczytać stanu gry.</p>
        )}
        {bootstrap.data && (
          <ul className="kv">
            <li>
              <span>Drużyna</span>
              <span>
                {bootstrap.data.team
                  ? `${bootstrap.data.team.members.length}/3`
                  : 'brak'}
              </span>
            </li>
            <li>
              <span>Puszki</span>
              <span>
                {bootstrap.data.cans.reduce((sum, can) => sum + can.quantity, 0)}
              </span>
            </li>
            <li>
              <span>Kampania</span>
              <span>
                {bootstrap.data.pve.stagesCleared}/{bootstrap.data.pve.stagesTotal}
              </span>
            </li>
            <li>
              <span>Arena</span>
              <span>
                {bootstrap.data.arena.league} · {bootstrap.data.arena.rating}
              </span>
            </li>
            <li>
              <span>Powiadomienia</span>
              <span>{bootstrap.data.notifications.length}</span>
            </li>
            <li>
              <span>Czas serwera</span>
              <span>
                {new Date(bootstrap.data.serverTime).toLocaleTimeString('pl-PL')}
              </span>
            </li>
          </ul>
        )}
      </section>

      <p className="hint">Wkrótce: Mikser — łączenie składników w wojowników.</p>
    </>
  )
}
