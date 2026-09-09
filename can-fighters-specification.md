# Can Fighters — Game Design & Technical Specification

## 1. Overview

**Can Fighters** is a responsive browser-first game combining:

- team-based RPG combat,
- collection mechanics,
- AI-generated fighters,
- equipment and progression,
- PvE,
- asynchronous PvP,
- future live PvP,
- crafting and class specializations.

The game is designed primarily for modern web browsers and mobile browsers.

The frontend is a responsive web application, while the backend exposes an API and realtime events.

---

# 2. Core fantasy

The player discovers strange cans containing unusual ingredients.

Examples:

- Pasta
- Screw
- Battery
- Cheese
- Sock
- Magnet
- Fork
- Fish
- Fire
- Slime
- Tin Can
- Spring

The player places ingredients into the **Mixer**.

Example:

```text
Pasta + Screw + Battery
            ↓
          MIX
            ↓
   Electro-Pastabot
```

The result is a new fighter.

The defining feature of Can Fighters is:

> “What happens if I mix these things together?”

This should be the strongest discovery and viral mechanic in the game.

---

# 3. Main gameplay loop

```text
GET A CAN
    ↓
OPEN THE CAN
    ↓
GET INGREDIENTS
    ↓
MIX INGREDIENTS
    ↓
CREATE A FIGHTER
    ↓
ADD FIGHTER TO TEAM
    ↓
GET EQUIPMENT
    ↓
FIGHT PvE OR PvP
    ↓
GET REWARDS
    ↓
UPGRADE
    ↓
GET MORE CANS / INGREDIENTS
```

A short session should still feel useful.

Ideal short session length:

- 2–5 minutes.

---

# 4. Main design pillars

## Discovery

Players experiment with ingredient combinations.

The game should encourage:

> “What happens if I mix a fish, a battery, two screws and a sock?”

## Collection

Players collect:

- ingredients,
- fighters,
- traits,
- classes,
- equipment,
- mutations,
- rare combinations,
- achievements.

## Team building

The strength of a team should depend on:

- class composition,
- position,
- skills,
- equipment,
- synergies,
- counterplay,
- player decisions.

There should not be one universally best fighter.

## Progression

Players can improve:

- account level,
- fighters,
- equipment,
- class progression,
- Mixer,
- crafting,
- PvP rating,
- collections.

## Humor

The world should be funny and absurd.

Examples:

- Legendary Stefan's Fork +7
- Grandma's Lid
- Smelly Shaman Sock
- Pasta Inquisitor
- Admiral Volt-Mackerel

The game should avoid generic high-fantasy naming where possible.

---

# 5. World concept

The world was changed by an accident involving the mysterious **Great Mixer**.

Strange cans began appearing around the world.

Inside the cans are everyday objects infused with strange energy.

When these objects are combined inside a Mixer, they can become living fighters.

The player obtains an old Mixer and starts building a team.

---

# 6. PvE regions

## Kitchen

Enemies:

- Angry Spoon
- Furious Fork
- Cheese Slime
- Kitchen Fly

Boss:

- Pan Chef

## Garage

Enemies:

- Screwling
- Nut Monster
- Rust Bot
- Wrench Warrior

Boss:

- Mega Wrench

## Trash Zone

Enemies:

- Cardboard Beast
- Banana Zombie
- Can Eater
- Plastic Monster

Boss:

- King of Trash

## Laboratory

Enemies:

- Batterion
- Magnetor
- Laser Rat
- Experimental Slime

Boss:

- Doctor Mixer

---

# 7. Ingredients

Each ingredient has metadata.

Example:

```json
{
  "id": "battery",
  "name": "Battery",
  "tags": [
    "electric",
    "energy",
    "mechanical"
  ],
  "rarity": "uncommon",
  "powerValue": 12
}
```

Example ingredient traits:

| Ingredient | Suggested traits |
|---|---|
| Pasta | organic, flexible, speed, chaos |
| Screw | mechanical, armor |
| Battery | electric, energy |
| Cheese | organic, support |
| Sock | chaos, debuff |
| Magnet | mechanical, control |
| Fork | weapon, melee, crit |
| Fish | organic, water |
| Fire | elemental, damage |
| Slime | poison, control |
| Tin Can | metal, defense |
| Spring | mechanical, speed |

Ingredient quantity can influence the result.

Example:

```text
3x Pasta
1x Screw
1x Battery
```

should produce a more pasta-oriented fighter than:

```text
1x Pasta
4x Screws
3x Batteries
```

---

# 8. AI Mixer

The Mixer is the central game system.

The AI should generate the **creative concept**, but must never control the numerical game balance.

AI responsibilities:

- fighter name,
- description,
- personality,
- visual concept,
- primary class suggestion,
- optional secondary class,
- traits,
- suggested skill families,
- visual DNA.

Backend responsibilities:

