# Can Fighters

Responsive browser game: mix strange canned ingredients through an AI Mixer into
persistent fighters, build a team, and fight PvE and asynchronous PvP.

The full product & architecture spec is [`can-fighters-specification.md`](./can-fighters-specification.md).
It is the source of truth for requirements; `docs/` holds working notes per area.

## Status

**Phase 4 — fighters & teams** (Phases 0–3 done).
Fighters now carry real stats + skills from `Domain\Balance\StandardBalanceEngine`
(`config/balance.php` — class weight profiles, rarity budget, level growth).
`GET /api/fighters/{id}` (stats + skills), `POST .../upgrade` (coins → level),
`POST .../mutate` (re-mix 1–3 ingredients — spec §19), `GET|PUT /api/teams`
(3-slot campaign roster, spec §20). SPA gains fighter detail (stat bars,
upgrade, mutate) and a Drużyna team builder.

**The UI is entirely in Polish** (`APP_LOCALE=pl`, `laravel-lang` for
validation/auth messages, Polish display names for game content; `slug`s stay
English as stable IDs).

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
open http://localhost:5173                      # register an account, land on the dashboard
```

### Common tasks

```bash
docker compose exec api php artisan <cmd>       # artisan
docker compose exec api php artisan test        # backend test suite (in-memory sqlite)
docker compose exec api ./vendor/bin/pint       # format PHP
docker compose exec api composer <cmd>          # composer
docker compose exec web npm <cmd>               # npm for the SPA
docker compose exec web npx tsc -b              # typecheck the SPA
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

## What's built

**Phase 0 — foundation**

- `docker-compose.yml`: postgres, redis, api, queue worker, reverb, web.
- API: fresh Laravel; `install:api` + Sanctum; `laravel/reverb`; Postgres +
  Redis wired via `.env`; CORS for the SPA origin with credentials;
  `GET /api/health` checks DB + cache.
- Web: Vite dev server with a same-origin `/api` proxy; TanStack Query + axios.
- Laravel reads `apps/api/.env` from the mounted volume (no compose `env_file`,
  which would shadow `.env.testing`). Tests run on in-memory SQLite via
  `.env.testing`, isolated from the dev DB.

**Phase 1 — accounts & bootstrap**

- `PlayerProfile` (level, xp, coins, rating) — one per user, created at register.
- `AuthController`: register / login / logout / me. Sanctum SPA cookie auth
  (`statefulApi()`), session-fixation-safe (`session()->regenerate()`).
- `GameController@bootstrap` → `GET /api/game/bootstrap` (spec §43 shape).
- SPA: `useMe` / `useLogin` / `useRegister` / `useLogout` hooks, CSRF priming,
  a login/register form, a dashboard driven by the bootstrap endpoint.

**Phase 2 — ingredients & cans**

- `GameContentSeeder` (idempotent): 12 ingredients (spec §7) + 1 can, all with
  Polish names. `ingredient_definitions`, `can_definitions`.
- `player_ingredients`, `player_cans`, `can_openings` (seed + results — spec §11).
- `Domain\Inventory\OpenCanService` — atomic (spec §57): row-locks the stack,
  rolls the drop table with `WeightedRoller` (deterministic LCG), moves
  ingredients in, records the opening.
- `Support\Idempotency` + `idempotency_keys` — replay protection (spec §58).
- `Domain\Player\GrantStarterPack` — 3 cans at register.
- Endpoints: `GET /api/ingredients`, `GET /api/player/inventory`,
  `GET /api/cans`, `POST /api/cans/{can}/open`.
- Locale → `pl` via `laravel-lang`.
- SPA: Panel / Puszki / Plecak tabs; open-a-can with reveal; `Idempotency-Key`
  per request.

**Phase 3 — AI Mixer**

- `Domain\Mixer\`: `CharacterGenerationProvider` interface + `Fallback`
  (deterministic, `SeededRng` LCG) + `Mock` (tests); `ConceptValidator`
  (allowed class / traits / skill families, normalise); `MixerService`
  (resolve + consume ingredients atomically, generate, validate, persist,
  announce). `ProcessMixRequest` job, `MixerCompleted` / `MixerFailed` events.
- `fighters`, `mix_requests` tables; `config/mixer.php` (all pools + maps).
- `Idempotency` reused for `mixer.mix`. Broadcasting is best-effort — a socket
  failure never fails a saved mix.
- SPA: ingredient picker, preview, MIKSUJ, polled status, fighter reveal,
  Wojownicy list.

**Phase 4 — fighters & teams**

- `fighter_stats`, `fighter_skills`, `teams`, `team_members`, `fighter_mutations`.
- `Domain\Balance\StandardBalanceEngine::apply()` — the only place numbers are
  decided (spec §16). Runs on mix, on upgrade, on mutate; `fighters:recompute-balance`
  command for tuning.
- `Domain\Fighters\UpgradeFighter` (coins, atomic), `MutateFighter` (spec §19 —
  bounded, records before/after). `Domain\Teams\SaveTeam` (validation).
- `config/balance.php` holds every tunable.
- SPA: `FighterDetail` (stat bars + skills + Ulepsz + Mutuj), `TeamView`
  (Przód/Środek/Tył slots + picker + save).
- `queue:listen` (was `queue:work`) so the dev worker picks up code changes.

**85 feature/unit tests.**

## Next: Phase 5

Balance Engine hardening: Power Budget enforcement, PvP legality, skill
parameter resolver, tuning + tests. See spec §15–§17, §68.
