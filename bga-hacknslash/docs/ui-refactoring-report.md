# UI Code Review & Refactoring Report

> **Project:** Hack N Slash — Board Game Arena  
> **Scope:** Frontend only (`hacknslash.js`, `hacknslash.css`)  
> **Methodology:** Tidy First — each change is an atomic, behaviour-preserving restructure  
> **Date:** May 2026

---

## 1. Initial State (Before Refactoring)

| Metric | JS | CSS |
|---|---|---|
| Lines | ~2 333 | ~781 |
| Structure | Single flat `declare()` class, all logic inlined | Flat list of rules, no variables |
| Duplication | 11+ entity-iteration loops, 6 render sequences, 2 identical handlers | 50+ hardcoded colours, near-duplicate values |
| Magic numbers | `70`, `36`, `520`, `0.42`, `3` scattered without names | Repeated `border-radius`, pixel values |
| Accessibility | Zero ARIA attributes, zero keyboard support, no `prefers-reduced-motion` | No `:focus-visible` styles |
| Performance | `renderAllPanels()` called synchronously on every notification | — |

### Key Findings

1. **Hardcoded colours** — 50+ raw hex/rgba values. Near-duplicates like `#171311` / `#181311` and `#5b4634` / `#574334` coexisted without design intent.
2. **Entity iteration** — 11+ `for (var id in entities)` loops with the same guard clauses (`state !== 'active'`, type filter) repeated verbatim across methods.
3. **Render sequences** — 6 places that called `renderHeroPanels()` + `renderPowerCards()` in identical pairs.
4. **Duplicate handlers** — `onRewardUpgradeClick` was a copy-paste of `onUpgradePowerClick`.
5. **No separation of concerns** — Pure game logic (LoS, distance, range, targeting) mixed with DOM manipulation and Dojo animation code in the same methods.
6. **Asset paths** — URL resolution (`g_gamethemeurl + 'img/...'`) duplicated in 20+ locations.
7. **Zero accessibility** — No `role`, `tabindex`, `aria-label`, `aria-live`, no keyboard activation, no reduced-motion media query.
8. **Dead code** — `gobelins` key duplicated in asset maps (unreachable: `getMonsterKey` always returns `goblin`).

---

## 2. Constraints

| Constraint | Impact |
|---|---|
| BGA enforces a **single JS file** and a **single CSS file** per game | No ES modules, no bundler, no code splitting |
| Dojo Toolkit AMD (`define()` / `declare()`) | Must use `var` declarations inside `define()` scope; no `import` / `export` |
| Deployment is raw SFTP mirror (`scripts/deploy-bga-sftp.sh`) | No build step available |
| `this` binding in `declare()` class methods | Factory-created handlers must be compatible (`createSkipHandler` pattern) |

**Decision:** All restructuring uses **intra-file objects** (`GameRules`, `AssetManager`) defined at the top of the `define()` scope. Zero tooling, fully BGA-compatible.

---

## 3. Refactoring Plan (5 Sprints)

### Sprint 1 — CSS Design Tokens & Shared Patterns

**Goal:** Eliminate all hardcoded colours, unify near-duplicates, extract reusable component patterns.

**Changes:**
- Defined ~60 CSS custom properties as design tokens inside `#hns_wrap` (backgrounds, gold/accent, text, borders, semantic colours, entity, badges, buttons, radii, transition).
- Unified near-duplicate colours:
  - `#171311` / `#181311` → `--hns-bg-surface`
  - `#5b4634` / `#574334` → `--hns-border`
  - `#fff1d8` / `#fff4e8` → `--hns-text-bright`
- Extracted `.hns_badge` shared base for all pill-shaped badges (`.hns_target_count`, `.hns_spawn_label`, `.hns_monster_card_losses`, `.hns_power_badge`, `.hns_effect_badge`).
- Extracted shared dead overlay base for `.hns_entity_dead` and `.hns_monster_card_dead` (common `filter: grayscale(1)`, `::after` strike-through line).
- Made shield/thorns composable via a single CSS variable `--hns-effect-filter`, allowing combined state `.hns_entity_shielded.hns_entity_thorns` without a third rule.
- Normalized all `border-radius` to tokens (`--hns-radius-card`, `--hns-radius-panel`, `--hns-radius-pill`).
- Added `@media (prefers-reduced-motion: reduce)` disabling all animations and transitions.