- HP,
- attack values,
- defense values,
- magic values,
- speed,
- critical chance,
- cooldowns,
- skill strength,
- rarity bonuses,
- power score,
- PvP legality.

Correct architecture:

```text
INGREDIENTS
    ↓
AI CREATIVE LAYER
    ↓
CHARACTER CONCEPT
    ↓
VALIDATION
    ↓
BALANCE ENGINE
    ↓
FINAL GAME CHARACTER
    ↓
BATTLE ENGINE
```

The AI must not be able to create:

```text
999999 HP
100% stun
50000 damage
```

---

# 9. Mixer flow

Player selects ingredients.

Example request:

```json
{
  "ingredients": [
    {
      "ingredientId": "pasta",
      "quantity": 2
    },
    {
      "ingredientId": "screw",
      "quantity": 1
    },
    {
      "ingredientId": "battery",
      "quantity": 1
    }
  ]
}
```

Backend process:

1. Validate player.
2. Validate ingredient ownership.
3. Create idempotency key.
4. Lock or reserve ingredients.
5. Generate seed.
6. Create AI input.
7. Send request to Character Generation Provider.
8. Validate AI output.
9. Normalize the concept.
10. Run Balance Engine.
11. Save fighter.
12. Consume ingredients.
13. Update collection.
14. Emit realtime event.
15. Return fighter or processing status.

---

# 10. Example AI result

```json
{
  "name": "Electro-Pastabot",
  "description": "A mechanical warrior whose pasta cables carry unstable electricity.",
  "archetype": "engineer",
  "combatRange": "ranged",
  "primaryTrait": "electric",
  "secondaryTrait": "mechanical",
  "personality": "chaotic",
  "suggestedSkills": [
    {
      "skillFamily": "direct_damage",
      "modifier": "stun"
    },
    {
      "skillFamily": "self_buff",
      "modifier": "speed"
    }
  ],
  "visual": {
    "body": "tin_can",
    "material": "pasta_and_metal",
    "weapon": "battery_cannon",
    "specialFeature": "glowing_pasta_wires"
  }
}
```

---

# 11. Deterministic fighter generation

Every generated fighter should receive:

```text
characterId
generationSeed
generationVersion
```

Example:

```text
seed: 8347291283
generatorVersion: 1
```

After creation, the fighter is persisted.

The game should not ask AI to regenerate the same fighter during normal gameplay.

Benefits:

- stable PvP,
- repeatable replays,
- easier debugging,
- consistent skills,
- consistent identity,
- lower AI cost.

---

# 12. Fighter classes

The class system should have depth inspired by classic MMORPGs, while using original names and mechanics.

## Tank

Purpose:

- absorb damage,
- protect allies,
- taunt,
- shield.

Possible advanced classes:

- Guardian
- Fortress
- Juggernaut

## Fighter

Purpose:

- melee damage,
- sustained pressure,
- frontline combat.

Possible advanced classes:

- Duelist
- Berserker
- Destroyer

## Assassin

Purpose:

- high speed,
- critical damage,
- target backline.

Possible advanced classes:

- Shadow
- Executioner

## Ranged

Purpose:

- attack from distance,
- pressure enemy backline,
- projectiles.

Possible advanced classes:

- Sniper
- Gunner
- Bombardier

## Mage

Purpose:

- magical damage,
- AoE,
- elemental effects,
- crowd control.

Possible advanced classes:

- Elementalist
- Chaos Mage
- Control Mage

## Support

Purpose:

- healing,
- buffs,
- shields,
- cleansing.

Possible advanced classes:

- Healer
- Buffer
- Bard

## Debuffer

Purpose:

- weaken enemies,
- poison,
- slow,
- silence,
- reduce stats.

Possible advanced classes:

- Poisoner
- Controller
- Hexer

## Summoner

Purpose:

- summon extra units,
- swarm tactics,
- temporary helpers.

Possible advanced classes:

- Swarm Master
- Machine Master
- Creature Master

## Engineer

Purpose:

- turrets,
- mines,
- mechanical effects,
- battlefield control,
- repairing mechanical fighters.

## Crafter

Purpose:

- create equipment,
- improve crafting quality,
- unlock recipes,
- improve item rolls.

Crafter can be intentionally weaker in direct battle but more useful economically.

---

# 13. Hybrid classes

AI may suggest hybrid characters.

Examples:

```text
Tank / Engineer
Mage / Support
Fighter / Debuffer
Ranged / Summoner
```

A fighter can have:

```text
primaryClass
secondaryClass (optional)
```

No more than two classes.

---

# 14. Stats

MVP stats:

```text
HP
ATK
DEF
MAG
SPD
CRIT
```

Possible future stats:

```text
MAGIC_DEFENSE
ACCURACY
EVASION
BLOCK
RESISTANCE
```

---

# 15. Power Budget

Every fighter should use a Power Budget.

Example concept:

```text
Level 1 Common = 100 Power Budget
Level 1 Rare = 108
Level 1 Epic = 115
```

