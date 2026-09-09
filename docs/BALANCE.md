# Balance notes

Full detail: spec §8, §14–§18. Nothing implemented in Phase 0 — this is a
placeholder so the decisions have a home when the Balance Engine lands (Phase 5).

## Principles

- AI never sets numbers. It picks class/traits/skill *families* + theme; the
  Balance Engine turns that into stats via class weight profiles + Power Budget.
- Every fighter carries `generation_seed` + `generation_version` and is
  persisted once — deterministic, no re-generation during normal play (spec §11).
- Rarity is a small edge, not dominance (spec §15). Team comp > raw rarity.
- Mutations must not create unbounded power scaling (spec §19).

## MVP stats

`HP · ATK · DEF · MAG · SPD · CRIT` (spec §14).

## Engine shape (spec §16) — implemented

`App\Domain\Balance\StandardBalanceEngine::apply(Fighter)` — the only place
numbers are set. Runs on mix, upgrade and mutate.

1. **Budget** = `(base 100 + rarity bonus) × (1 + 0.09·(level−1))` (spec §15).
2. **Raw stats** = `floor + classProfileWeight × scale` per stat; a secondary
   class blends its profile in at 35%.
3. **Normalise**: scale every raw stat by `budget / rawPowerScore` so the final
   `power_score` lands on the budget (±rounding). Clamp to floors + `crit_cap`.
4. **`pvp_legal`** = `power_score ≤ budget × 1.10`. By construction true for
   engine output; the flag lets the Arena reject tampered fighters.
5. **Skills**: `SkillParameterResolver` — per-family baselines from config,
   level scaling, and offensive families scale `power` with the wielder's best
   offence stat.

Everything tunable lives in `config/balance.php`. `php artisan
fighters:recompute-balance [--check]` re-runs / audits drift.

## Class profile intent

tank = HP+DEF wall · fighter = balanced melee · assassin = SPD+ATK+CRIT glass ·
ranged = ATK+SPD · mage = MAG · support = MAG+HP+DEF · debuffer = MAG+SPD ·
summoner = MAG · engineer = balanced bruiser · crafter = defensive/economic.
