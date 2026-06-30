# Hack N Slash

## What is this game?

Hack N Slash is a cooperative tactical dungeon crawler for 1 or 2 players. You control heroes fighting through a dungeon room by room, growing stronger until you face a final boss. You win or lose together.

---

## Setting Up

### Choose a Scenario

- **Dungeon**: Fight through rooms and level up your powers before the boss.
- **Boss Fight**: Start fully powered and face the boss immediately.

### Choose a Difficulty

| Difficulty | Starting HP |
| ---------- | ----------- |
| Easy       | 20          |
| Normal     | 10          |
| Hard       | 5           |
| Hardcore   | 1           |

### Player Count

| Players | Actions per round | Starting room |
| ------- | ----------------- | ------------- |
| 1       | 2 actions         | Room 1        |
| 2       | 1 action each     | Room 3        |

Each hero starts with 2 basic power cards: **Attack** and **Strike**.

---

## The Dungeon

The dungeon has **7 rooms** followed by a **boss room** (room 8).

Each room is a grid filled with monsters. Clear all monsters in a room to advance. Early rooms (1-3) are smaller (5x5); later rooms are larger (7x7).

### Terrain

| Terrain | Can walk on? | Blocks line of sight? | Effect                                       |
| ------- | :----------: | :-------------------: | -------------------------------------------- |
| Floor   |     Yes      |          No           | —                                            |
| Spikes  |     Yes      |          No           | 1 damage per round to anything standing here |
| Wall    |      No      |          Yes          | —                                            |
| Pillar  |      No      |          Yes          | —                                            |
| Hole    |      No      |          No           | —                                            |
| Entry   |      No      |          No           | —                                            |
| Exit    |      No      |          No           | —                                            |

---

## Round Structure

Each round follows these steps in order:

1. **Free Move** — Each hero may move 1 tile (orthogonal only). Optional.
2. **Hero Actions** — Heroes play power cards, spending action points.
3. **Cooldown** — All power cooldowns tick down by 1.
4. **Traps** — Spikes deal 1 damage to every entity on them.
5. **Small Monsters** — Activate from closest to farthest from a hero.
6. **Big Monsters** — Activate from closest to farthest.
7. **Boss** — Activates last.

A hero's turn ends when they have used their free move, spent all action points, and have no free actions available (or choose to pass).

---

## Movement

**Free Move**: Exactly 1 tile, orthogonal (up/down/left/right). Cannot be used while Slimed.

**Line of Sight**: Required for all ranged attacks. Walls and pillars block it.

---

## Powers

Powers are your main tools. Each power card has:

- A **cooldown** (typically 2 rounds after use)
- A **range** (minimum and maximum distance)
- A **range type**: orthogonal (cardinal directions only) or diagonal (includes diagonals)

### Basic Powers

|                                                            |            |                                              |
| ---------------------------------------------------------- | ---------- | -------------------------------------------- |
| ![Attack](src/resources/images/cards/powers/attack-0.webp) | **Attack** | 1 damage, range 0-1 (diagonal), cooldown 2   |
| ![Strike](src/resources/images/cards/powers/strike-0.webp) | **Strike** | 2 damage, range 0-1 (orthogonal), cooldown 2 |

### Power Cards

Each power exists in 3 ranks. Higher ranks have better range, damage, area, or reduced cooldown.

| Power            | Rank 1                                                     | Rank 2                                                     | Rank 3                                                     |
| ---------------- | ---------------------------------------------------------- | ---------------------------------------------------------- | ---------------------------------------------------------- |
| **Dash**         | ![](src/resources/images/cards/powers/dash-1.webp)         | ![](src/resources/images/cards/powers/dash-2.webp)         | ![](src/resources/images/cards/powers/dash-3.webp)         |
| **Dash Attack**  | ![](src/resources/images/cards/powers/dash-attack-1.webp)  | ![](src/resources/images/cards/powers/dash-attack-2.webp)  | ![](src/resources/images/cards/powers/dash-attack-3.webp)  |
| **Fireball**     | ![](src/resources/images/cards/powers/fireball-1.webp)     | ![](src/resources/images/cards/powers/fireball-2.webp)     | ![](src/resources/images/cards/powers/fireball-3.webp)     |
| **Whirlwind**    | ![](src/resources/images/cards/powers/whirlwind-1.webp)    | ![](src/resources/images/cards/powers/whirlwind-2.webp)    | ![](src/resources/images/cards/powers/whirlwind-3.webp)    |
| **Vortex**       | ![](src/resources/images/cards/powers/vortex-1.webp)       | ![](src/resources/images/cards/powers/vortex-2.webp)       | ![](src/resources/images/cards/powers/vortex-3.webp)       |
| **Jump**         | ![](src/resources/images/cards/powers/jump-1.webp)         | ![](src/resources/images/cards/powers/jump-2.webp)         | ![](src/resources/images/cards/powers/jump-3.webp)         |
| **Heal**         | ![](src/resources/images/cards/powers/heal-1.webp)         | ![](src/resources/images/cards/powers/heal-2.webp)         | ![](src/resources/images/cards/powers/heal-3.webp)         |
| **Projection**   | ![](src/resources/images/cards/powers/grab-1.webp)         | ![](src/resources/images/cards/powers/grab-2.webp)         | ![](src/resources/images/cards/powers/grab-3.webp)         |
| **Power Strike** | ![](src/resources/images/cards/powers/power-strike-1.webp) | ![](src/resources/images/cards/powers/power-strike-2.webp) | ![](src/resources/images/cards/powers/power-strike-3.webp) |
| **Quick Strike** | ![](src/resources/images/cards/powers/quick-strike-1.webp) | ![](src/resources/images/cards/powers/quick-strike-2.webp) | ![](src/resources/images/cards/powers/quick-strike-3.webp) |
| **Quick Shot**   | ![](src/resources/images/cards/powers/quick-shot-1.webp)   | ![](src/resources/images/cards/powers/quick-shot-2.webp)   | ![](src/resources/images/cards/powers/quick-shot-3.webp)   |
| **Point Blank**  | ![](src/resources/images/cards/powers/point-blank-1.webp)  | ![](src/resources/images/cards/powers/point-blank-2.webp)  | ![](src/resources/images/cards/powers/point-blank-3.webp)  |
| **Leech**        | ![](src/resources/images/cards/powers/leech-1.webp)        | ![](src/resources/images/cards/powers/leech-2.webp)        | ![](src/resources/images/cards/powers/leech-3.webp)        |
| **Reinforce**    | ![](src/resources/images/cards/powers/reinforce-1.webp)    | ![](src/resources/images/cards/powers/reinforce-2.webp)    | ![](src/resources/images/cards/powers/reinforce-3.webp)    |