Rarity should provide an advantage, but not an overwhelming one.

Team composition and strategy should remain more important than raw rarity.

---

# 16. Balance Engine

The Balance Engine receives:

```text
class
secondaryClass
traits
skillFamilies
rarity
level
seed
equipment bonuses
```

It returns:

```text
stats
skill parameters
powerScore
```

Example formula concept:

```text
CharacterPower =
HP × hpWeight
+ ATK × attackWeight
+ DEF × defenseWeight
+ MAG × magicWeight
+ SPD × speedWeight
+ SkillPower
```

Class-specific weight profiles determine stat distribution.

Example Tank:

```text
HP: very high
DEF: high
ATK: low
SPD: low
```

Example Assassin:

```text
HP: low
DEF: low
ATK: high
SPD: very high
CRIT: high
```

---

# 17. Skills

AI can select only from predefined skill families.

Initial skill families:

```text
direct_damage
area_damage
heal
shield
taunt
stun
slow
silence
poison
bleed
buff_attack
buff_defense
buff_speed
debuff_attack
debuff_defense
summon
lifesteal
counterattack
execute
cleanse
revive
```

The AI describes the theme.

The backend assigns legal values.

---

# 18. Example fighter

## Electro-Pastabot

Class:

```text
Engineer / Ranged
```

Traits:

```text
Electric
Mechanical
```

Example stats:

```text
HP 110
ATK 24
DEF 18
MAG 31
SPD 22
CRIT 5%
```

Skills:

### Pasta Short Circuit

Deals magical damage and has a limited chance to stun.

### Overload

When HP drops below a threshold, speed increases temporarily.

---

# 19. Mutations

An existing fighter may later be mixed again.

Example:

```text
Pastabot
+
Fire
+
Legendary Fork
```

Result:

```text
Pasta Inquisitor
```

Mutation may:

- alter appearance,
- add or replace a trait,
- replace one skill,
- unlock secondary class,
- alter combat style.

Mutation must not create unlimited power scaling.

---

# 20. Team system

MVP team size:

```text
3 fighters
```

Possible future size:

```text
5 fighters
```

Suggested positions:

```text
front
middle
back
```

Example:

```text
       BACK

 [Mage] [Support] [Ranged]

      FRONT

 [Tank]       [Fighter]
```

For a 3-person MVP:

```text
Front
Middle
Back
```

The same fighter cannot occupy more than one team slot.

---

# 21. Targeting logic

Examples:

Tank:

```text
nearest enemy
```

Assassin:

```text
weakest valid backline target
```

Support:

```text
lowest HP ally
```

Ranged:

```text
nearest valid target
```

Mage:

```text
cluster / highest AoE value
```

---

# 22. Class counters

Avoid hard rock-paper-scissors rules.

Use soft counters.

Examples:

Assassin:

- strong against Support,
- strong against Mage,
- vulnerable when controlled by Tank.

Mage:

- strong against groups,
- vulnerable to Assassin.

Tank:

- protects backline,
- lower damage.

Debuffer:

- reduces Berserker effectiveness.

Engineer:

- controls battlefield positioning.

Support:

- enables team synergies,
- weak alone.

---

# 23. Equipment

MVP slots:

```text
Weapon
Armor
Accessory
```

Possible future slots:

```text
Weapon
Armor
Head
Accessory
Artifact
```

Examples:

## Legendary Stefan's Fork

```text
+14 ATK
+3% CRIT
```

Special effect:

```text
Puncture
```

## Can Armor

```text
+40 HP
+12 DEF
```

## Smelly Shaman Sock

```text
+8 MAG
+6% Debuff Power
```

---

# 24. Equipment rarity

Player-facing naming can be humorous.

Internal values:

```text
common
uncommon
rare
epic
legendary
```

Possible displayed names:

```text
Normal
Weird
Very Weird
What Is This?!
Absurd
```

---

# 25. Item sets

Future mechanic.

Example:

## Garage Set

2 pieces:

```text
+5% DEF
```

4 pieces:

```text
10% chance to counterattack
```

Not required for MVP.

---

# 26. Crafting

Crafter characters can produce equipment.

Example recipe:

```text
3x Screw
2x Metal Plate
1x Battery
1x Magnet
```

Result:

```text
Electro Armor
```

Craft result can depend on:

```text
crafterLevel
recipeLevel
materialsQuality
seed
```

Possible quality levels:

```text
Normal
Good
Excellent
Masterwork
```

---

# 27. PvE

Main PvE modes:

## Campaign

Story progression through regions.

## Bosses

Reward:

- equipment,
- rare ingredients,
- materials,
- cans.

## Tower

Increasing difficulty.

## Daily Challenge

Example modifier:

```text
Electric fighters gain +20% speed today.
```

## World Boss

Future mechanic.

Many players contribute damage to a shared boss.

---

