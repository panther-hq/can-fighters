import { isAxiosError } from 'axios'
import { useState } from 'react'
import { BattleReplay } from '../battle/BattleReplay'
import { TeamView } from '../teams/TeamView'
import {
  useArena,
  useArenaHistory,
  useChallenge,
  useOpponents,
  useRanking,
} from './hooks'
import type { ChallengeOutcome } from './types'

type Panel = 'defense' | 'fight' | 'ranking' | 'history'

const PANELS: { id: Panel; label: string }[] = [
  { id: 'defense', label: 'Obrona' },
  { id: 'fight', label: 'Walka' },
  { id: 'ranking', label: 'Ranking' },
  { id: 'history', label: 'Historia' },
]

function errorText(error: unknown): string {
  if (isAxiosError<{ message?: string }>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return 'Nie udało się rozegrać walki.'
}

export function ArenaView() {
  const arena = useArena()
  const [panel, setPanel] = useState<Panel>('fight')
  const [outcome, setOutcome] = useState<ChallengeOutcome | null>(null)

  if (outcome) {
    return (
      <BattleReplay
        events={outcome.result.events}
        won={outcome.won}
        heading="WYGRANA W ARENIE"
        onDone={() => setOutcome(null)}
      >
        <p className="result__stars">
          Ranking {outcome.rating.after} (
          {outcome.rating.delta >= 0 ? '+' : ''}
          {outcome.rating.delta})
        </p>
      </BattleReplay>
    )
  }

  return (
    <section className="arena">
      <p className="arena__summary">
        {arena.data
          ? `${arena.data.league} · ${arena.data.rating} pkt · miejsce ${arena.data.rank}`
          : '…'}
      </p>

      <nav className="tabs" role="tablist">
        {PANELS.map((p) => (
          <button
            key={p.id}
            type="button"
            role="tab"
            aria-selected={panel === p.id}
            className={panel === p.id ? 'is-active' : ''}
            onClick={() => setPanel(p.id)}
          >
            {p.label}
          </button>
        ))}
      </nav>

      {panel === 'defense' && (
        <>
          <p className="muted">Drużyna, którą inni gracze będą atakować.</p>
          <TeamView type="defense" />
        </>
      )}
      {panel === 'fight' && <OpponentsPanel onOutcome={setOutcome} errorText={errorText} />}
      {panel === 'ranking' && <RankingPanel />}
      {panel === 'history' && <HistoryPanel />}
    </section>
  )
}

function OpponentsPanel({
  onOutcome,
  errorText,
}: {
  onOutcome: (o: ChallengeOutcome) => void
  errorText: (e: unknown) => string
}) {
  const opponents = useOpponents()
  const challenge = useChallenge()

  if (opponents.isLoading) return <p className="muted">Szukanie przeciwników…</p>

  const list = opponents.data ?? []
  if (list.length === 0) {
    return <p className="muted">Brak przeciwników z drużyną obronną.</p>
  }

  return (
    <>
      <ul className="cards">
        {list.map((o) => (
          <li key={o.id} className="card">
            <span className="card__icon" aria-hidden="true">
              ⚔️
            </span>
            <div className="card__body">
              <span className="card__name">{o.name}</span>
              <span className="muted">
                {o.league} · {o.rating} pkt · moc {o.teamPower}
              </span>
            </div>
            <button
              type="button"
              className="btn btn--primary"
              disabled={challenge.isPending}
              onClick={() => challenge.mutate(o.id, { onSuccess: onOutcome })}
            >
              {challenge.isPending && challenge.variables === o.id ? 'Walka…' : 'Wyzwij'}
            </button>
          </li>
        ))}
      </ul>
      {challenge.isError && (
        <p className="muted muted--bad">{errorText(challenge.error)}</p>
      )}
    </>
  )
}

function RankingPanel() {
  const ranking = useRanking()
  if (ranking.isLoading) return <p className="muted">Wczytywanie rankingu…</p>

  return (
    <ol className="rank">
      {(ranking.data?.top ?? []).map((row) => (
        <li key={row.rank} className="rank__row">
          <span className="rank__pos">{row.rank}.</span>
          <span className="rank__name">{row.name}</span>
          <span className="muted">{row.league}</span>
          <span className="rank__rating">{row.rating}</span>
        </li>
      ))}
      {ranking.data && (
        <li className="rank__row rank__row--me">
          <span className="rank__pos">{ranking.data.me.rank}.</span>
          <span className="rank__name">Ty</span>
          <span className="muted">{ranking.data.me.league}</span>
          <span className="rank__rating">{ranking.data.me.rating}</span>
        </li>
      )}
    </ol>
  )
}

function HistoryPanel() {
  const history = useArenaHistory()
  if (history.isLoading) return <p className="muted">Wczytywanie historii…</p>

  const rows = history.data ?? []
  if (rows.length === 0) return <p className="muted">Brak walk w arenie.</p>

  return (
    <ul className="kv">
      {rows.map((row) => (
        <li key={row.battleId}>
          <span>
            {row.role === 'attack' ? '⚔️' : '🛡️'} {row.opponent}
          </span>
          <span className={row.won ? 'ok' : 'bad'}>
            {row.won ? 'wygrana' : 'przegrana'} ({row.ratingDelta >= 0 ? '+' : ''}
            {row.ratingDelta})
          </span>
        </li>
      ))}
    </ul>
  )
}
