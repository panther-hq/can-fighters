import { useState } from 'react'
import { isAxiosError } from 'axios'
import { BattleReplay } from '../battle/BattleReplay'
import { useFightStage, useStages } from './hooks'
import type { FightOutcome, PveStage } from './types'

function errorText(error: unknown): string {
  if (isAxiosError<{ message?: string }>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return 'Nie udało się rozpocząć walki.'
}

export function CampaignView() {
  const stages = useStages()
  const fight = useFightStage()
  const [outcome, setOutcome] = useState<FightOutcome | null>(null)

  if (outcome) {
    const { rewards } = outcome
    return (
      <BattleReplay
        events={outcome.result.events}
        won={outcome.won}
        onDone={() => setOutcome(null)}
      >
        {outcome.won && (
          <>
            <p className="result__stars">
              {'★'.repeat(outcome.stars)}
              {'☆'.repeat(3 - outcome.stars)}
            </p>
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
              {rewards.equipment.map((item) => (
                <li key={item.id}>
                  <span>
                    {item.icon} {item.name}
                  </span>
                  <span>nowy</span>
                </li>
              ))}
            </ul>
          </>
        )}
      </BattleReplay>
    )
  }

  if (stages.isLoading) {
    return <p className="muted">Wczytywanie kampanii…</p>
  }
  if (stages.isError) {
    return <p className="muted muted--bad">Nie udało się wczytać kampanii.</p>
  }

  const list = stages.data ?? []

  function start(stage: PveStage) {
    fight.mutate(stage.slug, { onSuccess: setOutcome })
  }

  return (
    <section className="campaign">
      <h2 className="campaign__region">Kuchnia</h2>
      <ol className="stages">
        {list.map((stage) => (
          <li
            key={stage.slug}
            className={`stage ${stage.unlocked ? '' : 'stage--locked'} ${stage.isBoss ? 'stage--boss' : ''}`}
          >
            <div className="stage__info">
              <span className="stage__name">
                {stage.isBoss ? '👑 ' : ''}
                {stage.order}. {stage.name}
              </span>
              <span className="stage__stars">
                {stage.unlocked
                  ? `${'★'.repeat(stage.stars)}${'☆'.repeat(3 - stage.stars)}`
                  : '🔒'}
              </span>
              <span className="muted">
                {stage.enemies.length} przeciwn. · +{stage.rewards.coins} monet
              </span>
            </div>
            <button
              type="button"
              className="btn btn--primary"
              disabled={!stage.unlocked || fight.isPending}
              onClick={() => start(stage)}
            >
              {fight.isPending && fight.variables === stage.slug ? 'Walka…' : 'Walcz'}
            </button>
          </li>
        ))}
      </ol>
      {fight.isError && <p className="muted muted--bad">{errorText(fight.error)}</p>}
    </section>
  )
}