# 28. PvP

Can Fighters should support two types of PvP.

---

# 29. Asynchronous PvP Arena

This should be implemented first.

Player sets:

```text
Defense Team
```

Another player can challenge it even when the owner is offline.

Flow:

1. Create immutable team snapshots.
2. Generate battle seed.
3. Run battle on backend.
4. Save result.
5. Update PvP rating.
6. Return battle events.
7. Notify defender by WebSocket if online.

Benefits:

- technically simpler,
- suitable for web/mobile,
- no need for simultaneous connection,
- easier anti-cheat.

---

# 30. PvP ranking

Possible leagues:

```text
Bronze
Silver
Gold
Platinum
Diamond
Master
Can Legend
```

Matchmaking may consider:

```text
MMR
Team Power
Season
```

PvP should not match only by raw Power Score.

---

# 31. Live PvP

Future phase.

Both players are online.

Recommended combat style:

- basic attacks are automatic,
- energy regenerates,
- player chooses when to use special skills,
- player may select target.

This gives active gameplay without requiring 30–60 actions per second.

---

# 32. Backend-authoritative combat

The frontend must never tell the backend:

```json
{
  "damage": 9999
}
```

The client may only send intentions.

Example:

```json
{
  "type": "activate_skill",
  "characterId": "char_71",
  "skillId": "electric_shock",
  "targetId": "char_83"
}
```

Backend validates:

```text
battle active?
fighter belongs to player?
fighter alive?
skill exists?
cooldown ready?
enough energy?
target legal?
```

Then the backend calculates the result.

---

# 33. Battle Engine

The Battle Engine should be a separate Laravel domain module.

Suggested structure:

```text
app/
  Domain/
    Battle/
      BattleEngine.php
      BattleState.php
      BattleUnit.php
      DamageCalculator.php
      TargetSelector.php
      SkillResolver.php
      Effects/
      ValueObjects/
```

The Battle Engine should:

- accept two teams,
- accept seed,
- simulate battle,
- choose targets,
- process attacks,
- process skills,
- process deaths,
- return battle result,
- return ordered battle events.

Input concept:

```json
{
  "seed": 938472,
  "teamA": [],
  "teamB": []
}
```

Output concept:

```json
{
  "winner": "teamA",
  "duration": 42,
  "events": []
}
```

---

# 34. Battle events

Example:

```json
{
  "sequence": 182,
  "time": 5100,
  "type": "skill_used",
  "source": "char_71",
  "target": "char_83",
  "skill": "electric_shock",
  "damage": 42,
  "effect": "stun",
  "effectDuration": 1500
}
```

The frontend only visualizes battle events.

Phaser should not determine damage or winner.

---

# 35. Battle replay

Save:

```text
seed
team snapshots
player decisions
battle version
```

This allows:

- replay,
- debugging,
- cheat investigation,
- deterministic tests.

---

# 36. Frontend architecture

Recommended frontend stack:

```text
React
Vite
TypeScript
Phaser
TanStack Query
Laravel Echo
```

Vite is preferred over Next.js if SEO is not required.

Most of Can Fighters is an authenticated SPA.

React handles:

- Dashboard
- Inventory
- Mixer
- Characters
- Equipment
- Team Builder
- PvE map
- Arena
- Shop
- Account

Phaser handles:

- battle scene,
- fighter animations,
- projectiles,
- damage numbers,
- skill effects,
- battle presentation.

---

# 37. Backend architecture

Preferred backend stack:

```text
Laravel
PHP
PostgreSQL
Redis
Laravel Reverb
Laravel Echo
Laravel Queue
S3-compatible storage
```

Optional:

```text
Laravel Horizon
```

Laravel handles:

```text
auth
players
inventory
characters
AI Mixer
equipment
teams
crafting
PvE
PvP
economy
battle engine
rewards
realtime events
```

---

# 38. Realtime architecture

Do not implement full-page auto-refresh.

Use:

```text
REST API
+
WebSockets
```

Main rule:

> REST changes state. WebSocket informs clients about state changes.

Use Laravel Reverb for WebSockets.

Frontend uses Laravel Echo.

---

# 39. What should use REST

Examples:

```text
GET /api/game/bootstrap
GET /api/player/inventory
POST /api/cans/{id}/open
POST /api/mixer/mix
POST /api/characters/{id}/equip
POST /api/pve/stages/{id}/battle
POST /api/arena/challenge/{playerId}
```

---

# 40. What should use WebSockets

Examples:

```text
InventoryUpdated
CharacterCreated
MixerCompleted
EquipmentObtained
ArenaDefenseAttacked
ArenaRatingChanged
MatchFound
BattleStarted
BattleEvent
BattleFinished
```

---

# 41. React auto-refresh strategy

Recommended combination:

```text
TanStack Query
+
Laravel Echo
```

Example flow:

