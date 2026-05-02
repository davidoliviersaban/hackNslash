# HackNSlash BGA Scaffold

This is the initial BoardGameArena structure for HackNSlash.

It provides:
- BGA entry files: PHP game/action/view, JS, CSS, template.
- Database tables for cards, dungeon tiles, entities, and global variables.
- A minimal state machine: player turn, action resolution, next player, game end.
- Modular PHP traits inspired by the Windwalkers BGA implementation.
- Placeholder material files to be replaced or generated from `src/resources`.
- Asset folders ready for BGA deployment.

## Rule Book Notes

This section records clarified rules as they are provided, so the implementation does not drift.

- Base powers have cooldown `1`, so after being used they are reusable on the next turn; evolved powers generally have cooldown `2`.
- Heroes start with `10` HP.
- The game ends immediately in defeat when any hero reaches `0` HP.
- Each hero starts with 3 cards always in front of them: `1` Strike / Coup and `2` Attack / Frappe.
- Each time a level is completed, the team receives an upgrade offer of `3` randomly drawn level-1 powers from the power deck. A hero can choose to upgrade an existing card. If the upgraded card is a base card, the hero can take a new power from the offer instead. Otherwise players may choose either to take one offered power or upgrade an existing level-1+ power.
- Attack / Frappe deals `1` damage at range `1`, diagonals included.
- Strike / Coup deals `2` damage at range `1`, orthogonal only.
- Impulsion / Dash is free after any card, has orthogonal range `2`, cooldown `2` at levels 1 and 2, and cooldown `1` at level 3.
- Impulsion / Dash cannot target an obstacle.
- Vortex / Trou noir is a ranged attack; ranged attacks use Chebyshev distance. It selects a tile at range `2`, then chooses up to `2` monsters at distance `1` from that tile and pulls them onto/toward it using Chebyshev movement, triggering `afterPushOrPull`.
- Monster movement resolution always follows monster activation order: small monsters first, then big monsters, then bosses. Vortex follows this order for pulled monsters.
- Each level number determines how many monster slots are filled: level `1` has `1` monster on slot `1`, level `2` fills slots `1` and `2`, and so on through level `7`. Level `8` is the boss level.
- Bosses have `3` phases. When a boss reaches `0` HP, all current actions are interrupted, the boss immediately takes its turn, then advances to the next phase. When the third phase is defeated, the game ends in victory.
- There are two bosses: Slasher and Striker. Slasher is generally the first boss encountered. Slasher phase 1 has `8` HP, moves `2` tiles with diagonals, and deals `2` melee damage.
- Slasher phase 2 spawns `2` minions (goblins or slimes) before doing its movement and attack action. Slasher phase 3 casts Shield / Bouclier on all creatures, then spawns minions, then attacks. Creatures cannot have multiple shields.
- Each monster has a specific AI profile. The default AI is simple: if it can attack, it attacks; otherwise it moves toward the closest player. Some monsters can attack and move in the same activation, and some monsters cannot move.
- Goblins always arrive in pairs. Each goblin has `1` HP, attacks orthogonally for `1` damage, and otherwise moves orthogonally by `1` tile.
- Slimes arrive in pairs. Each slime has `2` HP, moves up to `2` tiles diagonally, and sticks heroes at orthogonal range `1`.
- Evil Eye does not move. It shoots at range `3`, diagonals included, for `1` damage.
- Kamikaze has `1` HP, moves `1` tile diagonally, and explodes when orthogonal to a hero or when it dies. Its explosion deals `2` damage around itself orthogonally.
- Wizard has `2` HP, attacks orthogonally at range `1-4` for `1` damage, does not attack at range `0`, and otherwise moves `1` tile orthogonally.
- Bomber has `3` HP, moves orthogonally, attacks orthogonally at range `2-3` for `1` damage, and cannot shoot at orthogonal range `1`.
- Orc is a big monster with `4` HP. It moves `1` tile orthogonally and deals `2` damage to the 3 tiles in front of it: the orthogonal front tile and the 2 adjacent diagonal front tiles.
- Pig Rider is a big monster with `4` HP. It moves `1` tile and attacks orthogonally at range `1` for `1` damage by charging: it takes the hero's tile and pushes the hero back by `1` tile. If the hero cannot be pushed, both the Pig Rider and the hero take `1` collision damage.
- Wolf Rider is a big monster with `3` HP. It summons `1` goblin on an orthogonally adjacent tile, then moves `1` tile away from the hero.
- A tile can contain either `1` big monster or up to `2` small monsters. A small monster and a big monster cannot share the same tile.
- When the Thorns / Épines effect is drawn, every monster in the level gains thorns. Thorns deal `1` damage orthogonally to the hero attacking that monster, so heroes should attack thorned monsters diagonally or from range `2`.
- When the Shield / Bouclier effect is drawn, every monster in the level gains shield. The first damage dealt to each monster is fully absorbed, regardless of amount. The shield then breaks and disappears from that monster.
- When a monster or hero cannot move onto the desired tile because it is occupied, it collides: the moving entity and one occupying entity each take `1` damage. If several monsters occupy the tile, choose the weakest monster as the collision target. If the collision damage kills the blocking entity and the tile becomes legal, the moving entity can then enter the tile.
- When a monster or hero moves into an obstacle such as a wall or pillar, the moving entity takes `1` damage. Walls and pillars are indestructible. The moving entity follows its trajectory as far as possible and stops on the closest legal tile before the obstacle; for example a 3-tile projection into a wall stops 1 tile before the wall.
