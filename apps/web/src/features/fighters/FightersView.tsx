import { FighterCard } from './FighterCard'
import { useFighters } from './hooks'

export function FightersView() {
  const fighters = useFighters()

  if (fighters.isLoading) {
    return <p className="muted">Wczytywanie wojowników…</p>
  }

  if (fighters.isError) {
    return <p className="muted muted--bad">Nie udało się wczytać wojowników.</p>
  }

  const list = fighters.data ?? []

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
        <FighterCard key={fighter.id} fighter={fighter} />
      ))}
    </div>
  )
}