```text
Laravel changes inventory
       ↓
InventoryUpdated event
       ↓
Laravel Reverb
       ↓
WebSocket
       ↓
React
       ↓
queryClient.invalidateQueries(["inventory"])
       ↓
GET /api/player/inventory
       ↓
UI updates
```

This avoids polling the whole application.

---

# 42. Do not poll continuously

Avoid:

```text
GET /api/player every 2 seconds
GET /api/inventory every 2 seconds
GET /api/arena every 2 seconds
```

This creates unnecessary load.

WebSockets should notify the frontend when something meaningful changes.

---

# 43. Bootstrap endpoint

Create:

```http
GET /api/game/bootstrap
```

Response example:

```json
{
  "player": {},
  "currencies": {},
  "team": {},
  "cans": [],
  "notifications": [],
  "serverTime": "..."
}
```

Use it:

- after login,
- after full refresh,
- after reconnect,
- after long disconnection.

PostgreSQL/Laravel remains the source of truth.

WebSockets are only notifications.

---

# 44. Player WebSocket channel

Suggested private channel:

```text
private-player.{playerId}
```

Events may include:

```text
player.inventory.updated
player.currency.updated
can.opened
mixer.started
mixer.completed
mixer.failed
character.created
character.updated
equipment.obtained
equipment.equipped
arena.defense_attacked
arena.rating_updated
matchmaking.found
```

---

# 45. Battle WebSocket channel

For live PvP:

```text
private-battle.{battleId}
```

Events:

```text
battle.started
battle.event
battle.finished
player.connected
player.disconnected
```

---

# 46. Future channels

Possible future channels:

```text
private-clan.{clanId}
private-party.{partyId}
presence-world-boss.{bossId}
private-trade.{tradeId}
```

---

# 47. AI Mixer with queues

AI generation may take longer than a normal API request.

Recommended flow:

```text
POST /api/mixer/mix
        ↓
Laravel validates request
        ↓
Mix job queued
        ↓
AI generation
        ↓
validation
        ↓
Balance Engine
        ↓
save fighter
        ↓
mixer.completed WebSocket event
```

Initial API response:

```json
{
  "mixId": "mix_78122",
  "status": "processing"
}
```

Later WebSocket event:

```json
{
  "mixId": "mix_78122",
  "characterId": "char_99812"
}
```

Frontend can show a Mixer animation while processing.

---

# 48. AI provider abstraction

Do not bind game logic directly to one AI vendor.

Example PHP interface:

```php
interface CharacterGenerationProvider
{
    public function generateCharacter(
        CharacterGenerationInput $input
    ): CharacterGenerationResult;
}
```

Possible implementations:

```text
OpenAICharacterGenerationProvider
FallbackCharacterGenerationProvider
MockCharacterGenerationProvider
```

---

# 49. AI validation

Every AI result must pass:

```text
schema validation
↓
allowed class validation
↓
allowed traits validation
↓
allowed skill family validation
↓
semantic normalization
↓
Balance Engine
```

If AI fails:

```text
retry
```

If it fails again:

```text
fallback deterministic generator
```

The game must continue working even if external AI is unavailable.

---

# 50. AI cost control

Do not call AI for:

```text
every attack
every battle
opening menus
viewing a fighter
equipment changes
PvP calculations
```

Use AI only for:

```text
fighter creation
fighter mutation
optional future portrait generation
```

Store results permanently.

---

# 51. Redis

Redis is recommended for:

```text
queues
cache
rate limiting
temporary locks
matchmaking
online presence
battle locks
live battle state
WebSocket scaling
```

---

# 52. PostgreSQL

PostgreSQL should be the primary database and source of truth.

Store:

```text
players
inventory
fighters
stats
skills
equipment
teams
battles
rewards
PvP rating
mix history
economy transactions
```

---

# 53. Object storage

Use S3-compatible storage for:

```text
fighter portraits
generated images
avatars
battle assets
future uploaded assets
```

---

# 54. Data model

Main entities:

## User

```text
id
email
password
display_name
created_at
updated_at
```

## PlayerProfile

```text
id
user_id
level
xp
coins
rating
created_at
updated_at
```

## IngredientDefinition

```text
id
slug
name
rarity
tags
power_value
```

## PlayerIngredient

```text
player_id
ingredient_definition_id
quantity
```

## CanDefinition

```text
id
slug
name
rarity
```

## PlayerCan

```text
id
player_id
can_definition_id
quantity
```

## Fighter

```text
id
player_id
name
description
generation_seed
generation_version
primary_class
secondary_class
rarity
level
xp
traits
visual_dna
created_at
updated_at
```

## FighterStats

```text
fighter_id
hp
attack
defense
magic
speed
crit
power_score
```

## FighterSkill

```text
fighter_id
skill_definition_id
level
parameters
```

## EquipmentDefinition

```text
id
slug
name
slot
base_stats
rarity
```

## PlayerEquipment

