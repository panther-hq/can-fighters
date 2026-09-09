# Can Fighters

Responsive browser game: mix strange canned ingredients through an AI Mixer into
persistent fighters, build a team, and fight PvE and asynchronous PvP.

The full product & architecture spec is [`can-fighters-specification.md`](./can-fighters-specification.md).
It is the source of truth for requirements; `docs/` holds working notes per area.

## Status

**Phase 0 — project foundation.** A running skeleton only: no game features yet.

| Service         | URL                              | Notes                              |
| --------------- | -------------------------------- | ---------------------------------- |
| Web (React SPA) | http://localhost:5173            | Vite dev server, proxies `/api`    |
| API (Laravel)   | http://localhost:8000            | `GET /api/health`, `GET /up`       |
| Reverb (WS)     | ws://localhost:8080              | Laravel Reverb, not used yet       |
| PostgreSQL      | localhost:5442 (`can_fighters`)  | source of truth                    |
| Redis           | localhost:6389                   | cache, queue, sessions             |

Host ports are shifted off the defaults (5442/6389) to avoid clashing with other
local projects. Containers talk to each other on standard ports via service name.

## Requirements

- Docker + Docker Compose. Nothing else is installed on the host — PHP, Composer
  and Node all run inside containers.

## Getting started

```bash
docker compose up -d
```

First boot builds the API image and runs `npm install` for the web app inside a
volume, so give it a minute. Then:

```bash
curl http://localhost:8000/api/health          # {"status":"ok", ...}
open http://localhost:5173                      # status page, all checks green
```

### Common tasks

```bash
docker compose exec api php artisan <cmd>       # artisan
docker compose exec api php artisan test        # backend test suite
docker compose exec api composer <cmd>          # composer
docker compose run --rm web npm <cmd>           # npm for the SPA
docker compose logs -f api web                  # tail logs
docker compose down                             # stop
docker compose down -v                          # stop + wipe db/redis/node_modules
```

## Layout

```
apps/
  api/     Laravel 13 (PHP 8.4) — REST API, Reverb, queues, Battle Engine (later)
  web/     React 19 + Vite + TypeScript — SPA; Phaser for battles (later)
docker/
  api/     Dockerfile for the PHP CLI image (artisan serve / queue / reverb)
docs/      Per-area notes; see can-fighters-specification.md for the full spec
```

## What Phase 0 set up

- `docker-compose.yml`: postgres, redis, api, queue worker, reverb, web.
- **API**: fresh Laravel; `install:api` (adds `routes/api.php` + Sanctum);
  `laravel/reverb`; Postgres + Redis wired via `.env`; CORS configured for the
  SPA origin with credentials (ready for Sanctum SPA cookie auth in Phase 1);
  `GET /api/health` checks DB + cache and is what the SPA polls.
- **Web**: Vite dev server with a same-origin `/api` proxy; TanStack Query +
  axios client; a status page that proves `browser → Vite → Laravel → PG/Redis`.
- Tests run against in-memory SQLite (`phpunit.xml`), isolated from the dev DB.

## Next: Phase 1

Auth (register / login / logout / me) with Sanctum SPA cookies, `PlayerProfile`,
and `GET /api/game/bootstrap`. See spec §43, §54, §55, §68.