### Sprint 2 — Named Constants

**Goal:** Replace all magic numbers with named constants.

**Constants extracted:**
```
TILE_SIZE           = 70
BORDER_TILE_SIZE    = 36
ANIM_MOVE_MS        = 520
ANIM_SYNC_MS         = 620
ANIM_LUNGE_FORWARD_MS = 170
ANIM_LUNGE_RETURN_MS  = 230
LUNGE_DISTANCE_RATIO  = 0.42
MAX_POWER_CARDS       = 3
MAX_EVENTS            = 3
SOLO_ACTION_POINTS    = 2
MULTI_ACTION_POINTS   = 1
SLIME_TYPE_ARG        = 2
MONSTER_TYPES         = ['monster', 'boss']
```

`MONSTER_TYPES` replaced all inline `['monster', 'boss']` arrays and `entity.type !== 'monster' && entity.type !== 'boss'` guards throughout the file.

### Sprint 3 — GameRules Object (Pure Logic Extraction)

**Goal:** Extract all game logic that does not touch the DOM into a testable, pure-function object.

**`GameRules` methods (no `this`, no `gamedatas` dependency):**

| Method | Purpose |
|---|---|
| `powerDistance(from, to, metric)` | Manhattan or Chebyshev distance |
| `hasBlockingTileAt(x, y, tiles)` | Wall/pillar check at coordinate |
| `hasLineOfSight(from, to, tiles)` | Bresenham-like LoS trace |
| `isTileInMonsterFrontArc(from, tile)` | 8-neighbour arc check |
| `isTileInPowerRange(from, tile, power)` | Range + metric constraint |
| `isEntityInPowerRange(from, entity, power, tiles)` | Range + LoS |
| `isEntityInPowerRangeIgnoringLoS(from, entity, power, tiles)` | Range only (jump) |
| `isTileInMonsterAttackRange(from, tile, monster, tiles)` | Monster range + metric + LoS |
| `isWalkableTile(tile)` | Floor or spikes |
| `isTileOccupied(tileId, entities)` | Active entity on tile |
| `entityOnTile(tileId, entities, types)` | First matching entity ID or null |
| `entitiesAdjacentToTile(tileId, tiles, entities, types)` | Chebyshev-1 neighbours |
| `isFreeMoveTile(from, tile, entities)` | Orthogonal-1, walkable, unoccupied |
| `hasDashAttackDestination(from, target, power, tiles, entities)` | Valid dash-attack landing exists |
| `hasSlimeStatus(status)` | Regex check for `slimed` |
| `isMovementBlockedBySlime(power, hero)` | Slime blocks movement powers |
| `isHeroHeldBySlime(hero, tiles, entities)` | Adjacent active slime check |
| `isEntityTargetableByPower(from, entity, power, tiles, entities, selectedTileId)` | Full targeting logic per effect |
| `isTileValidPowerTarget(from, tile, power, tiles, entities)` | Tile-level targeting per effect |

All methods take tiles/entities as explicit parameters. This enables future unit testing without mocking BGA infrastructure.

### Sprint 4 — AssetManager & Utilities

**Goal:** Centralize URL resolution, eliminate string duplication, extract reusable helpers.

**`AssetManager` object:**
- `init(themeUrl)` — called once in `setup()` with `g_gamethemeurl`
- `getUrl(path)` — base URL + relative path
- `getMonsterKey(typeArg)` — numeric ID → string key mapping
- `getTileImage(tile, tileGrid)` — tile type → image URL (with floor variant hashing and wall adjacency logic)
- `pickWallVariant(tile, tileGrid)` — wall auto-tiling based on neighbour openness
- `getMonsterTileImage(monsterKey)` — board sprite
- `getBossTileImage(bossKey)` — board sprite
- `getMonsterCardImage(monsterKey)` — side panel card image (handles `slasher-N` boss phases)
- `getBossCardImage(bossKey, phase)` — boss card per phase
- `getPowerCardImage(powerKey)` — full power card map (~30 entries)