### Power Descriptions

| Power            | What it does                                                               |
| ---------------- | -------------------------------------------------------------------------- |
| **Dash**         | Move your hero in a straight line. Removes Slimed status.                  |
| **Dash Attack**  | Dash to a tile, then hit an adjacent enemy at your destination.            |
| **Fireball**     | Hit all enemies in an area around a target tile. Ignores shields.          |
| **Whirlwind**    | Optionally step, then hit all enemies around you.                          |
| **Vortex**       | Pull enemies within an area toward a chosen point.                         |
| **Jump**         | Leap to a distant tile. Push aside or damage monsters on the landing spot. |
| **Heal**         | Restore HP to yourself or a nearby ally (cannot exceed starting max).      |
| **Projection**   | Shove an adjacent enemy away from you.                                     |
| **Power Strike** | A heavy melee blow, optionally with knockback.                             |
| **Quick Strike** | Hit multiple enemies in melee range.                                       |
| **Quick Shot**   | Hit multiple enemies at range.                                             |
| **Point Blank**  | Ranged shot that pushes the target back.                                   |
| **Leech**        | Melee attack that heals you for 1 HP per hit. Ignores shields.             |
| **Reinforce**    | Empower a hero: their next card this turn is free (doubled Dash distance). |

### Free Actions

Some powers can be triggered **for free** when a specific event happens earlier in the same turn:

| Trigger           | When it fires                                          |
| ----------------- | ------------------------------------------------------ |
| After Move        | You just used your free move                           |
| After Dash        | You just dashed or jumped                              |
| After Card Played | You just played any card                               |
| After Kill        | You just killed a monster                              |
| After Push/Pull   | You just pushed or pulled an enemy                     |
| After Dash Attack | You just resolved a Dash Attack (for multi-hit chains) |
| Shield Broken     | You just broke a monster's shield                      |

**Important**: Each free power can only trigger once per turn chain. Free actions do not cost action points.

**Multi-play powers** (Dash Attack rank 2+): A single action point lets you chain multiple executions in sequence.

---

## Monsters

### Bestiary

| Monster    | Card                                                          | Size  | HP  | Special                                                   |
| ---------- | ------------------------------------------------------------- | :---: | :-: | --------------------------------------------------------- |
| Goblins    | ![](src/resources/images/cards/monsters/gobelins.webp)        | Small |  1  | Always spawn in pairs                                     |
| Slime      | ![](src/resources/images/cards/monsters/slime.webp)           | Small |  2  | Immobilize heroes instead of dealing damage               |
| Evil Eye   | ![](src/resources/images/cards/monsters/evil-eye.webp)        | Small |  1  | Ranged, cannot move                                       |
| Kamikaze   | ![](src/resources/images/cards/monsters/goblin-kamikaze.webp) | Small |  1  | Explodes when killed, dealing 2 damage to adjacent heroes |
| Wizard     | ![](src/resources/images/cards/monsters/goblin-wizard.webp)   | Small |  2  | Long range (2-4), moves to maintain distance              |
| Bomber     | ![](src/resources/images/cards/monsters/goblin-bomber.webp)   | Small |  3  | Medium range (2-3), sturdy                                |
| Orc        | ![](src/resources/images/cards/monsters/orc.webp)             |  Big  |  6  | Hits everything in front of it (arc of 3 tiles)           |
| Pig Rider  | ![](src/resources/images/cards/monsters/pig-rider.webp)       |  Big  |  5  | Charges and pushes the hero back                          |
| Wolf Rider | ![](src/resources/images/cards/monsters/wolf-rider.webp)      |  Big  |  3  | Summons a Goblin, then runs away                          |