```text
id
player_id
equipment_definition_id
rarity
level
seed
rolled_stats
```

## FighterEquipment

```text
fighter_id
player_equipment_id
slot
```

## Team

```text
id
player_id
type
```

## TeamMember

```text
team_id
fighter_id
position
```

## Battle

```text
id
type
seed
player_a_id
player_b_id
winner_player_id
battle_version
created_at
```

## BattleSnapshot

```text
battle_id
team
snapshot_json
```

## MixRequest

```text
id
player_id
seed
status
generation_version
input_json
result_fighter_id
created_at
completed_at
```

---

# 55. API MVP

## Auth

```text
POST /api/auth/register
POST /api/auth/login
POST /api/auth/logout
GET /api/auth/me
```

## Bootstrap

```text
GET /api/game/bootstrap
```

## Player

```text
GET /api/player/profile
GET /api/player/inventory
```

## Ingredients

```text
GET /api/ingredients
```

## Cans

```text
GET /api/cans
POST /api/cans/{id}/open
```

## Mixer

```text
POST /api/mixer/preview
POST /api/mixer/mix
GET /api/mixer/{mixId}
```

## Fighters

```text
GET /api/fighters
GET /api/fighters/{id}
POST /api/fighters/{id}/upgrade
POST /api/fighters/{id}/mutate
```

## Equipment

```text
GET /api/equipment
POST /api/fighters/{id}/equip
POST /api/fighters/{id}/unequip
```

## Teams

```text
GET /api/teams
POST /api/teams
PUT /api/teams/{id}
```

## PvE

```text
GET /api/pve/stages
POST /api/pve/stages/{id}/battle
GET /api/pve/battles/{id}
```

## Arena

```text
PUT /api/arena/defense-team
GET /api/arena/opponents
POST /api/arena/challenge/{playerId}
GET /api/arena/ranking
GET /api/arena/history
```

## Live PvP — future

```text
POST /api/arena/live/queue
DELETE /api/arena/live/queue
POST /api/battles/{battleId}/actions
```

---

# 56. Economy rules

All critical economic operations happen on backend.

Backend controls:

```text
drops
currencies
inventory
cans
mixing
crafting
equipment rewards
battle rewards
PvP rating
experience
```

Frontend is never trusted.

---

# 57. Transactions

Mixing should be atomic.

Example:

```text
BEGIN TRANSACTION

validate ingredients
reserve/consume ingredients
create mix request
generate/save fighter
update collection
write economy transaction

COMMIT
```

On failure:

```text
ROLLBACK
```

---

# 58. Idempotency

Use idempotency keys for critical actions:

```text
open can
mix fighter
claim reward
craft item
purchase
```

This protects against:

- double-clicks,
- retries,
- poor mobile connections,
- duplicate network requests.

---

# 59. Anti-cheat rules

All important calculations must be server-side.

Never trust frontend values for:

```text
damage
winner
drop
XP
currency
fighter stats
equipment ownership
PvP result
skill cooldown
energy
```

---

# 60. Responsive UI

Design mobile-first.

Minimum target width:

```text
320 px
```

Desktop:

```text
Sidebar + Main Content
```

Mobile:

```text
Main Content
+
Bottom Navigation
```

Suggested bottom navigation:

```text
Home
Mixer
Battle
Team
More
```

---

# 61. Main screens

## Dashboard

Shows:

- current team,
- cans,
- quests,
- currency,
- quick actions,
- latest rewards.

## Cans

Open cans.

## Mixer

Select ingredients.

Suggested slots:

```text
Slot 1
Slot 2
Slot 3
Slot 4
```

Button:

```text
MIX
```

## Fighter Reveal

Show:

```text
NEW FIGHTER!
```

Then:

- name,
- class,
- rarity,
- traits,
- skills,
- visual.

## Fighter Details

Show:

```text
portrait
name
level
class
traits
stats
skills
equipment
history
```

## Team Builder

Desktop:

- drag & drop.

Mobile:

- tap to select position.

## Inventory

Show:

- equipment,
- materials,
- ingredients,
- cans.

## PvE Map

Regions and stages.

## Arena

Show:

- opponents,
- ranking,
- battle history,
- defense team.

## Battle

Rendered using Phaser.

---

# 62. Art direction

Recommended style:

```text
cartoon
bright
funny
toy-like
readable
non-realistic
```

Avoid realistic violence.

The absurdity of combinations should be visually obvious.

---

# 63. Visual DNA

Initially, fighters can be assembled from predefined parts.

Example:

```json
{
  "body": "can",
  "head": "battery",
  "arms": "forks",
  "legs": "pasta",
  "effect": "electric"
}
```

This is cheaper and more consistent than generating a new image for every fighter.

AI-generated fighter portraits can be added later.

---

# 64. Recommended repository structure

