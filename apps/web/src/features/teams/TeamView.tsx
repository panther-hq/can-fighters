import { isAxiosError } from 'axios'
import { useState } from 'react'
import { useFighters } from '../fighters/hooks'
import { classLabel } from '../fighters/types'
import { useSaveTeam, useTeam } from './hooks'
import { POSITIONS, type Team, type TeamPosition } from './types'

type Slots = Partial<Record<TeamPosition, number>>

function slotsFromTeam(team: Team | null | undefined): Slots {
  const next: Slots = {}
  for (const member of team?.members ?? []) next[member.position] = member.fighter.id
  return next
}

function errorText(error: unknown): string {
  if (isAxiosError<{ message?: string }>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return 'Nie udało się zapisać drużyny.'
}

export function TeamView() {
  const team = useTeam()
  const fighters = useFighters()
  const save = useSaveTeam()

  // Local edits override the saved roster once the user touches something.
  const [edited, setEdited] = useState<Slots | null>(null)
  const [pickingFor, setPickingFor] = useState<TeamPosition | null>(null)

  if (team.isLoading || fighters.isLoading) {
    return <p className="muted">Wczytywanie drużyny…</p>
  }

  const slots = edited ?? slotsFromTeam(team.data)
  const setSlots = (updater: (current: Slots) => Slots) =>
    setEdited((current) => updater(current ?? slotsFromTeam(team.data)))

  const roster = fighters.data ?? []
  const usedIds = new Set(Object.values(slots))

  function assign(position: TeamPosition, fighterId: number) {
    setSlots((current) => ({ ...current, [position]: fighterId }))
    setPickingFor(null)
  }

  function clearSlot(position: TeamPosition) {
    setSlots((current) => {
      const next = { ...current }
      delete next[position]
      return next
    })
  }

  function persist() {
    const members = POSITIONS.filter((p) => slots[p.id]).map((p) => ({
      fighterId: slots[p.id] as number,
      position: p.id,
    }))
    save.mutate(members, { onSuccess: () => setEdited(null) })
  }

  if (roster.length === 0) {
    return (
      <p className="muted">Najpierw zmiksuj wojowników w zakładce „Mikser”.</p>
    )
  }

  return (
    <section className="team">
      <div className="team__slots">
        {POSITIONS.map((position) => {
          const fighter = roster.find((f) => f.id === slots[position.id])
          return (
            <div key={position.id} className="slot">
              <span className="slot__label">{position.label}</span>
              {fighter ? (
                <>
                  <span className="slot__fighter">{fighter.name}</span>
                  <span className="muted">{classLabel(fighter.primaryClass)}</span>
                  <button
                    type="button"
                    className="btn btn--ghost"
                    onClick={() => clearSlot(position.id)}
                  >
                    Usuń
                  </button>
                </>
              ) : (
                <button
                  type="button"
                  className="btn"
                  onClick={() => setPickingFor(position.id)}
                >
                  Wybierz
                </button>
              )}
            </div>
          )
        })}
      </div>

      {pickingFor && (
        <div className="team__picker">
          <p className="muted">
            Wybierz wojownika na pozycję{' '}
            {POSITIONS.find((p) => p.id === pickingFor)?.label}:
          </p>
          <ul className="cards">
            {roster
              .filter((f) => !usedIds.has(f.id) || slots[pickingFor] === f.id)
              .map((f) => (
                <li key={f.id} className="card">
                  <span className="card__icon" aria-hidden="true">
                    ⚔️
                  </span>
                  <div className="card__body">
                    <span className="card__name">{f.name}</span>
                    <span className="muted">
                      {classLabel(f.primaryClass)} · moc {f.stats?.powerScore ?? '—'}
                    </span>
                  </div>
                  <button
                    type="button"
                    className="btn btn--primary"
                    onClick={() => assign(pickingFor, f.id)}
                  >
                    Ustaw
                  </button>
                </li>
              ))}
          </ul>
          <button
            type="button"
            className="btn btn--ghost"
            onClick={() => setPickingFor(null)}
          >
            Anuluj
          </button>
        </div>
      )}

      <button
        type="button"
        className="btn btn--primary team__save"
        onClick={persist}
        disabled={save.isPending || Object.keys(slots).length === 0}
      >
        {save.isPending ? 'Zapisywanie…' : 'Zapisz drużynę'}
      </button>
      {save.isError && <p className="muted muted--bad">{errorText(save.error)}</p>}
      {save.isSuccess && !save.isPending && (
        <p className="muted">Drużyna zapisana.</p>
      )}
    </section>
  )
}
