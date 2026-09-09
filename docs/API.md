# API notes

Planned MVP surface: spec §55. This file tracks what actually exists.

## Live now (Phase 0)

| Method | Path          | Auth | Description                                        |
| ------ | ------------- | ---- | ------------------------------------------------- |
| GET    | `/up`         | none | Laravel built-in health probe                     |
| GET    | `/api/health` | none | Checks PostgreSQL + Redis; `200 ok` / `503 degraded` |
| GET    | `/api/user`   | sanctum | Returns the authenticated user (no auth flow yet) |

`/api/health` response:

```json
{
  "status": "ok",
  "service": "can-fighters-api",
  "checks": { "database": true, "cache": true },
  "time": "2026-01-01T00:00:00+00:00"
}
```

## Next (Phase 1)

```
POST /api/auth/register
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/me
GET  /api/game/bootstrap
```

## Conventions

- JSON only. Errors render as JSON for `api/*` (configured in `bootstrap/app.php`).
- Idempotency keys on economic actions (open can, mix, claim, craft, purchase) — spec §58.
- REST mutates state; the client learns about side effects over Reverb — spec §38.
