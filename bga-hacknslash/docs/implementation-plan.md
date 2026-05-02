# HackNSlash BGA Implementation Plan

## Scope

- First playable scope is 1-2 player cooperative mode.
- BGA uses an abstract score/time budget, not a strict real-time ten minute clock.
- The first powers are fixed powers: Attack, Dash, and Vortex.
- The implementation is test-driven: add a failing test for each rule slice before writing the production code.

## Core Round Flow

The game is not a locked simultaneous planning game. It is an event-driven sequence where resolved actions can open free-action windows.

1. Room setup.
2. Main free-move step for heroes that still have a free move.
3. Free-action window after each resolved move, if events were produced.
4. Main hero-action step for heroes that still have a main action.
5. Free-action window after each resolved action, if events were produced.
6. Cooldown step.
7. Trap activation.
8. Small monster activation from nearest to farthest hero.
9. Large monster activation from nearest to farthest hero.
10. Boss activation in boss room only.
11. Room-end check and transition.

## Event Chain Rule

Resolved actions produce an active event chain, for example `afterMove`, `afterDash`, `afterPushOrPull`, or `afterKill`.

- A free action is available when at least one event in the active chain matches its trigger.
- When a free action is used, it consumes the triggering event and activates its cooldown immediately.
- Two players cannot consume the same event.
- The events produced by the free action replace the previous active chain.
- Events from the previous chain that were not consumed are abandoned.
- A card or power already used in the current chain cannot be used again in that same chain.
- A power in cooldown is not usable.
- If all eligible players pass, the active event chain is discarded and the game returns to the main flow.

## BGA State Machine Target

- `roomSetup`: create room terrain, monster slots, heroes, and room counters.
- `mainFreeMove`: active or multiactive state for the next hero free move.
- `resolveAction`: game state that applies one planned/resolved action and emits events.
- `freeActionWindow`: generic reaction state using the active event chain.
- `mainHeroAction`: active or multiactive state for the next hero main action.
- `cooldown`: reduce cooldowns for unused powers and finalize used powers.
- `activateTraps`: resolve spikes and terrain hazards.
- `activateSmallMonsters`: resolve small monsters by priority.
- `activateLargeMonsters`: resolve large monsters by priority.
- `activateBoss`: resolve boss behavior in room 8.
- `roomEndCheck`: advance room, end game, or start next round.

## Data Model Target

- Store current room, round number, abstract time/score budget, and phase.
- Store hero flags per round: free move remaining, main action remaining.
- Store powers per player with cooldown and fixed power key.
- Store active event chain as JSON in a table/global variable.
- Store current free-chain id and used power ids/keys for the chain.
- Store passed players for the current free-action window.
- Store monster slot number separately from monster material id.

## Room Slot Pattern

Rooms use seven numbered slots.

- Large monsters must be placed on even slots: 2, 4, and 6.
- Small monsters must be placed on odd slots: 1, 3, 5, and 7.
- Room events such as spikes and shield also use odd slots.
- A room can contain at most two event slots.
- Random room generation must preserve this pattern.
- Static room material must be validated against this pattern before it is used by setup logic.

## TDD Milestones

1. Event chain domain object: replacing chains, matching triggers, consuming events.
2. Free action eligibility: trigger match, cooldown check, once per chain.
3. Free action resolution: event consumed, power cooldown starts, new chain replaces old chain.
4. Main round flags: free move and main action are consumed independently.
5. Board rules: legal movement, terrain, distance, line/range as needed.
6. Power rules: Attack, Dash, Vortex.
7. Enemy rules: nearest priority, small then large activation.
8. Room progression and score budget.
9. BGA state transitions around the tested domain rules.

## PHP Best Practices

- Keep BGA entry files thin; put reusable logic in `modules/HNS_*.php` traits or small domain classes.
- Keep pure rule logic free from direct database calls when practical so it can be unit-tested.
- Use strict comparisons and explicit casts at BGA boundaries.
- Validate every action server-side with `checkAction` and rule-specific validation.
- Prefer small associative arrays with documented keys for BGA payloads; avoid hidden positional arrays.
- Never trust client-provided ids without checking ownership, location, phase, and current player eligibility.
- Keep material definitions under `modules/material/*.inc.php` and generated/static content separate from logic.
- Use constants for state labels, event names, power keys, and entity states.
- Add tests for every rule branch before wiring UI behavior.

## JavaScript Best Practices

- Treat the client as a renderer and action dispatcher; all rules remain server-authoritative.
- Keep DOM rendering functions idempotent and able to redraw from `gamedatas` or notifications.
- Use clear CSS class state names such as `hns-selectable`, `hns-cooldown`, and `hns-disabled`.
- Disable or hide buttons based on server state args, but still expect server-side rejection.
- Use BGA notifications to update only changed entities when possible, and full redraws only as a fallback.
- Keep action payloads minimal: ids, target tiles, and selected power key/id.
- Avoid duplicating pathfinding or combat validation in JS beyond preview hints.

## CSS Best Practices

- Keep selectors scoped with the `hns-` prefix to avoid collisions in BGA pages.
- Use layout CSS for structure and semantic classes for game state.
- Keep responsive behavior explicit for the board/sidebar split.
- Avoid styling based on translated text or generated inline content.
- Prefer reusable token classes for terrain, entities, selectable targets, cooldowns, and warnings.
- Keep image asset paths under `img/` for BGA deployment.