### How Monsters Behave

Monsters act automatically. On their activation:

1. They identify the **closest hero** as their target.
2. If they can already hit their target, they **attack** (they don't move).
3. If they cannot, they **move toward** attack range.

Monsters never attack and move in the same activation — except the Wolf Rider, which summons then flees in one turn.

### Special Abilities

**Charge** (Pig Rider): Moves its full movement, then slams into the hero, pushing them 1 tile back. If the hero can't be pushed (wall behind), both take 1 extra damage.

**Front Arc** (Orc): Attacks all heroes in a 3-tile cone in front of it (the tile ahead + both diagonals in that direction).

**Explode** (Kamikaze): When this creature dies, it deals its damage to all heroes adjacent to it (orthogonally).

**Summon & Flee** (Wolf Rider): Places a Goblin on a nearby tile, then moves away from the closest hero.

**Slime** (Slime): Instead of dealing damage, applies the **Slimed** status. A Slimed hero cannot use their free move or any movement power — except Dash, which breaks free. The status lasts as long as the hero stays adjacent to a living Slime.

### Stacking

Goblins can share a tile: up to 2 Goblins may occupy the same space. Stacked Goblins move and attack together as a group.

---

## Level Enchantments

Some rooms apply a modifier to **all monsters** in that room:

|                                                        | Enchantment  | Effect                                                                                                                                                                                                          |
| ------------------------------------------------------ | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ![](src/resources/images/cards/monsters/shielded.webp) | **Shielded** | Every monster gains a shield that absorbs the first hit entirely, no matter how much damage it would deal. Fireball and Leech ignore shields. Breaking a shield triggers the "Shield Broken" free action event. |
| ![](src/resources/images/cards/monsters/spiky.webp)    | **Spiky**    | Whenever a hero hits a monster in melee (adjacent, orthogonal), the hero takes 1 damage back.                                                                                                                   |

---

## Bosses

After clearing 7 rooms, you reach the boss. Bosses have **3 phases**, each with its own health pool.

### Slasher

![](src/resources/images/cards/monsters/slasher-1.webp) ![](src/resources/images/cards/monsters/slasher-2.webp) ![](src/resources/images/cards/monsters/slasher-3.webp)

A fast melee bruiser. Moves 2 tiles per turn and strikes everything adjacent.

| Phase | HP  | Behavior                                                                                    |
| :---: | :-: | ------------------------------------------------------------------------------------------- |
|   1   |  8  | Moves 2, attacks adjacent (2 damage)                                                        |
|   2   |  8  | **Pre-action**: spawns 2 minions. Then moves and attacks.                                   |
|   3   |  8  | **Pre-actions**: grants shield to all creatures + spawns 2 minions. Then moves and attacks. |

### Striker

![](src/resources/images/cards/monsters/striker-1.webp) ![](src/resources/images/cards/monsters/striker-2.webp) ![](src/resources/images/cards/monsters/striker-3.webp)

A devastating area attacker. Steps forward, then slams the ground around it.

| Phase | HP  | Behavior                                                                                                       |
| :---: | :-: | -------------------------------------------------------------------------------------------------------------- |
|   1   | 10  | Moves 1 (diagonal OK), then deals 2 damage to everyone within 1 tile (all 8 surrounding tiles + its own tile). |
|   2   | 10  | **Pre-action**: charges the closest hero (1 damage, push). Then area attack.                                   |
|   3   | 10  | **Pre-actions**: grants shield + charges. Then area attack.                                                    |

### Boss Rules

- **Phase transition**: When a boss phase is defeated, all hero actions stop immediately. The round jumps to the enemy phase (cooldown → traps → monster activation) with the boss entering its new phase at full HP.
- **Pre-actions** happen at the start of the boss's activation (step 7 of the round). They may grant shields, spawn minions, or charge.
- **Newly spawned minions do not act on the turn they appear.** Since small monsters activate before bosses (step 5), any minions spawned during the boss's pre-actions won't move until the next round.
- **Winning**: Defeat the boss's third phase to win the game.

---

## Rewards

After clearing a room (not the boss room), each player chooses **one** reward:

**Option A — New Power**: Pick a rank-1 power from a shared offer of 3 cards. It replaces one of your current powers. Powers from families you already own are excluded from the offer.

**Option B — Upgrade**: Upgrade one of your current powers to its next rank.

In 2-player games, both players see the same new-power offer. If one player takes a card, it disappears from the other's offer. Upgrade offers are personal.

---

## Collisions

When a pushed or pulled creature is blocked (wall, occupied tile, board edge):

- The blocked creature takes **1 damage**.
- If the blocking tile contains a monster, that monster also takes **1 damage**.

---

## Winning and Losing

| Outcome     | Condition                                     |
| ----------- | --------------------------------------------- |
| **Victory** | Defeat the boss's third phase.                |
| **Defeat**  | Any hero reaches 0 HP. Game over immediately. |
