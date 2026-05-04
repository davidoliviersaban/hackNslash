# Rapport de migration PHP — HackNSlash BGA

Date : 2026-05-04
Tests : **294 tests, 1360 assertions — tout vert**
Fichiers modifies : 34 (dont 2 nouveaux)

---

## 1. Feature : plays_remaining (multi-play powers)

Objectif : rendre generique le mecanisme de pouvoirs jouables plusieurs fois par tour (ex. dash_attack avec `plays: 2`), au lieu d'un cas special code en dur.

### Changements

| Fichier | Detail |
|---------|--------|
| `dbmodel.sql` | Ajout colonne `power_plays_remaining TINYINT UNSIGNED NOT NULL DEFAULT 0` dans `player_power` |
| `modules/HNS_Board.php` | Migration runtime `ensurePowerPlaysRemainingColumn()` (ALTER TABLE si colonne absente) |
| `modules/HNS_Player.php` | Helpers : `getPowerPlaysRemaining`, `setPowerPlaysRemaining`, `clearAllPowerPlaysRemaining` ; `getPlayerPowers` enrichi ; `resetRoundFlags` reset les plays_remaining |
| `modules/HNS_FreeActionEngine.php` | `canUseFreeAction` / `useFreeAction` acceptent `$playsRemaining` ; bypass cooldown si `playsRemaining > 0` |
| `hacknslash.game.php` | `actPlayCard` lit `power_plays_remaining`, autorise le jeu malgre cooldown si plays restants ; `playsRemainingAfterUse()` ; `cooldownAfterPowerUse()` simplifie (plus de cas special dash_attack) |
| `tests/FreeActionEnginePlaysRemainingTest.php` | **Nouveau** — 5 tests unitaires couvrant le bypass cooldown, la consommation, et le blocage par `usedActionKeys` |
| `tests/SetupTest.php` | Assertion mise a jour pour le nouveau INSERT |

---

## 2. Normalisation du nommage

### A. Status tokens
- **Avant** : `stuck`, `stick`, `slimed` reconnus comme synonymes
- **Apres** : seul `slimed` est valide
- Fichiers : `HNS_PowerResolver.php`, `HNS_RoundEngine.php`, `hacknslash.js`

### B. Effets monstre
- `stick` -> `slime`
- `front_arc_damage` -> `front_arc`
- Fichiers : `monsters.inc.php`, `HNS_MonsterAi.php`

