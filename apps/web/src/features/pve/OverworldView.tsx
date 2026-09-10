import { isAxiosError } from 'axios'
import { useCallback, useEffect, useRef, useState } from 'react'
import {
  useAbandonRun,
  useEndDay,
  useLeaveMerchant,
  useMerchantBuy,
  useMoveHero,
} from './hooks'
import { startOverworldGame } from './OverworldScene'
import { type MapObject, type MoveResult, OBJECT_LABEL, type OverworldView as OverworldViewData } from './types'

function errorText(error: unknown): string {
  if (isAxiosError<{ message?: string }>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return 'Coś poszło nie tak.'
}

export function OverworldView({
  view,
  onResolve,
}: {
  view: OverworldViewData
  onResolve: (result: MoveResult) => void
}) {
  const move = useMoveHero()
  const endDay = useEndDay()
  const buy = useMerchantBuy()
  const leave = useLeaveMerchant()
  const abandon = useAbandonRun()

  const [local, setLocal] = useState(view)
  const [walkPath, setWalkPath] = useState<[number, number][] | null>(null)
  const [selected, setSelected] = useState<MapObject | null>(null)

  const hostRef = useRef<HTMLDivElement>(null)

  // A mutable box of the click handler, refreshed after every render (inside an
  // effect, never during render) so the Phaser scene calls the latest closure
  // without the game being torn down and rebuilt on each render.
  const box = useRef({ tile: (_x: number, _y: number) => {}, walkDone: () => {} })
  useEffect(() => {
    box.current.tile = (x: number, y: number) => {
      if (move.isPending || walkPath || local.activeMerchant) return
      const obj = local.objects.find((o) => o.x === x && o.y === y) ?? null
      setSelected(obj)
      move.mutate(
        { x, y },
        {
          onSuccess: (res) => {
            if (res.type === 'battle' || res.type === 'loot' || res.type === 'event') {
              onResolve(res)
              return
            }
            setWalkPath(res.path && res.path.length > 1 ? res.path : null)
            setLocal(res.run)
            setSelected(null)
          },
        },
      )
    }
    box.current.walkDone = () => setWalkPath(null)
  })

  const handleTile = useCallback((x: number, y: number) => box.current.tile(x, y), [])
  const handleWalkDone = useCallback(() => box.current.walkDone(), [])

  useEffect(() => {
    if (!hostRef.current) return
    const game = startOverworldGame(hostRef.current, {
      view: local,
      walkPath,
      onTileClick: handleTile,
      onWalkDone: handleWalkDone,
    })
    return () => {
      game.destroy(true)
    }
  }, [local, walkPath, handleTile, handleWalkDone])

  const busy = move.isPending || walkPath !== null
  const merchant = local.activeMerchant

  return (
    <section className="overworld">
      <div className="overworld__hud">
        <span className="overworld__day">Dzień {local.day}</span>
        <span className="overworld__move" aria-label="punkty ruchu">
          <span className="overworld__movebar">
            <span
              className="overworld__movefill"
              style={{ width: `${(local.movementLeft / local.movementMax) * 100}%` }}
            />
          </span>
          {local.movementLeft}/{local.movementMax}
        </span>
        <button
          type="button"
          className="btn btn--ghost"
          onClick={() => endDay.mutate(undefined, { onSuccess: (res) => setLocal(res.run) })}
          disabled={busy || endDay.isPending || !!merchant}
        >
          Zakończ dzień
        </button>
        <button
          type="button"
          className="btn btn--ghost"
          onClick={() => abandon.mutate()}
          disabled={abandon.isPending}
        >
          Porzuć
        </button>
      </div>

      <div className="overworld__stage" ref={hostRef} />

      {move.isError && <p className="muted muted--bad">{errorText(move.error)}</p>}

      {!merchant && selected && (
        <p className="overworld__tip">
          {selected.kind === 'enemy' || selected.kind === 'boss'
            ? `${selected.elite ? 'Elita' : OBJECT_LABEL[selected.kind]} — ${(selected.enemies ?? [])
                .map((e) => e.name)
                .join(', ')}`
            : OBJECT_LABEL[selected.kind]}
        </p>
      )}
      {!merchant && !selected && (
        <p className="overworld__tip muted">
          Kliknij pole w zasięgu, aby tam podejść. Pola ze złotą ramką to obiekty.
        </p>
      )}

      {merchant && (
        <div className="merchant">
          <h3>Handlarz</h3>
          <ul className="cards">
            {merchant.offers.map((offer) => (
              <li key={offer.id} className={`card ${offer.bought ? 'card--spent' : ''}`}>
                <span className="card__icon" aria-hidden="true">
                  {offer.icon}
                </span>
                <div className="card__body">
                  <span className="card__name">{offer.label}</span>
                  <span className="muted">{offer.price} monet</span>
                </div>
                <button
                  type="button"
                  className="btn btn--primary"
                  disabled={offer.bought || buy.isPending}
                  onClick={() =>
                    buy.mutate(offer.id, { onSuccess: (res) => setLocal(res.run) })
                  }
                >
                  {offer.bought ? 'Kupione' : 'Kup'}
                </button>
              </li>
            ))}
          </ul>
          <button
            type="button"
            className="btn"
            onClick={() => leave.mutate(undefined, { onSuccess: (res) => setLocal(res.run) })}
            disabled={leave.isPending}
          >
            Idź dalej
          </button>
          {buy.isError && <p className="muted muted--bad">{errorText(buy.error)}</p>}
        </div>
      )}
    </section>
  )
}
