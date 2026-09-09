# Can Fighters

Responsive browser game: mix strange canned ingredients through an AI Mixer into
persistent fighters, build a team, and fight PvE and asynchronous PvP.

The full product & architecture spec is [`can-fighters-specification.md`](./can-fighters-specification.md).
It is the source of truth for requirements; `docs/` holds working notes per area.

## Status

**Phase 9 — Async PvP Arena — MVP complete** (Phases 0–8 done).
Set a defense team; challenge other players' defense teams while they're
offline. Immutable team snapshots, backend-run battle, Elo rating + Polish
leagues (Brąz → Puszkowa Legenda), opponent matchmaking by rating band,
ranking + history, and `arena.defense_attacked` / `arena.rating_updated`
broadcasts. This closes the spec §67 Definition of Done — a new player can go
account → can → mix → team → equip → PvE → PvP end to end.

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

**Phase 5 — Balance Engine**

- `StandardBalanceEngine` reworked: raw class-profile stats → normalise to the
  Power Budget → `pvp_legal` check. `budget` + `pvp_legal` on `fighter_stats`.
- `SkillParameterResolver` extracted; offensive skills scale with offence stat.
- `config/balance.php` gains `pvp_tolerance`, `offensive_skills`, crit scale bump.
- `fighters:recompute-balance --check` audits power drift / legality.
- SPA fighter detail shows `moc / budżet` and a PvP-illegal warning.

**Phase 6 — Battle Engine**

- `Domain\Battle\`: `BattleEngine` (initiative loop), `BattleState`,
  `BattleUnit` (hp/shield/effects/cooldowns), `DamageCalculator`,
  `TargetSelector` (spec §21), `SkillResolver` (per-family behaviour),
  `FighterCombatants` (Fighter → `CombatantInput` snapshot).
  ValueObjects: `CombatantInput`, `BattleEvent`, `BattleResult`.
- Deterministic: same combatants + seed → identical event stream.
- `docs/BATTLE.md` documents the model + event types.

**Phase 7 — PvE**

- `pve_stage_definitions` + `PveKitchenSeeder` (6 Kitchen stages, Polish enemy
  names). `battles` + `battle_snapshots` + `player_stage_progress`.
- `Domain\PvE\EnemyCombatants` (class profile → enemy at stage budget, bosses
  bumped), `FightStage` (atomic: build → simulate → persist → reward + XP +
  progress + unlock). `Idempotency` on the battle POST.
- `StandardBalanceEngine::statsForBudget()` shared by fighters and enemies.
- SPA: `useStages` / `useFightStage`, `CampaignView` (stage list, lock/stars),
  and `BattleReplay` + a Phaser `BattleScene` that replays `events` (HP bars,
  floating damage, lunges, skill/effect labels) with a result + rewards overlay.

**Phase 8 — Equipment**

- `equipment_definitions` + 15 seeded items; `player_equipment` (seeded roll,
  rarity multiplier + ±10% variance — deterministic); `fighter_equipment`
  (one piece per slot, one wearer per piece).
- `Domain\Equipment\EquipmentRoller` + `EquipFighter` (equip / unequip,
  atomic, moves a piece off its old wearer, re-runs the Balance Engine).
- `StandardBalanceEngine` adds equipment bonuses after budget normalisation;
  `pvp_legal` still tracks the base block.
- PvE `equipmentDrops` (boss guaranteed) via `EquipmentRoller`.
- `GET /api/equipment`, `POST /api/fighters/{id}/equip|unequip`.
- SPA: `FighterEquipment` slot UI in the detail, gear section in Plecak,
  equipment in the battle-rewards overlay.

**Phase 9 — Async PvP Arena**

- `Domain\Arena\`: `Elo` (zero-sum, K=32, rating floor), `LeagueTable`
  (rating → Polish league), `ChallengeOpponent` (atomic: snapshot both teams,
  run BattleEngine, update both ratings, record `arena_results`, notify).
- Reuses `teams` with `type='defense'` + `SaveTeam`; `battles`/`battle_snapshots`
  from phase 7. `config/arena.php` holds every knob.
- `GET /api/arena` (summary), `PUT /api/arena/defense-team`,
  `GET /api/arena/opponents|ranking|history`,
  `POST /api/arena/challenge/{player}` (Idempotency-Key).
- SPA: `TeamView` parametrised by team type; `ArenaView` with Obrona / Walka /
  Ranking / Historia panels; `BattleReplay` generalised (PvE + Arena share it).

**222 feature/unit tests** (+14: Elo maths + leagues, defense team,
opponents filtering, challenge win/rating/records, self-challenge + no-defense
guards, idempotency, ranking + history).

## Next: Phase 10

Responsive polish: mobile/tablet/desktop layout, bottom nav, touch targets,
loading/offline/reconnect states, Echo wiring for realtime. See spec §60–§61, §68.
