import type Phaser from 'phaser'
import { type ReactNode, useEffect, useRef, useState } from 'react'
import type { BattleEvent } from '../pve/types'
import { startBattleGame } from './BattleScene'

export function BattleReplay({
  events,
  won,
  onDone,
  heading,
  children,
}: {
  events: BattleEvent[]
  won: boolean
  onDone: () => void
  heading?: string
  children?: ReactNode
}) {
  const hostRef = useRef<HTMLDivElement>(null)
  const gameRef = useRef<Phaser.Game | null>(null)
  const [finished, setFinished] = useState(false)
  const [fast, setFast] = useState(false)

  useEffect(() => {
    if (!hostRef.current) return
    setFinished(false)

    const game = startBattleGame(hostRef.current, {
      events,
      speed: fast ? 120 : 430,
      onComplete: () => setFinished(true),
    })
    gameRef.current = game

    return () => {
      game.destroy(true)
      gameRef.current = null
    }
  }, [events, fast])

  return (
    <div className="replay">
      <div className="replay__stage" ref={hostRef} />

      <div className="replay__controls">
        {!finished && (
          <button
            type="button"
            className="btn btn--ghost"
            onClick={() => setFast((v) => !v)}
          >
            {fast ? 'Normalnie' : 'Przyspiesz'}
          </button>
        )}
      </div>

      {finished && (
        <div className={`result result--${won ? 'win' : 'lose'}`}>
          <p className="result__title">
            {won ? (heading ?? 'ZWYCIĘSTWO') : 'PORAŻKA'}
          </p>
          {children}
          <button type="button" className="btn btn--primary" onClick={onDone}>
            Wróć
          </button>
        </div>
      )}
    </div>
  )
}
