import { useCollection } from './hooks'

function Bar({ value, max, label }: { value: number; max: number; label: string }) {
  const pct = max > 0 ? Math.min(100, (value / max) * 100) : 0
  return (
    <div className="coll__row">
      <span className="coll__label">{label}</span>
      <span className="coll__track">
        <span className="coll__fill" style={{ width: `${pct}%` }} />
      </span>
      <span className="coll__value">
        {value}/{max}
      </span>
    </div>
  )
}

export function CollectionPanel() {
  const collection = useCollection()
  if (collection.isLoading || !collection.data) return null

  const c = collection.data

  return (
    <section className="panel coll">
      <h2>Kolekcja</h2>
      <Bar value={c.ingredientsFound} max={c.ingredientsTotal} label="Składniki" />
      <Bar value={c.regionsCleared} max={c.regionsTotal} label="Regiony" />
      <ul className="kv">
        <li>
          <span>Wojownicy</span>
          <span>{c.fighters}</span>
        </li>
        <li>
          <span>Najlepsza seria dni</span>
          <span>{c.bestDailyStreak}</span>
        </li>
        <li>
          <span>Arena</span>
          <span>
            {c.league} · {c.arenaRating}
          </span>
        </li>
      </ul>
    </section>
  )
}
