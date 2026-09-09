# Battle Engine notes

Spec §32–§35. `App\Domain\Battle\` — pure, deterministic, backend-authoritative.
Phaser only replays `events`; it decides nothing.

## Entry point

```php
$result = app(BattleEngine::class)->run(array $combatants /* CombatantInput[] */, int $seed);
// BattleResult { winner: 'A'|'B'|'draw', duration, seed, version, survivors, events[] }
```

`FighterCombatants::fromEntries($entries, 'A'|'B')` builds `CombatantInput[]`
from `Fighter` models (used by PvE / Arena) — an immutable snapshot so a battle
stays reproducible even if the fighter changes later.

## Model

- **Initiative**: each unit has `nextActAt`; the engine repeatedly picks the
  alive unit with the smallest one (ties → lowest id), advances the clock to
  it, resolves its turn, then `nextActAt += actionInterval` where
  `actionInterval = 1000 · 100 / (50 + speed)` (faster → more turns).
- **A turn**: expire timed effects + roll damage-over-time on self → if stunned,
  skip → else use the first ready, useful skill (slot order), else basic attack.
- **Basic attack**: hits `TargetSelector::enemyTarget` for `attack` (or `magic`
  for mage/support/debuffer/summoner), mitigated by `100/(100+defense)`, ×1.5
  on a crit (`rng < crit%`), min 1. `counterattack` skills fire here.
- **Skills**: `SkillResolver` — one method per family. direct/area/execute/
  lifesteal damage; poison/bleed DoT; heal/shield/cleanse/revive support;
  taunt/stun/slow/silence control; buff_* (self) / debuff_* (enemy) stat mods.
  `summon` is a placeholder AoE for now; real extra units later.
- **Termination**: one team wiped, or `MAX_TIME` 60 000 ms (draw), or
  `MAX_ACTIONS` 600 (safety). Always terminates.

## Events (spec §34)

`{ sequence, time, type, source?, target?, ...extra }`, strictly increasing
`sequence`, non-decreasing `time`. Types: `spawn`, `battle_start`, `damage`
(`cause`: attack|skill|counter|poison|bleed, `crit?`), `heal`, `effect`
(`effect`: stun|slow|shield|taunt|buff_*|debuff_*|poison|…), `stunned`,
`revive`, `death`, `battle_end` (`winner`).

## Tuning

`config/balance.php` drives fighter stats + skill parameters (phase 5).
Engine constants (intervals, AoE falloff, execute multiplier, caps) live in the
classes. `BattleEngine::VERSION` is stored on results for replay compatibility.