### C. Evenements
- `monsterStick` -> `monsterSlime`
- `monsterFrontArcAttack` -> `monsterFrontArc`
- Suppression du doublon `cardPlayed` (seul `afterCardPlayed` reste)
- `HNS_EventDispatcher` reecrit avec registre complet (22 types d'evenements)
- Fichiers : `HNS_MonsterAi.php`, `HNS_GameEngine.php`, `HNS_EventDispatcher.php`, `hacknslash.js`

### D. Taille des monstres
- `large` -> `big` partout (le material utilisait deja `big`)
- Fichiers : `HNS_RoomSlotPattern.php` (validate + firstAvailableMonsterSlot), `HNS_GameEngine.php`

### E. Metriques de distance
- Valeur par defaut `manhattan` -> `orthogonal` (meme comportement, nommage coherent)
- `resolveDash` refactorise avec expression `match` (suppression du double-assign)
- Fichiers : `HNS_PowerResolver.php` (4 methodes : resolveAttack, resolveAreaAttack, resolveHeal, resolvePull)

### F. Cles de pouvoir
Convention : `{nom}_{rang}` avec `_` comme seul separateur.

| Avant | Apres |
|-------|-------|
| `dash-attack_N` | `dash_attack_N` |
| `power-strike_N` | `power_strike_N` |
| `quick-strike_N` | `quick_strike_N` |
| `quick-shot_N` | `quick_shot_N` |
| `point-blank_N` | `point_blank_N` |
| `vortex` (rang 1 sans suffixe) | `vortex_1` |

Fichiers : `bonus_cards.inc.php`, `hacknslash.js` (2 asset maps), `hacknslash.game.php`, + 7 fichiers de test

> **Note** : les noms de fichiers images sur disque restent avec des tirets (`dash-attack-1.webp`) — ce sont les cles JS qui sont normalisees, pas les fichiers physiques.

---

## 3. Refactoring architecture

### G. Boss material DRY
- **Avant** : `HNS_GameEngine::slasherBossMaterial()` dupliquait la phase 1 en dur
- **Apres** : `createLevel()` accepte `$bossMaterial` en parametre optionnel ; le vrai `bosses.inc.php` est passe
- Methode privee supprimee
- Fichiers : `HNS_GameEngine.php`, `HNS_RoundEngine.php`, `HNS_Setup.php`, 3 tests

### H. Suppression dead code events
- Constantes `EVENT_AFTER_SHIELD_BREAK` et `EVENT_AFTER_TRAP` supprimees (jamais emises, jamais utilisees en `free_triggers`)
- Entrees retirees du registre `HNS_EventDispatcher`
- `entityDamaged` re-ajoute dans le registre (avait ete supprime par erreur)
- Fichiers : `HNS_FreeActionEngine.php`, `HNS_EventDispatcher.php`

### I. Deduplication nextEntityId
- **Avant** : methode `nextEntityId()` identique dans `HNS_MonsterAi` et `HNS_BossEngine`
- **Apres** : `HNS_GameEngine::nextEntityId()` public static, avec guard array vide
- Fichiers : `HNS_GameEngine.php`, `HNS_MonsterAi.php`, `HNS_BossEngine.php`

### J. Extraction HNS_SeededRandom
- **Avant** : classe definie en bas de `HNS_LevelGenerator.php`
- **Apres** : fichier propre `modules/HNS_SeededRandom.php`
- `require_once` ajoute dans : `hacknslash.game.php`, `GameEngineTest`, `LevelGeneratorTest`, `RoundEngineTest`, `BossEngineTest`, `PowerResolverTest`

### K. Securisation SQL escape
- **Avant** : fallback `addslashes()` (non multibyte-safe)
- **Apres** : `str_replace` cible sur les 6 caracteres SQL critiques (`\`, `'`, `\0`, `\n`, `\r`, `\x1a`)
- Fichier : `HNS_DbHelpers.php`

---

## 4. Corrections de tests

| Fichier | Correction |
|---------|------------|
| `StructureTest.php` | 8 assertions alignees sur le refactoring GameRules (fonctions extraites du prototype vers objet pur), constantes TILE_SIZE/BORDER_TILE_SIZE, createSkipHandler, SLIME_TYPE_ARG |
| `AssetExistenceTest.php` | Separation assets litteraux (walls, floors) vs dynamiques (entrance, exit, pillar, hole, spikes — construits via mapping simpleTypes) |
| `EventDispatcherTest.php` | Assertion alignee sur le passage de in_array vers comparaison directe `=== 'afterCardPlayed'` |
| `RoomSlotPatternTest.php` | Assertions alignees sur `big` au lieu de `large` |

---

## 5. Fichiers crees

| Fichier | Raison |
|---------|--------|
| `modules/HNS_SeededRandom.php` | Extraction depuis HNS_LevelGenerator |
| `tests/FreeActionEnginePlaysRemainingTest.php` | Tests unitaires plays_remaining |

---

## 6. Migrations runtime actives

Deux migrations sont executees au chargement du board (idempotentes) :
1. `ensureTileSpawnLabelColumn()` — ajoute `tile_spawn_label` si absent
2. `ensurePowerPlaysRemainingColumn()` — ajoute `power_plays_remaining` si absent

---

## 7. Leftovers — resolus

### L1. Deduplication des fonctions partagees (resolu)

3 fonctions identiques entre modules ont ete extraites dans `HNS_BoardRules` :

| Fonction | Avant | Apres |
|----------|-------|-------|
| `assertEntityExists` | `HNS_MonsterAi` + `HNS_PowerResolver` | `HNS_BoardRules::assertEntityExists()` |
| `assertTileExists` | `HNS_PowerResolver` uniquement | `HNS_BoardRules::assertTileExists()` |
| `entityTile` | `HNS_MonsterAi` (signature `$entityId, $state`) + `HNS_PowerResolver` (`$state, $entityId`) | `HNS_BoardRules::entityTile($state, $entityId)` — signature unifiee |
| `hasSlimeStatus` | `HNS_RoundEngine` + `HNS_PowerResolver` | `HNS_BoardRules::hasSlimeStatus()` |

`bestAdjacentTileToward` reste en double : les semantiques divergent trop (MonsterAi a un param `$moveMetric`, un check `canEnterTile`, et un tie-breaking par id ; PowerResolver hardcode `diagonalDistance` et autorise la destination non-walkable).

### L2. SQL escape (resolu precedemment)

Fallback `addslashes()` remplace par `str_replace` cible — voir section 3.K.

### L3. `require_once` manquant (resolu)

`tests/PowerResolverTest.php` n'importait pas `HNS_SeededRandom.php` — ajoute.

### L4. Commentaire dbmodel (resolu)

`dash-attack` -> `dash_attack` dans le commentaire SQL de `power_plays_remaining`.

---

## 8. Items non traites (choix delibere)

### Fichiers images en kebab-case

Les fichiers physiques sur disque restent en kebab-case (`dash-attack-1.webp`, `quick-shot-2.webp`). C'est un choix delibere : le kebab-case est la convention de nommage des assets. Le mapping JS `power_key` -> `filename` fait la traduction.

### `bestAdjacentTileToward` duplique

Les deux implementations ont des semantiques differentes (voir L1). Une unification necessiterait un refactoring du pathfinding monstre vs pouvoir, hors scope.

### Fragilite StructureTest

Les tests `StructureTest` et `AssetExistenceTest` lisent le source PHP/JS en string pour verifier la presence de patterns. C'est fragile : chaque refactoring JS casse des assertions. Mais c'est fonctionnel et sert de filet de securite pour les contrats JS/PHP. A terme, remplacer par des tests d'integration.

---

## Resume des conventions etablies

| Domaine | Convention |
|---------|-----------|
| Cles de pouvoir | `{nom}_{rang}` — underscore uniquement, rang toujours present |
| Status tokens | `slimed` uniquement (pas de synonymes) |
| Effets monstre | `slime`, `front_arc`, `charge`, `summon`, `explode` |
| Evenements | camelCase, prefixe par categorie (`monster*`, `boss*`, `after*`) |
| Taille monstre | `small`, `big`, `boss` |
| Metrique distance | `orthogonal`, `chebyshev` |
| Escape SQL | `escapeStringForDB` en prod, `str_replace` en test |