```text
can-fighters/
│
├── apps/
│   ├── web/
│   │   ├── React
│   │   ├── Vite
│   │   ├── TypeScript
│   │   ├── Phaser
│   │   ├── TanStack Query
│   │   └── Laravel Echo
│   │
│   └── api/
│       └── Laravel
│
├── docs/
│   ├── GAME_DESIGN.md
│   ├── ARCHITECTURE.md
│   ├── API.md
│   ├── BALANCE.md
│   └── REALTIME.md
│
├── docker/
├── docker-compose.yml
└── README.md
```

Laravel domain structure:

```text
apps/api/app/
│
├── Domain/
│   ├── Battle/
│   ├── Fighters/
│   ├── Inventory/
│   ├── Mixer/
│   ├── Equipment/
│   ├── PvE/
│   ├── Arena/
│   └── Economy/
│
├── Events/
├── Jobs/
├── Http/
└── Models/
```

---

# 65. MVP scope

Content:

```text
12 ingredients
6 base classes
20–30 trait combinations
20–30 equipment items
15 PvE stages
3 bosses
1 basic can
3-fighter teams
3 equipment slots
```

Base classes:

```text
Tank
Fighter
Ranged
Mage
Support
Engineer
```

Systems:

```text
Auth
Dashboard
Inventory
Cans
AI Mixer
Fighters
Teams
Equipment
PvE
Async PvP Arena
Ranking
Realtime notifications
```

---

# 66. Not in MVP

Do not build initially:

```text
guilds
clans
chat
player trading
marketplace
auction house
world boss
advanced crafting
live PvP
5-person teams
AI animation generation
complex social systems
```

---

# 67. MVP Definition of Done

A new player can:

1. Create an account.
2. Log in.
3. Receive starter cans.
4. Open a can.
5. Receive ingredients.
6. Put ingredients into the Mixer.
7. Create an AI-generated fighter.
8. See fighter class, traits and stats.
9. Add fighter to a team.
10. Equip an item.
11. Start a PvE battle.
12. Watch an animated battle.
13. Receive rewards.
14. Configure PvP Defense Team.
15. Attack another player asynchronously.
16. Gain or lose PvP rating.
17. Receive relevant realtime notifications without refreshing the page.

---

# 68. Development roadmap

## Phase 0 — Project foundation

Create:

```text
React + Vite frontend
Laravel backend
PostgreSQL
Redis
Laravel Reverb
Queue setup
Docker
```

## Phase 1 — Auth and player profile

Implement:

```text
registration
login
profile
bootstrap endpoint
```

## Phase 2 — Inventory and cans

Implement:

```text
ingredients
cans
drop tables
opening cans
idempotency
```

## Phase 3 — AI Mixer

Implement:

```text
mix requests
AI provider abstraction
fallback generator
validation
queue processing
mixer.completed event
```

## Phase 4 — Fighters

Implement:

```text
fighter model
stats
traits
skills
character details
team builder
```

## Phase 5 — Balance Engine

Implement:

```text
Power Budget
class stat profiles
skill parameter resolver
Power Score
```

## Phase 6 — Battle Engine

Implement:

```text
deterministic seed
basic attacks
skills
targeting
damage
healing
effects
battle result
battle events
```

## Phase 7 — PvE

Implement:

```text
Kitchen region
stages
boss
rewards
battle view
```

## Phase 8 — Equipment

Implement:

```text
weapon
armor
accessory
equipment stats
equip/unequip
```

## Phase 9 — Async PvP

Implement:

```text
defense team
opponents
battle snapshots
rating
history
WebSocket notifications
```

## Phase 10 — Responsive polish

Optimize:

```text
mobile
tablet
desktop
touch
loading states
offline/reconnect
```

## Phase 11 — Live PvP

Only after MVP is stable.

Implement:

```text
matchmaking
Redis queue
battle room
WebSocket battle events
server-authoritative actions
reconnect
disconnect grace period
```

---

# 69. Key tests

## Battle Engine

Test:

```text
damage calculation
target selection
death
heal
shield
buff
debuff
stun
speed
critical hits
same seed = same result
```

## Economy

Test:

```text
opening can
double request
insufficient inventory
mixing
reward claiming
transaction rollback
```

## AI

Test:

```text
invalid JSON
invalid class
unknown skill family
timeout
provider exception
fallback generator
```

## PvP

Test:

```text
snapshot immutability
rating update
invalid target
unauthorized challenge
duplicate challenge request
result cannot be manipulated by client
```

## Realtime

Test:

```text
private channel authorization
event delivery
reconnect
bootstrap state refresh
```

---

# 70. Recommended realtime rule

Use this rule throughout Can Fighters:

> PostgreSQL and Laravel are the source of truth. WebSockets are delivery and notification mechanisms only.

If the socket connection is lost:

- the account remains correct,
- the battle record remains correct,
- rewards remain correct,
- the frontend can recover from `/api/game/bootstrap`.

---

# 71. Recommended architecture summary

