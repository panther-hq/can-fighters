# Architecture notes

Full detail: `../can-fighters-specification.md` §32–§53, §70–§71. This file
records decisions and their current state.

## Core rules (spec §72)

1. Laravel is authoritative; PostgreSQL is the source of truth.
2. REST changes state. WebSockets (Reverb) only notify clients of changes.
3. Battle math runs in Laravel. Phaser only visualizes events.
4. AI Mixer produces creative concepts only; the Balance Engine sets every
   number. AI failure must fall back to a deterministic generator.
5. Never trust the client for damage, rewards, stats, drops or PvP results.
6. Critical inventory/economy operations are transactional + idempotent.
7. On socket loss the client rebuilds from `GET /api/game/bootstrap`.

## Stack (as built)

| Layer     | Choice                                              |
| --------- | -------------------------------------------------- |
| Frontend  | React 19, Vite 8, TypeScript, TanStack Query, axios |
| Realtime  | Laravel Echo + Reverb (client wiring: Phase 9)      |
| Battles   | Phaser (Phase 6–7)                                  |
| Backend   | Laravel 13, PHP 8.4                                 |
| Database  | PostgreSQL 16                                       |
| Cache/Q   | Redis 7 (cache, queue, sessions)                    |
| WebSocket | Laravel Reverb                                      |
| Storage   | S3-compatible (later; `FILESYSTEM_DISK=local` now)  |

## Decisions

- **Auth**: Sanctum SPA cookie auth (stateful). SPA and API are same-origin in
  dev via the Vite proxy; `config/cors.php` has `supports_credentials => true`
  and the SPA origin allow-listed. Token auth is not used.
- **Host runs nothing**: PHP/Composer/Node are container-only. The API image
  (`docker/api/Dockerfile`) is a PHP 8.4 CLI image with `pdo_pgsql` + `redis`,
  used for `artisan serve`, the queue worker and the Reverb server.
- **Host ports shifted** (PG 5442, Redis 6389) to coexist with other local
  Docker projects. Inter-service traffic uses standard ports + service names.
- **`php artisan serve`** runs the API in dev. Production would be php-fpm +
  a web server; revisit before deploy.

## Planned domain layout (spec §64)

```
apps/api/app/Domain/{Battle,Fighters,Inventory,Mixer,Equipment,PvE,Arena,Economy}
```

Not created yet — introduced per phase as each domain gets real logic.
