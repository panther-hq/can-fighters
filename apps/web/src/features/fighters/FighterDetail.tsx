import { isAxiosError } from 'axios'
import { useState } from 'react'
import { useInventory } from '../inventory/hooks'
import type { MixIngredient } from '../mixer/types'
import { useMutateFighter, useUpgradeFighter } from './hooks'
import type { Fighter } from './types'
import { classLabel, rarityLabel } from './types'

type NumericStat = 'hp' | 'attack' | 'defense' | 'magic' | 'speed' | 'crit'

const STAT_ROWS: { key: NumericStat; label: string; max: number }[] = [
  { key: 'hp', label: 'HP', max: 260 },
  { key: 'attack', label: 'Atak', max: 45 },
  { key: 'defense', label: 'Obrona', max: 45 },
  { key: 'magic', label: 'Magia', max: 45 },
  { key: 'speed', label: 'Szybkość', max: 45 },
  { key: 'crit', label: 'Kryt %', max: 60 },
]

function errorText(error: unknown, fallback: string): string {
  if (isAxiosError<{ message?: string }>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return fallback
}

export function FighterDetail({
  fighter,
  onClose,
}: {
  fighter: Fighter
  onClose: () => void
}) {
  const upgrade = useUpgradeFighter()
  const mutate = useMutateFighter()
  const inventory = useInventory()
  const [picks, setPicks] = useState<Record<string, number>>({})

  const upgradeCost = fighter.level * 100
  const stats = fighter.stats
  const ingredients = inventory.data?.ingredients ?? []
  const mutateTotal = Object.values(picks).reduce((sum, n) => sum + n, 0)

  const mutateIngredients: MixIngredient[] = Object.entries(picks)
    .filter(([, quantity]) => quantity > 0)
    .map(([slug, quantity]) => ({ slug, quantity }))

  function bump(slug: string, delta: number, max: number) {
    setPicks((current) => {
      const next = Math.max(0, Math.min((current[slug] ?? 0) + delta, max))
      return { ...current, [slug]: next }
    })
  }

  return (
    <div className="detail">
      <button type="button" className="btn btn--ghost detail__back" onClick={onClose}>
        ← Wróć
      </button>

      <h2 className="detail__name">{fighter.name}</h2>
      <p className="detail__meta">
        {classLabel(fighter.primaryClass)}
        {fighter.secondaryClass && ` / ${classLabel(fighter.secondaryClass)}`} ·{' '}
        {rarityLabel(fighter.rarity)} · poziom {fighter.level}
      </p>
      <p className="detail__desc">{fighter.description}</p>

      {stats && (
        <div className="statbars">
          {STAT_ROWS.map((row) => (
            <div key={row.key} className="statbar">
              <span className="statbar__label">{row.label}</span>
              <span className="statbar__track">
                <span
                  className="statbar__fill"
                  style={{ width: `${Math.min(100, (stats[row.key] / row.max) * 100)}%` }}
                />
              </span>
              <span className="statbar__value">{stats[row.key]}</span>
            </div>
          ))}
          <p className="detail__power">
            Moc: {stats.powerScore} / budżet {stats.budget}
          </p>
          {!stats.pvpLegal && (
            <p className="muted muted--bad">Niedozwolony w PvP (za wysoka moc).</p>
          )}
        </div>
      )}

      {fighter.skills && fighter.skills.length > 0 && (
        <ul className="detail__skills">
          {fighter.skills.map((skill) => (
            <li key={skill.slot}>
              <b>{skill.skillFamily}</b>
              {skill.modifier && <em> ({skill.modifier})</em>}
              <span className="muted">
                {' '}
                {Object.entries(skill.parameters)
                  .map(([k, v]) => `${k}: ${v}`)
                  .join(', ')}
              </span>
            </li>
          ))}
        </ul>
      )}

      <div className="detail__actions">
        <button
          type="button"
          className="btn btn--primary"
          onClick={() => upgrade.mutate(fighter.id)}
          disabled={upgrade.isPending}
        >
          {upgrade.isPending ? 'Ulepszanie…' : `Ulepsz (${upgradeCost} monet)`}
        </button>
      </div>
      {upgrade.isError && (
        <p className="muted muted--bad">
          {errorText(upgrade.error, 'Nie udało się ulepszyć.')}
        </p>
      )}

      <section className="detail__mutate">
        <h3>Mutacja</h3>
        <p className="muted">Dorzuć 1–3 składniki, żeby zmienić wojownika.</p>
        {ingredients.length === 0 ? (
          <p className="muted">Brak składników.</p>
        ) : (
          <ul className="picker">
            {ingredients.map((ingredient) => {
              const value = picks[ingredient.slug] ?? 0
              return (
                <li key={ingredient.slug} className="picker__row">
                  <span className="picker__icon" aria-hidden="true">
                    {ingredient.icon}
                  </span>
                  <span className="picker__name">{ingredient.name}</span>
                  <span className="muted">z {ingredient.quantity}</span>
                  <div className="stepper">
                    <button
                      type="button"
                      onClick={() => bump(ingredient.slug, -1, ingredient.quantity)}
                      disabled={value === 0}
                    >
                      −
                    </button>
                    <span>{value}</span>
                    <button
                      type="button"
                      onClick={() => bump(ingredient.slug, 1, ingredient.quantity)}
                      disabled={value >= ingredient.quantity || mutateTotal >= 3}
                    >
                      +
                    </button>
                  </div>
                </li>
              )
            })}
          </ul>
        )}
        <button
          type="button"
          className="btn"
          disabled={mutateTotal < 1 || mutateTotal > 3 || mutate.isPending}
          onClick={() =>
            mutate.mutate(
              { fighterId: fighter.id, ingredients: mutateIngredients },
              { onSuccess: () => setPicks({}) },
            )
          }
        >
          {mutate.isPending ? 'Mutowanie…' : 'Mutuj'}
        </button>
        {mutate.isError && (
          <p className="muted muted--bad">
            {errorText(mutate.error, 'Nie udało się zmutować.')}
          </p>
        )}
      </section>
    </div>
  )
}
