import { useClaimDaily, useDaily } from './hooks'
import { rewardLabel } from './types'

export function DailyCard() {
  const daily = useDaily()
  const claim = useClaimDaily()

  if (daily.isLoading || !daily.data) return null

  const { canClaim, streak, day, reward, ladder } = daily.data

  return (
    <section className="daily">
      <div className="daily__head">
        <div>
          <h2>Codzienna nagroda</h2>
          <p className="muted">
            Seria: {streak} dni · dziś dzień {day}/7
          </p>
        </div>
        <button
          type="button"
          className="btn btn--primary"
          disabled={!canClaim || claim.isPending}
          onClick={() => claim.mutate()}
        >
          {claim.isPending
            ? 'Odbieranie…'
            : canClaim
              ? 'Odbierz'
              : 'Odebrane dziś'}
        </button>
      </div>

      <ol className="daily__ladder">
        {Object.entries(ladder).map(([d, r]) => {
          const dayNum = Number(d)
          const done = !canClaim ? dayNum <= day : dayNum < day
          return (
            <li
              key={d}
              className={`daily__day ${done ? 'is-done' : ''} ${dayNum === day && canClaim ? 'is-next' : ''}`}
              title={rewardLabel(r)}
            >
              {r.type === 'can' ? '🥫' : r.type === 'equipment' ? '🛠️' : r.type.startsWith('ingredient') ? '🧪' : '🪙'}
            </li>
          )
        })}
      </ol>

      {claim.isSuccess && (
        <p className="daily__got">Zgarnięto: {rewardLabel(claim.data.reward)}</p>
      )}
      {daily.data.canClaim && (
        <p className="muted">Dzisiaj: {rewardLabel(reward)}</p>
      )}
    </section>
  )
}