```text
                        CAN FIGHTERS

┌───────────────────────────────────────────────────┐
│                    BROWSER                        │
│                                                   │
│ React + Vite + TypeScript                         │
│ TanStack Query                                    │
│ Laravel Echo                                      │
│ Phaser                                            │
└──────────────────────┬────────────────────────────┘
                       │
             REST API  │  WebSocket
                       │
            ┌──────────▼───────────┐
            │      LARAVEL API     │
            │                      │
            │ Auth                 │
            │ Inventory            │
            │ Mixer                │
            │ Fighters             │
            │ Equipment            │
            │ Teams                │
            │ Battle Engine        │
            │ PvE                  │
            │ Arena                │
            │ Economy              │
            └──────┬────────┬──────┘
                   │        │
          ┌────────▼───┐ ┌──▼───────────────┐
          │ PostgreSQL │ │ Laravel Reverb   │
          └────────────┘ │ WebSockets       │
                         └────────┬──────────┘
                                  │
                           ┌──────▼──────┐
                           │    Redis    │
                           └─────────────┘

                    Laravel Queue
                         │
                         ▼
                    AI Provider

                    S3 Storage
```

---

# 72. Master instruction for an AI coding agent

You are the lead software architect and senior full-stack engineer responsible for building a responsive browser game called **Can Fighters**.

Use this document as the source of product and architecture requirements.

Technology stack:

```text
Frontend:
React
Vite
TypeScript
Phaser
TanStack Query
Laravel Echo

Backend:
Laravel
PostgreSQL
Redis
Laravel Reverb
Laravel Queue

Storage:
S3-compatible storage
```

Core architecture rules:

1. Laravel is authoritative.
2. PostgreSQL is the primary source of truth.
3. WebSockets are used for realtime events, not as the permanent state store.
4. REST API performs normal game actions.
5. Laravel Reverb broadcasts state changes and realtime events.
6. Phaser only visualizes battles.
7. Battle calculations happen in Laravel.
8. The AI Mixer creates creative concepts only.
9. AI may not choose numeric combat balance.
10. Balance Engine controls all final stats.
11. All critical inventory operations must be transactional.
12. Use idempotency keys for economic actions.
13. Do not trust any damage, rewards, stats or PvP result sent by frontend.
14. Keep the project mobile-first and responsive from 320 px width.
15. Do not build features outside the current implementation phase unless required by the architecture.
16. Prefer simple, testable implementations over premature abstractions.
17. Add automated tests for all critical game logic.
18. When a socket connection is lost, the client must be able to rebuild game state from REST API.
19. AI failure must not prevent gameplay; always provide a deterministic fallback generator.
20. Keep external AI providers behind an interface.

After each implementation phase:

1. run backend tests,
2. run frontend tests,
3. run lint,
4. run TypeScript checks,
5. run PHP static analysis if configured,
6. fix failures,
7. update documentation.

Do not implement guilds, marketplace, player trading, chat, live PvP, world bosses or advanced crafting until the MVP is complete.

---

# 73. First vertical slice

The first fully playable sequence should be:

```text
START
↓
player receives 3 cans
↓
opens a can
↓
receives Pasta + Screw + Battery
↓
opens Mixer
↓
submits ingredients
↓
Laravel queues AI generation
↓
Mixer animation starts
↓
AI creates Electro-Pastabot concept
↓
Balance Engine assigns legal stats
↓
fighter saved in PostgreSQL
↓
mixer.completed event sent through Reverb
↓
frontend reveals fighter
↓
player adds fighter to team
↓
player enters first Kitchen stage
↓
Battle Engine simulates battle
↓
Phaser plays battle events
↓
player defeats Angry Fork
↓
player receives Can Armor
↓
player equips armor
↓
fighter becomes stronger
↓
player continues campaign
```

If this sequence is fun, the core of Can Fighters works.

---

# 74. Product principle

Before adding another major system, ask:

> Is mixing fighters, building the team and fighting already fun?

If not, improve:

```text
Mixer
Fighters
Combat
Loot
Team building
```

before expanding the game.

---

# 75. Final product identity

Can Fighters should feel like a mixture of:

- collectible creature game,
- party RPG,
- auto-battler,
- light MMORPG progression,
- equipment collection,
- experimentation sandbox.

But the project should maintain its own identity.

The strongest unique mechanic is:

> Players create unusual, persistent fighters by mixing strange ingredients through AI, then build teams and use them in PvE and PvP.

Example:

```text
Fish
+
Battery
+
3x Pasta
+
Smelly Sock
```

may create:

# Admiral Volt-Mackerel

```text
Mage / Support
Electric / Water / Chaos
```

Skill:

## Wet Short Circuit

Deals electric area damage and applies a short slow.

The fighter can then:

- gain levels,
- equip items,
- mutate,
- join a team,
- fight bosses,
- fight other players,
- become part of the player's permanent collection.

That loop should remain the heart of **Can Fighters**.
