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

  if (regions.isLoading) return <p className="muted">Wczytywanie mapy…</p>
  if (regions.isError) return <p className="muted muted--bad">Nie udało się wczytać mapy.</p>

  const run = regions.data?.run ?? null
  const list = regions.data?.regions ?? []

  if (run && run.status === 'active') {
    return <RunMap run={run} />
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
              {start.isPending && start.variables === region.slug
                ? 'Rusza…'
                : 'Wyprawa'}
            </button>
          </li>
        ))}
      </ul>
      {start.isError && <p className="muted muted--bad">{errorText(start.error)}</p>}
    </section>
  )
}

function RunMap({ run }: { run: RunView }) {
  const visit = useVisitNode()
  const buy = useMerchantBuy()
  const advance = useAdvanceRun()
  const abandon = useAbandonRun()
  const [result, setResult] = useState<VisitResult | null>(null)

  const nodesById = new Map(
    run.map.rows.flatMap((r) => r.nodes.map((n) => [n.id, n])),
  )
  const reachable = run.reachableNodeIds
    .map((id) => nodesById.get(id))
    .filter((n): n is NonNullable<typeof n> => n != null)
  const clearedTrail = run.clearedNodeIds
    .map((id) => nodesById.get(id))
    .filter((n): n is NonNullable<typeof n> => n != null)
  const bossReached = run.reachableNodeIds.includes('boss')

  // A finished battle we are still showing the replay for.
  if (result?.result?.events) {
    return (
      <BattleReplay
        events={result.result.events}
        won={result.won ?? false}
        heading="WYGRANA"
        onDone={() => setResult(null)}
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
        {result.runEnded && (
          <p className="muted muted--bad">Wyprawa przerwana.</p>
        )}
      </BattleReplay>
    )
  }

  // A non-battle node outcome (loot / event) to acknowledge.
  if (result && (result.type === 'loot' || result.type === 'event')) {
    return (
      <div className="node-result">
        <p className="node-result__title">
          {result.event === 'pulapka'
            ? 'Pułapka!'
            : result.type === 'loot'
              ? 'Skrzynia'
              : (result.event ?? 'Zdarzenie')}
        </p>
        <ul className="result__rewards">
          {(result.rewards ?? []).map((reward, i) => (
            <li key={i}>
              <span>{rewardText(reward)}</span>
            </li>
          ))}
        </ul>
        <button type="button" className="btn btn--primary" onClick={() => setResult(null)}>
          Dalej
        </button>
      </div>
    )
  }

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

      <div className={`runmap__boss ${bossReached ? 'is-live' : ''}`}>
        👑 {bossReached ? 'Boss czeka!' : 'Boss regionu'}
      </div>

      {clearedTrail.length > 0 && (
        <div className="runmap__trail">
          {clearedTrail.map((node) => (
            <span key={node.id} title={NODE_LABEL[node.type as NodeType]}>
              {NODE_ICON[node.type as NodeType]}
            </span>
          ))}
        </div>
      )}

      {merchant ? (
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
      ) : (
        <>
          <p className="muted runmap__prompt">Wybierz drogę:</p>
          <ul className="runmap__choices">
            {reachable.map((node) => (
              <li key={node.id}>
                <button
                  type="button"
                  className="runmap__choice"
                  disabled={visit.isPending}
                  onClick={() => visit.mutate(node.id, { onSuccess: setResult })}
                >
                  <span className="runmap__choice-icon" aria-hidden="true">
                    {NODE_ICON[node.type as NodeType]}
                  </span>
                  <span>{NODE_LABEL[node.type as NodeType]}</span>
                  {visit.isPending && visit.variables === node.id && (
                    <span className="muted"> …</span>
                  )}
                </button>
              </li>
            ))}
          </ul>
          {visit.isError && <p className="muted muted--bad">{errorText(visit.error)}</p>}
        </>
      )}
    </section>
  )
}
