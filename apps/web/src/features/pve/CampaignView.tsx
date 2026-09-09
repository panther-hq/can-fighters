import { isAxiosError } from 'axios'
import { useState } from 'react'
import { BattleReplay } from '../battle/BattleReplay'
import {
  useAbandonRun,
  useAdvanceRun,
  useMerchantBuy,
  useRegions,
  useStartRun,
  useVisitNode,
} from './hooks'
import {
  NODE_ICON,
  NODE_LABEL,
  type NodeType,
  type Region,
  rewardText,
  type RunView,
  type VisitResult,
} from './types'

function errorText(error: unknown): string {
  if (isAxiosError<{ message?: string }>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return 'Coś poszło nie tak.'
}

export function CampaignView() {
  const regions = useRegions()
  const start = useStartRun()
  const visit = useVisitNode()
  // Held here (not in RunMap) so a lost battle — which ends the run and
  // unmounts RunMap — still shows its replay + result.
  const [result, setResult] = useState<VisitResult | null>(null)

  if (regions.isLoading) return <p className="muted">Wczytywanie mapy…</p>
  if (regions.isError) return <p className="muted muted--bad">Nie udało się wczytać mapy.</p>

  const run = regions.data?.run ?? null
  const list = regions.data?.regions ?? []

  function onVisit(nodeId: string) {
    visit.mutate(nodeId, {
      onSuccess: (outcome) => {
        // Merchant just parks you at the node — RunMap renders its shop.
        if (outcome.type !== 'merchant') setResult(outcome)
      },
    })
  }

  if (result) {
    return <NodeOutcome result={result} onDone={() => setResult(null)} />
  }

  if (run && run.status === 'active') {
    return (
      <RunMap
        run={run}
        onVisit={onVisit}
        visiting={visit.isPending ? (visit.variables ?? null) : null}
        visitError={visit.isError ? errorText(visit.error) : null}
      />
    )
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
  result: VisitResult
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

function RunMap({
  run,
  onVisit,
  visiting,
  visitError,
}: {
  run: RunView
  onVisit: (nodeId: string) => void
  visiting: string | null
  visitError: string | null
}) {
  const buy = useMerchantBuy()
  const advance = useAdvanceRun()
  const abandon = useAbandonRun()

  const cleared = new Set(run.clearedNodeIds)
  const reachable = new Set(run.reachableNodeIds)
  const merchant = run.activeMerchant

  return (
    <section className="runmap">
      <div className="runmap__head">
        <span className="muted">Wyprawa · rząd {run.currentRow + 1}</span>
        <button
          type="button"
          className="btn btn--ghost"
          onClick={() => abandon.mutate()}
          disabled={abandon.isPending}
        >
          Porzuć
        </button>
      </div>

      {!merchant && (
        <div className="minimap">
          {[...run.map.rows].reverse().map((row) => (
            <div key={row.row} className="minimap__row">
              {row.nodes.map((node) => {
                const state = cleared.has(node.id)
                  ? 'is-cleared'
                  : reachable.has(node.id)
                    ? 'is-reachable'
                    : 'is-future'
                const label = NODE_LABEL[node.type as NodeType]
                return state === 'is-reachable' ? (
                  <button
                    key={node.id}
                    type="button"
                    className="minimap__node is-reachable"
                    disabled={visiting !== null}
                    title={label}
                    onClick={() => onVisit(node.id)}
                  >
                    <span aria-hidden="true">{NODE_ICON[node.type as NodeType]}</span>
                    <span className="minimap__caption">
                      {visiting === node.id ? '…' : label}
                    </span>
                  </button>
                ) : (
                  <span key={node.id} className={`minimap__node ${state}`} title={label}>
                    <span aria-hidden="true">
                      {state === 'is-cleared' ? '✓' : NODE_ICON[node.type as NodeType]}
                    </span>
                  </span>
                )
              })}
            </div>
          ))}
        </div>
      )}

      {!merchant && visitError && <p className="muted muted--bad">{visitError}</p>}

      {merchant && (
        <div className="merchant">
          <h3>Kupiec</h3>
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
                  onClick={() => buy.mutate(offer.id)}
                >
                  {offer.bought ? 'Kupione' : 'Kup'}
                </button>
              </li>
            ))}
          </ul>
          <button
            type="button"
            className="btn"
            onClick={() => advance.mutate()}
            disabled={advance.isPending}
          >
            Idź dalej
          </button>
          {buy.isError && <p className="muted muted--bad">{errorText(buy.error)}</p>}
        </div>
      )}
    </section>
  )
}
