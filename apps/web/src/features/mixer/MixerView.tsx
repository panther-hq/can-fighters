import { isAxiosError } from 'axios'
import { useMemo, useState } from 'react'
import { FighterCard } from '../fighters/FighterCard'
import { useInventory } from '../inventory/hooks'
import { useMix, useMixPreview, useMixStatus } from './hooks'
import type { MixIngredient } from './types'

function errorMessage(error: unknown): string {
  if (isAxiosError<{ message?: string }>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return 'Nie udało się rozpocząć miksowania.'
}

const MIN = 2
const MAX = 6

export function MixerView() {
  const inventory = useInventory()
  const preview = useMixPreview()
  const mix = useMix()
  const [selected, setSelected] = useState<Record<string, number>>({})
  const [activeMixId, setActiveMixId] = useState<number | null>(null)

  const status = useMixStatus(activeMixId)
  const revealed =
    status.data && status.data.status !== 'processing' ? status.data : null

  const ingredients = inventory.data?.ingredients ?? []
  const total = useMemo(
    () => Object.values(selected).reduce((sum, n) => sum + n, 0),
    [selected],
  )
  const canMix = total >= MIN && total <= MAX && !mix.isPending && activeMixId === null

  const picked: MixIngredient[] = Object.entries(selected)
    .filter(([, quantity]) => quantity > 0)
    .map(([slug, quantity]) => ({ slug, quantity }))

  function setQty(slug: string, quantity: number, max: number) {
    const clamped = Math.max(0, Math.min(quantity, max))
    setSelected((current) => ({ ...current, [slug]: clamped }))
    preview.reset()
  }

  function startMix() {
    mix.mutate(picked, {
      onSuccess: (result) => {
        setActiveMixId(result.mixId)
        setSelected({})
        preview.reset()
      },
    })
  }

  function reset() {
    setActiveMixId(null)
    mix.reset()
  }

  if (revealed) {
    return (
      <section className="mixer-result">
        {revealed.status === 'completed' && revealed.fighter ? (
          <>
            <p className="mixer-result__banner">NOWY WOJOWNIK!</p>
            <FighterCard fighter={revealed.fighter} />
          </>
        ) : (
          <p className="muted muted--bad">
            Miksowanie się nie powiodło{revealed.error ? `: ${revealed.error}` : '.'}
          </p>
        )}
        <button type="button" className="btn btn--primary" onClick={reset}>
          Miksuj dalej
        </button>
      </section>
    )
  }

  if (activeMixId !== null) {
    return (
      <section className="mixer-progress">
        <span className="mixer-progress__spinner" aria-hidden="true">
          🌀
        </span>
        <p>Mikser pracuje…</p>
      </section>
    )
  }

  if (inventory.isLoading) {
    return <p className="muted">Wczytywanie składników…</p>
  }

  if (ingredients.length === 0) {
    return (
      <p className="muted">
        Nie masz składników. Otwórz puszkę w zakładce „Puszki”.
      </p>
    )
  }

  return (
    <section className="mixer">
      <ul className="picker">
        {ingredients.map((ingredient) => {
          const value = selected[ingredient.slug] ?? 0
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
                  onClick={() => setQty(ingredient.slug, value - 1, ingredient.quantity)}
                  disabled={value === 0}
                  aria-label={`Mniej ${ingredient.name}`}
                >
                  −
                </button>
                <span>{value}</span>
                <button
                  type="button"
                  onClick={() => setQty(ingredient.slug, value + 1, ingredient.quantity)}
                  disabled={value >= ingredient.quantity || total >= MAX}
                  aria-label={`Więcej ${ingredient.name}`}
                >
                  +
                </button>
              </div>
            </li>
          )
        })}
      </ul>

      <p className="mixer__count muted">
        Wybrano {total} / {MAX} (min. {MIN})
      </p>

      {preview.data && (
        <div className="mixer__preview">
          <b>{preview.data.name}</b> — {preview.data.primaryClass}
          {preview.data.secondaryClass && ` / ${preview.data.secondaryClass}`},{' '}
          {preview.data.rarity}
        </div>
      )}

      <div className="mixer__actions">
        <button
          type="button"
          className="btn"
          onClick={() => preview.mutate(picked)}
          disabled={total < MIN || total > MAX || preview.isPending}
        >
          {preview.isPending ? 'Podgląd…' : 'Podgląd'}
        </button>
        <button
          type="button"
          className="btn btn--primary"
          onClick={startMix}
          disabled={!canMix}
        >
          {mix.isPending ? 'Miksowanie…' : 'MIKSUJ'}
        </button>
      </div>

      {mix.isError && (
        <p className="muted muted--bad">{errorMessage(mix.error)}</p>
      )}
    </section>
  )
}
