import { useEffect, useState } from 'react'
import { useJoinQueue, useLeaveQueue } from './hooks'
import { LiveBattleScreen } from './LiveBattleScreen'

export function LivePanel() {
  const join = useJoinQueue()
  const leave = useLeaveQueue()
  const [battleId, setBattleId] = useState<number | null>(null)
  const queued = join.data?.status === 'queued' && battleId === null

  // Poll the queue until we're matched (the matchmaking.found event also
  // fires, but polling keeps this simple and works without a live socket).
  useEffect(() => {
    if (!queued) return
    const t = setInterval(() => {
      join.mutate(undefined, {
        onSuccess: (r) => {
          if (r.status === 'matched' && r.battleId) setBattleId(r.battleId)
        },
      })
    }, 2500)
    return () => clearInterval(t)
  }, [queued, join])

  if (battleId !== null) {
    return (
      <LiveBattleScreen
        battleId={battleId}
        onExit={() => {
          setBattleId(null)
          join.reset()
        }}
      />
    )
  }

  return (
    <div className="live-lobby">
      <p className="muted">
        Walka na żywo z drugim graczem. Rundy: obaj wybieracie akcję, serwer
        rozstrzyga.
      </p>

      {queued ? (
        <>
          <p>Szukam przeciwnika…</p>
          <button
            type="button"
            className="btn"
            onClick={() => {
              leave.mutate()
              join.reset()
            }}
          >
            Anuluj
          </button>
        </>
      ) : (
        <button
          type="button"
          className="btn btn--primary"
          disabled={join.isPending}
          onClick={() =>
            join.mutate(undefined, {
              onSuccess: (r) => {
                if (r.status === 'matched' && r.battleId) setBattleId(r.battleId)
              },
            })
          }
        >
          {join.isPending ? 'Szukam…' : 'Szukaj gry'}
        </button>
      )}

      {join.isError && (
        <p className="muted muted--bad">
          Nie udało się dołączyć do kolejki (ustaw drużynę?).
        </p>
      )}
    </div>
  )
}
