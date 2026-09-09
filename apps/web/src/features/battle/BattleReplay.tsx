import type Phaser from 'phaser'
import { useEffect, useRef, useState } from 'react'
import type { FightOutcome } from '../pve/types'
import { startBattleGame } from './BattleScene'

export function BattleReplay({
  outcome,
  onDone,
}: {
  outcome: FightOutcome
  onDone: () => void
}) {
  const hostRef = useRef<HTMLDivElement>(null)
  const gameRef = useRef<Phaser.Game | null>(null)
  const [finished, setFinished] = useState(false)
  const [fast, setFast] = useState(false)

  useEffect(() => {
    if (!hostRef.current) return
    setFinished(false)

    const game = startBattleGame(hostRef.current, {
      events: outcome.result.events,
      speed: fast ? 120 : 430,
      onComplete: () => setFinished(true),
    })
    gameRef.current = game

    return () => {
      game.destroy(true)
      gameRef.current = null
    }
  }, [outcome, fast])

  const { rewards } = outcome

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
        <div className={`result result--${outcome.won ? 'win' : 'lose'}`}>
          <p className="result__title">
            {outcome.won ? 'ZWYCIĘSTWO' : 'PORAŻKA'}
          </p>
          {outcome.won && (
            <>
              <p className="result__stars">{'★'.repeat(outcome.stars)}{'☆'.repeat(3 - outcome.stars)}</p>
              <ul className="result__rewards">
                <li>
                  <span>Monety</span>
                  <span>+{rewards.coins}</span>
                </li>
                <li>
                  <span>Dośw.</span>
                  <span>+{rewards.xp}</span>
                </li>
                {rewards.ingredients.map((item) => (
                  <li key={item.slug}>
                    <span>
                      {item.icon} {item.name}
                    </span>
                    <span>+{item.quantity}</span>
                  </li>
                ))}
                {rewards.cans.map((item) => (
                  <li key={item.slug}>
                    <span>
                      {item.icon} {item.name}
                    </span>
                    <span>+{item.quantity}</span>
                  </li>
                ))}
              </ul>
            </>
          )}
          <button type="button" className="btn btn--primary" onClick={onDone}>
            Wróć
          </button>
        </div>
      )}
    </div>
  )
}
