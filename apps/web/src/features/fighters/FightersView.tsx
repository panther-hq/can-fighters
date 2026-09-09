import { useState } from 'react'
import { FighterCard } from './FighterCard'
import { FighterDetail } from './FighterDetail'
import { useFighters } from './hooks'

export function FightersView() {
  const fighters = useFighters()
  const [selectedId, setSelectedId] = useState<number | null>(null)

  if (fighters.isLoading) {
    return <p className="muted">Wczytywanie wojowników…</p>
  }

  if (fighters.isError) {
    return <p className="muted muted--bad">Nie udało się wczytać wojowników.</p>
  }

  const list = fighters.data ?? []
  const selected = list.find((fighter) => fighter.id === selectedId) ?? null

  if (selected) {
    return <FighterDetail fighter={selected} onClose={() => setSelectedId(null)} />
  }

  if (list.length === 0) {
    return (
      <p className="muted">
        Nie masz jeszcze wojowników. Wejdź w „Mikser” i coś zmiksuj.
      </p>
    )
  }

  return (
    <div className="fighter-list">
      {list.map((fighter) => (
        <button
          key={fighter.id}
          type="button"
          className="fighter-list__item"
          onClick={() => setSelectedId(fighter.id)}
        >
          <FighterCard fighter={fighter} />
        </button>
      ))}
    </div>
  )
}
