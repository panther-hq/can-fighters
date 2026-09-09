# API notes

Planned MVP surface: spec §55. This file tracks what actually exists.

## Live now

| Method | Path                  | Auth    | Description                                          |
| ------ | --------------------- | ------- | --------------------------------------------------- |
| GET    | `/up`                 | none    | Laravel built-in health probe                        |
| GET    | `/api/health`         | none    | Checks PostgreSQL + Redis; `200 ok` / `503 degraded` |
| GET    | `/sanctum/csrf-cookie`| none    | Primes the `XSRF-TOKEN` cookie (call before writes)  |
| POST   | `/api/auth/register`  | none    | name, email, password, password_confirmation → 201, starts session |
| POST   | `/api/auth/login`     | none    | email, password → 200, starts session               |
| POST   | `/api/auth/logout`    | session | 204, ends session                                   |
| GET    | `/api/auth/me`        | session | Current user + profile                              |
| GET    | `/api/game/bootstrap` | session | Everything the SPA needs after login / reconnect    |
| GET    | `/api/ingredients`    | session | Full ingredient catalogue (12, static content)      |
| GET    | `/api/player/inventory`| session | `{ ingredients: [...], cans: [...] }`               |
| GET    | `/api/cans`           | session | `{ data: [...] }` — the player's owned cans          |
| POST   | `/api/cans/{can}/open`| session | Opens one can from that stack → `201 { openingId, seed, received[] }` |

`{can}` is a **player_cans id**. Send an `Idempotency-Key` header on `open`
(spec §58): a retry with the same key replays the stored result without opening
a second can. The response carries `Idempotency-Replayed: true|false`.
Empty stack → `422 { "message": "Nie masz tej puszki." }`; someone else's
can → `404`.

### Locale

`APP_LOCALE=pl` (fallback `en`). Validation / auth messages come from
`laravel-lang` (`lang/pl/*`). The whole UI is Polish.

### Auth model

Sanctum SPA **cookie** auth. The SPA is same-origin with the API via the Vite
proxy. Flow: `GET /sanctum/csrf-cookie` once, then axios mirrors the
`XSRF-TOKEN` cookie into `X-XSRF-TOKEN` on every write. First-party requests are
matched by `Origin` against `SANCTUM_STATEFUL_DOMAINS`.

### Response shapes

`register` / `login` / `me` — `UserResource`:

```json
{ "data": { "id": 1, "name": "Stefan", "email": "s@e.com",
            "profile": { "level": 1, "xp": 0, "coins": 0, "rating": 1000 } } }
```

`GET /api/game/bootstrap` (spec §43 — most sections stubbed until their phase):

```json
{
  "player": { "id": 1, "displayName": "Stefan",
              "profile": { "level": 1, "xp": 0, "coins": 0, "rating": 1000 } },
  "currencies": { "coins": 0 },
  "team": null,
  "cans": [],
  "notifications": [],
  "serverTime": "2026-01-01T00:00:00+00:00"
}
```

`/api/health`:

```json
{ "status": "ok", "service": "can-fighters-api",
  "checks": { "database": true, "cache": true }, "time": "…" }
```

## Next (Phase 3 — AI Mixer)

```
POST /api/mixer/preview
POST /api/mixer/mix          (queued; idempotency key)
GET  /api/mixer/{mixId}
```

## Conventions

- JSON only. Errors render as JSON for `api/*` (`bootstrap/app.php`).
- Validation errors: `422` `{ message, errors: { field: [msg] } }`.
- Idempotency keys on economic actions (open can, mix, claim, craft, purchase) — spec §58.
- REST mutates state; the client learns about side effects over Reverb — spec §38.
