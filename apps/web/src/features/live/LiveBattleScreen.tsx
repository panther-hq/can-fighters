import { useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { useEffect, useState } from 'react'
import {
  useLiveBattle,
  usePollResolve,
  useSubmitAction,
} from './hooks'
import type { LiveUnit } from './types'

function energyCost(cooldown: number): number {
  return Math.min(10, Math.max(1, cooldown * 3))
}

function skillUsable(unit: LiveUnit, slot: number, round: number): boolean {
  const skill = unit.skills.find((s) => s.slot === slot)
  if (!skill) return false
  const ready = round >= (unit.cooldowns?.[String(slot)] ?? 0)
  return ready && unit.energy >= energyCost(skill.params.cooldown ?? 2)
}

export function LiveBattleScreen({
  battleId,
  onExit,
}: {
  battleId: number
  onExit: () => void
}) {
  const queryClient = useQueryClient()
  const battleQuery = useLiveBattle(battleId)
  const submit = useSubmitAction(battleId)
  const poll = usePollResolve(battleId)

  const [actorId, setActorId] = useState<number | null>(null)
  const [mode, setMode] = useState<'attack' | 0 | 1 | null>(null)
  const [log, setLog] = useState<string[]>([])

  const battle = battleQuery.data
  const waitingForOpponent = submit.data?.status === 'waiting'

  function onRoundResolved(events?: { type: string; [k: string]: unknown }[]) {
    if (events?.length) {
      setLog((prev) => [...prev.slice(-6), ...summarise(events)])
    }
    setActorId(null)
    setMode(null)
    queryClient.invalidateQueries({ queryKey: ['live', battleId] })
  }

  // While waiting for the opponent, poll the timeout-resolver.
  useEffect(() => {
    if (!waitingForOpponent || battle?.status !== 'active') return
    const t = setInterval(
      () => poll.mutate(undefined, { onSuccess: (r) => onRoundResolved(r.events) }),
      3500,
    )
    return () => clearInterval(t)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [waitingForOpponent, battle?.status])

  if (battleQuery.isLoading || !battle) {
    return <p className="muted">Wczytywanie walki…</p>
  }

  if (battle.status === 'finished') {
    const won = battle.winner === battle.yourTeam
    return (
      <div className={`result result--${won ? 'win' : 'lose'}`}>
        <p className="result__title">{won ? 'WYGRANA' : 'PORAŻKA'}</p>
        <button type="button" className="btn btn--primary" onClick={onExit}>
          Wróć
        </button>
      </div>
    )
  }

  const mine = battle.units.filter((u) => u.team === battle.yourTeam)
  const foes = battle.units.filter((u) => u.team !== battle.yourTeam)
  const actor = mine.find((u) => u.id === actorId) ?? null

  function chooseTarget(targetId: number) {
    if (!actor || mode === null) return
    submit.mutate(
      {
        actorId: actor.id,
        type: mode === 'attack' ? 'attack' : 'skill',
        slot: mode === 'attack' ? undefined : mode,
        targetId,
      },
      { onSuccess: (r) => onRoundResolved(r.status === 'waiting' ? undefined : r.events) },
    )
  }

  return (
    <section className="live">
      <p className="live__round">
        Runda {battle.round}
        {waitingForOpponent && ' · czekam na przeciwnika…'}
      </p>

      <div className="live__side">
        {foes.map((u) => (
          <UnitChip
            key={u.id}
            unit={u}
            enemy
            selectable={actor !== null && mode !== null}
            onClick={() => chooseTarget(u.id)}
          />
        ))}
      </div>

      <ul className="live__log">
        {log.map((line, i) => (
          <li key={`${line}-${i}`}>{line}</li>
        ))}
      </ul>

      <div className="live__side">
        {mine.map((u) => (
          <UnitChip
            key={u.id}
            unit={u}
            selected={u.id === actorId}
            selectable={!waitingForOpponent && u.hp > 0}
            onClick={() => {
              setActorId(u.id)
              setMode(null)
            }}
          />
        ))}
      </div>

      {actor && !waitingForOpponent && (
        <div className="live__actions">
          <button
            type="button"
            className={`btn ${mode === 'attack' ? 'btn--primary' : ''}`}
            onClick={() => setMode('attack')}
          >
            Atak
          </button>
          {actor.skills.map((s) => (
            <button
              key={s.slot}
              type="button"
              className={`btn ${mode === s.slot ? 'btn--primary' : ''}`}
              disabled={!skillUsable(actor, s.slot, battle.round)}
              onClick={() => setMode(s.slot as 0 | 1)}
            >
              {s.family}
            </button>
          ))}
          {mode !== null && <span className="muted">Wybierz cel ↑</span>}
        </div>
      )}

      {submit.isError && (
        <p className="muted muted--bad">{errorText(submit.error)}</p>
      )}

      <button type="button" className="btn btn--ghost" onClick={onExit}>
        Opuść walkę
      </button>
    </section>
  )
}

function UnitChip({
  unit,
  enemy,
  selected,
  selectable,
  onClick,
}: {
  unit: LiveUnit
  enemy?: boolean
  selected?: boolean
  selectable?: boolean
  onClick: () => void
}) {
  const hpPct = Math.max(0, (unit.hp / unit.maxHp) * 100)
  return (
    <button
      type="button"
      className={`unitchip ${enemy ? 'unitchip--foe' : ''} ${selected ? 'is-selected' : ''}`}
      disabled={!selectable || unit.hp <= 0}
      onClick={onClick}
    >
      <span className="unitchip__name">
        {unit.hp <= 0 ? '💀 ' : ''}
        {unit.name}
      </span>
      <span className="unitchip__bar">
        <span className="unitchip__hp" style={{ width: `${hpPct}%` }} />
      </span>
      <span className="muted">
        {unit.hp}/{unit.maxHp} · ⚡{unit.energy}
        {unit.shield > 0 ? ` · 🛡️${unit.shield}` : ''}
      </span>
    </button>
  )
}

function summarise(events: { type: string; [k: string]: unknown }[]): string[] {
  return events.map((e) => {
    switch (e.type) {
      case 'damage':
        return `Cios za ${e.damage}${e.crit ? ' (kryt!)' : ''}`
      case 'heal':
        return `Leczenie +${e.amount}`
      case 'death':
        return 'Wojownik pada'
      case 'skill_used':
        return `Umiejętność: ${e.skill}`
      case 'effect':
        return `Efekt: ${e.effect}`
      case 'stunned':
        return 'Ogłuszony — traci turę'
      default:
        return e.type
    }
  })
}

function errorText(error: unknown): string {
  if (isAxiosError<{ message?: string }>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return 'Nie udało się wykonać akcji.'
}
