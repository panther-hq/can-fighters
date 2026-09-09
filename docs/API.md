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

## Next (Phase 2)

```
GET  /api/ingredients
GET  /api/cans
POST /api/cans/{id}/open      (idempotency key)
GET  /api/player/inventory
```

## Conventions

- JSON only. Errors render as JSON for `api/*` (`bootstrap/app.php`).
- Validation errors: `422` `{ message, errors: { field: [msg] } }`.
- Idempotency keys on economic actions (open can, mix, claim, craft, purchase) — spec §58.
- REST mutates state; the client learns about side effects over Reverb — spec §38.
