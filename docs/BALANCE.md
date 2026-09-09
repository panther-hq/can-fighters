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

## Engine shape (spec §16)

Input: class, secondaryClass, traits, skillFamilies, rarity, level, seed,
equipment bonuses. Output: stats, skill parameters, powerScore.
