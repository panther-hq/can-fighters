import { isAxiosError } from 'axios'
import { useState } from 'react'
import { BattleReplay } from '../battle/BattleReplay'
import { useRegions, useStartRun } from './hooks'
import { OverworldView } from './OverworldView'
import { type MoveResult, type Region, rewardText } from './types'

function errorText(error: unknown): string {
  if (isAxiosError<{ message?: string }>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return 'Coś poszło nie tak.'
}

export function CampaignView() {
  const regions = useRegions()
  const start = useStartRun()
  // Kept here (not in OverworldView) so a lost battle — which ends the run and
  // unmounts the map — still shows its replay + result.
  const [result, setResult] = useState<MoveResult | null>(null)

  if (regions.isLoading) return <p className="muted">Wczytywanie mapy…</p>
  if (regions.isError) return <p className="muted muted--bad">Nie udało się wczytać mapy.</p>

  const run = regions.data?.run ?? null
  const list = regions.data?.regions ?? []

  if (result) {
    return <NodeOutcome result={result} onDone={() => setResult(null)} />
  }

  if (run && run.status === 'active') {
    return <OverworldView key={run.runId} view={run} onResolve={setResult} />
  }

  return (
    <section className="campaign">
      {run && run.status === 'cleared' && (
        <p className="muted">Ostatnia wyprawa: region ukończony! 🎉</p>
      )}
      {run && run.status === 'abandoned' && (
        <p className="muted muted--bad">Ostatnia wyprawa przerwana.</p>
      )}

      <h2 className="campaign__region">Wybierz region</h2>
      <ul className="regions">
        {list.map((region: Region) => (
          <li key={region.slug} className={`region ${region.unlocked ? '' : 'region--locked'}`}>
            <div className="region__info">
              <span className="region__name">
                {region.order}. {region.name}
              </span>
              <span className="muted">
                {region.unlocked
                  ? region.timesCleared > 0
                    ? `ukończono ${region.timesCleared}×`
                    : 'nowy'
                  : '🔒 zablokowany'}
              </span>
            </div>
            <button
              type="button"
              className="btn btn--primary"
              disabled={!region.unlocked || start.isPending}
              onClick={() => start.mutate(region.slug)}
            >
              {start.isPending && start.variables === region.slug ? 'Rusza…' : 'Wyprawa'}
            </button>
          </li>
        ))}
      </ul>
      {start.isError && <p className="muted muted--bad">{errorText(start.error)}</p>}
    </section>
  )
}

const EVENT_TITLE: Record<string, string> = {
  skarb: 'Skarb!',
  trening: 'Trening',
  pulapka: 'Pułapka!',
  zasadzka: 'Zasadzka!',
  handlarz: 'Handlarz',
}

function NodeOutcome({
  result,
  onDone,
}: {
  result: MoveResult
  onDone: () => void
}) {
  if (result.result?.events) {
    return (
      <BattleReplay
        events={result.result.events}
        won={result.won ?? false}
        heading="WYGRANA"
        onDone={onDone}
      >
        {result.won && result.rewards && (
          <ul className="result__rewards">
            {result.rewards.map((reward, i) => (
              <li key={i}>
                <span>{rewardText(reward)}</span>
              </li>
            ))}
          </ul>
        )}
        {result.runEnded && <p className="muted muted--bad">Wyprawa przerwana.</p>}
      </BattleReplay>
    )
  }

  return (
    <div className="node-result">
      <p className="node-result__title">
        {result.event
          ? (EVENT_TITLE[result.event] ?? 'Zdarzenie')
          : result.type === 'loot'
            ? 'Skrzynia'
            : 'Zdarzenie'}
      </p>
      <ul className="result__rewards">
        {(result.rewards ?? []).map((reward, i) => (
          <li key={i}>
            <span>{rewardText(reward)}</span>
          </li>
        ))}
      </ul>
      <button type="button" className="btn btn--primary" onClick={onDone}>
        Dalej
      </button>
    </div>
  )
}
