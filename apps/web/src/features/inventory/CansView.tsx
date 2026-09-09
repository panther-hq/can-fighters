import { useState } from 'react'
import { useInventory, useOpenCan } from './hooks'
import type { OpenCanResult } from './types'

export function CansView() {
  const inventory = useInventory()
  const openCan = useOpenCan()
  const [lastOpening, setLastOpening] = useState<OpenCanResult | null>(null)

  const cans = inventory.data?.cans ?? []
  const total = cans.reduce((sum, can) => sum + can.quantity, 0)

  function open(canId: number) {
    openCan.mutate(canId, { onSuccess: setLastOpening })
  }

  if (inventory.isLoading) {
    return <p className="muted">Wczytywanie puszek…</p>
  }

  return (
    <section>
      {lastOpening && (
        <div className="reveal">
          <h3>Wypadło z puszki</h3>
          <ul className="reveal__items">
            {lastOpening.received.map((item) => (
              <li key={item.slug}>
                <span className="reveal__icon" aria-hidden="true">
                  {item.icon}
                </span>
                {item.name}
                {item.quantity > 1 && <b> ×{item.quantity}</b>}
              </li>
            ))}
          </ul>
          <button
            type="button"
            className="btn btn--ghost"
            onClick={() => setLastOpening(null)}
          >
            OK
          </button>
        </div>
      )}

      {total === 0 ? (
        <p className="muted">Nie masz żadnych puszek.</p>
      ) : (
        <ul className="cards">
          {cans
            .filter((can) => can.quantity > 0)
            .map((can) => (
              <li key={can.id} className="card">
                <span className="card__icon" aria-hidden="true">
                  {can.icon}
                </span>
                <div className="card__body">
                  <span className="card__name">{can.name}</span>
                  <span className="muted">Posiadasz: {can.quantity}</span>
                </div>
                <button
                  type="button"
                  className="btn btn--primary"
                  onClick={() => open(can.id)}
                  disabled={openCan.isPending}
                >
                  {openCan.isPending ? 'Otwieranie…' : 'Otwórz'}
                </button>
              </li>
            ))}
        </ul>
      )}

      {openCan.isError && (
        <p className="muted muted--bad">Nie udało się otworzyć puszki.</p>
      )}
    </section>
  )
}
