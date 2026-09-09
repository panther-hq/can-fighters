# Game design notes

The design is fully specified in `../can-fighters-specification.md` §1–§31, §61–§75.
Do not duplicate it here — this file is for open questions and decisions taken
during implementation.

## Core loop (spec §3)

get can → open → ingredients → mix → fighter → team → equip → fight PvE/PvP →
rewards → upgrade → more cans.

## First vertical slice (spec §73)

3 cans → open → Pasta + Screw + Battery → Mixer → queued AI gen → Balance Engine
→ fighter saved → `mixer.completed` over Reverb → reveal → add to team → Kitchen
stage → Battle Engine → Phaser playback → win → Can Armor → equip → continue.

## MVP scope (spec §65)

12 ingredients · 6 base classes (Tank, Fighter, Ranged, Mage, Support, Engineer) ·
20–30 trait combos · 20–30 equipment items · 15 PvE stages · 3 bosses · 1 can ·
3-fighter teams · 3 equipment slots.

## Explicitly out for MVP (spec §66)

guilds, clans, chat, trading, marketplace, world boss, advanced crafting, live
PvP, 5-person teams, AI animation.

## Language

Whole UI + game content display names are Polish. Ingredient slugs (English)
are the stable IDs the Mixer / balance systems key on. Ingredient names:
Makaron, Śruba, Bateria, Ser, Skarpeta, Magnes, Widelec, Ryba, Ogień, Maź,
Blaszana puszka, Sprężyna. Starter can: *Zardzewiała puszka*.

## Open questions

- _(none yet)_