**Standalone utility functions:**
- `escapeHtml(value)` — XSS-safe HTML escaping
- `setNodePosition(node, left, top)` — shorthand for `dojo.style(node, {left, top})`
- `tileBounds(tiles)` — find maxX/maxY across all tiles
- `boardAxisOffset(index, maxIndex)` — pixel offset for a tile index (handles border tiles)
- `tileBox(tile, tiles)` — `{left, top, width, height}` for a tile
- `createSkipHandler(actionName)` — factory replacing 4 identical skip methods (`onSkipReward`, `onSkipFreeMove`, `onSkipMainAction`, `onEndTurn`)

**Other Sprint 4 changes:**
- Merged `onRewardUpgradeClick` into `onUpgradePowerClick` (one-line delegation)
- Removed dead `gobelins` key from asset maps
- Wrapped all user-visible strings in `_()` for i18n
- Reorganized class methods by section (lifecycle, rendering, handlers, state, data access, utility)

### Sprint 5 — Accessibility, Keyboard, Performance

#### Sprint 5a — Accessibility & Responsive

**ARIA attributes added to templates:**

| Element | Attributes |
|---|---|
| `.hns_tile` | `role="button" tabindex="-1" aria-label="${type}"` |
| `.hns_entity` | `role="button" tabindex="-1" aria-label="${label}"` |
| `.hns_monster_card` | `role="button" tabindex="0" aria-label="${name}"` |
| `.hns_power_card` | `role="button" tabindex="0" aria-label="${name}"` |

- Tiles and entities start at `tabindex="-1"` (not in tab order). When highlighted as valid targets (free move, power target), they are promoted to `tabindex="0"` and demoted back on clear.
- Panel cards (monster, power) are always tabbable.

**ARIA landmarks added to layout:**

| Element | Attribute | Purpose |
|---|---|---|
| `#hns_monster_panel` | `aria-label="Monsters"` | Landmark for screen readers |
| `#hns_side` | `aria-label="Hero and powers"` | Landmark for screen readers |
| `#hns_board_hint` | `role="status"` | Polite status updates |
| `#hns_power_confirm` | `role="alert"` | Assertive announce when action required |
| `#hns_event_list` | `aria-live="polite"` | Screen readers announce new game events |

**Keyboard navigation:**
- Single delegated `onWrapKeyDown` handler on `#hns_wrap`: Enter or Space on any `role="button"` with `tabindex="0"` triggers `.click()`.
- No per-element keyboard wiring needed; all existing click handlers work transparently.

**CSS `:focus-visible` styles:**
- Gold outline ring on power cards, monster cards, entities (consistent with game theme)
- Inner outline on tiles (to avoid overlap with board edge)
- Standard outline on buttons (upgrade, validate, cancel)

**Responsive:**
- Existing breakpoints: 1100px (tighter 3-column), 860px (single column)
- New 640px breakpoint: compact panel padding, smaller board min-width (280px), smaller monster/boss entity sprites, 2-column power card grid

#### Sprint 5b — Smart Dirty-Panel Rendering

Replaced synchronous `renderAllPanels()` in notification handlers with a `requestAnimationFrame`-debounced dirty-panel system.

**New methods:**
- `markPanelDirty(name)` — flags a panel (`monsters`, `heroes`, `powers`, `rewards`) and schedules a single rAF callback
- `flushDirtyPanels()` — renders only flagged panels, resets flags

**Impact:**
- Multiple rapid notifications (e.g. monster attack → damage → kill → free action) batch into a single render pass instead of 4 separate `renderAllPanels()` calls.
- `renderAllPanels()` kept synchronous for `setup()` (initial render must be immediate).
- All 5 notification handlers (`notif_heroMoved`, `notif_rewardChosen`, `notif_playerActionState`, `notif_powerCooldownsUpdated`, `notif_roundStarted`) and `refreshFromEvent` now use `markPanelDirty`.

---

## 4. Final State

| Metric | JS | CSS |
|---|---|---|
| Lines | 2 306 | 971 |
| Architecture | Constants → GameRules → AssetManager → Utilities → Templates → Class (sectioned) | Tokens → Layout → Components → Focus → Responsive → Reduced Motion |
| Pure logic | `GameRules` object (19 methods, zero DOM) | — |
| Asset resolution | `AssetManager` object (10 methods, initialized once) | — |
| Shared patterns | `createSkipHandler` factory, `MONSTER_TYPES` constant | Shared badge base, dead overlay base, composable effect filter |
| ARIA | `role`, `tabindex`, `aria-label`, `aria-live`, `role="alert"`, `role="status"` | `:focus-visible` styles on all interactive elements |
| Keyboard | Delegated Enter/Space handler; dynamic tabindex promotion | — |
| Performance | rAF-debounced dirty-panel rendering | — |
| Reduced motion | — | `@media (prefers-reduced-motion: reduce)` |
| Responsive | — | 3 breakpoints (1100px, 860px, 640px) |

