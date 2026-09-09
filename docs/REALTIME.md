# Realtime notes

Full detail: spec §38–§46, §70. Rule: **PostgreSQL + Laravel are the source of
truth; WebSockets are delivery only.** If the socket drops, the client recovers
from `GET /api/game/bootstrap`.

## State (Phase 0)

- Reverb server runs (`reverb` container, `ws://localhost:8080`).
- `BROADCAST_CONNECTION=reverb`; `config/reverb.php` + `routes/channels.php` published.
- No events are broadcast yet and the SPA has no Echo client yet.

## Planned channels (spec §44–§46)

- `private-player.{playerId}` — inventory/currency/can/mixer/character/equipment/arena/matchmaking events
- `private-battle.{battleId}` — live PvP (future phase)

## Planned pattern (spec §41)

```
REST mutates state
  -> Laravel fires domain event -> Reverb
  -> Echo receives -> queryClient.invalidateQueries([...])
  -> TanStack Query refetches the affected resource
```

No global polling. Client wiring lands in Phase 9 (async PvP) — earlier phases
can refetch on demand.