---

## 5. Leftovers & Future Work

### High Priority

| Item | Description | Effort |
|---|---|---|
| **Unit tests for GameRules** | All 19 methods are pure functions with no BGA dependency. A lightweight test harness (even a plain `<script>` runner) could validate LoS, range, targeting, slime logic. This is the highest-value testing investment. | Medium | **Done** — `tests/gameRules.html` with 109 assertions, all passing |
| ~~**`reenforce` → `reinforce` rename**~~ | ~~The spelling `reenforce` was used in 3 JS asset keys and 6 image files on disk.~~ | ~~Low~~ | **Done** — 6 `.webp` files renamed in both `src/` and `bga-hacknslash/img/`, 3 JS asset-map keys updated. Server PHP has no `reenforce` key; power not yet in `bonus_cards.inc.php` (will use `reinforce_*` when added). |
| **Board grid keyboard navigation** | Current keyboard support is tab-based (panel cards + highlighted tiles). A proper board grid would use arrow keys to move a cursor between tiles, reducing tab stops. Requires a `currentBoardCursor` state and arrow-key handler scoped to `#hns_board`. | Medium-High |

### Medium Priority

| Item | Description | Effort |
|---|---|---|
| **`aria-disabled` on dead entities and cooldown cards** | Dead entities have `pointer-events: none` but no ARIA disabled state. Cooldown cards are visually grayed but still clickable. Add `aria-disabled="true"` dynamically. | Low |
| **`aria-selected` / `aria-pressed` on selected cards** | Power card selection (`hns_selected` class) should be reflected via `aria-pressed="true"` for screen readers. Same for selected entity and monster card (`aria-selected`). | Low |
| **Granular dirty-panel tracking** | Current system marks all 4 panels dirty on engine events. Could map each event type to its affected panels (e.g. `afterKill` → monsters + heroes only, `entityHealed` → heroes only) for fewer renders. | Low |
| **`onEnteringState` / `onLeavingState` use `markPanelDirty`** | These BGA state callbacks still call `renderHeroPanels()` / `renderPowerCards()` synchronously. Could be migrated to dirty-panel system for consistency. | Low |
| **CSS container queries** | Replace `@media` width breakpoints with `@container` queries scoped to `#hns_wrap`, so the layout adapts to the game area width (not the viewport). BGA embeds games in variable-width panels. | Medium |

### Low Priority

| Item | Description | Effort |
|---|---|---|
| **Theming support** | The 60 CSS custom properties are ready for alternative themes (e.g. light mode, high contrast). Would require a `.hns_theme_light` class that overrides the token values. | Medium |
| **Animation choreography** | Lunge and slide animations use fixed durations. Could add easing curves configurable via CSS custom properties (`--hns-anim-move`, `--hns-anim-lunge`). | Low |
| **Tooltip system** | Monster cards and power cards show basic `title` attributes. A custom tooltip component with richer content (stats, effect description) would improve UX. | Medium |
| **`onPowerCardClick` simplification** | The method handles 4 distinct flows (reward replace, slime block, zero-distance auto-play, normal select/deselect). Could be split into smaller methods for readability. | Low |
| **Server action name audit** | Verify that all `bgaPerformAction` action names (`actMove`, `actPlayCard`, `actChooseReward`, `actSkipReward`, `actSkipFreeMove`, `actSkipMainAction`, `actEndTurn`) match the server's `action.php` and state machine exactly. | Low |

---

## 6. File Reference

| File | Role |
|---|---|
| `hacknslash.js` (2 306 lines) | Constants, GameRules, AssetManager, utilities, templates, main game class |
| `hacknslash.css` (971 lines) | Design tokens, layout, components, focus styles, responsive, reduced motion |
| `AGENTS.md` | Project conventions |
| `scripts/deploy-bga-sftp.sh` | Deployment (raw SFTP mirror, no build step) |
| `docs/implementation-plan.md` | Server-side game logic and state machine design |
| `docs/ui-refactoring-report.md` | This document |
