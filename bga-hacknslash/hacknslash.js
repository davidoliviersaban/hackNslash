define([
  'dojo',
  'dojo/_base/declare',
  'ebg/core/gamegui',
  'ebg/counter'
], function (dojo, declare) {

  // =========================================================================
  //  SECTION 1 — Constants
  // =========================================================================

  var TILE_SIZE = 70;
  var BORDER_TILE_SIZE = 36;
  var ANIM_MOVE_MS = 520;
  var ANIM_SYNC_MS = 620;
  var ANIM_LUNGE_FORWARD_MS = 170;
  var ANIM_LUNGE_RETURN_MS = 230;
  var LUNGE_DISTANCE_RATIO = 0.42;
  var MAX_POWER_CARDS = 3;
  var MAX_EVENTS = 3;
  var SOLO_ACTION_POINTS = 2;
  var MULTI_ACTION_POINTS = 1;
  var SLIME_TYPE_ARG = 2;
  var MONSTER_TYPES = ['monster', 'boss'];

  // =========================================================================
  //  SECTION 2 — GameRules (pure logic, no DOM, no gamedatas dependency)
  // =========================================================================

  var GameRules = {

    // -- Geometry --

    powerDistance: function (from, to, metric) {
      var dx = Math.abs(parseInt(from.x, 10) - parseInt(to.x, 10));
      var dy = Math.abs(parseInt(from.y, 10) - parseInt(to.y, 10));
      return metric === 'chebyshev' ? Math.max(dx, dy) : dx + dy;
    },

    hasBlockingTileAt: function (x, y, tiles) {
      for (var tileId in tiles) {
        var tile = tiles[tileId];
        if (parseInt(tile.x || 0, 10) === x && parseInt(tile.y || 0, 10) === y && ['wall', 'pillar'].indexOf(tile.type) !== -1) {
          return true;
        }
      }
      return false;
    },

    hasLineOfSight: function (from, to, tiles) {
      if (!from || !to) {
        return false;
      }

      var x = parseInt(from.x, 10);
      var y = parseInt(from.y, 10);
      var targetX = parseInt(to.x, 10);
      var targetY = parseInt(to.y, 10);
      var dx = Math.abs(targetX - x);
      var dy = Math.abs(targetY - y);
      var stepX = targetX === x ? 0 : (targetX > x ? 1 : -1);
      var stepY = targetY === y ? 0 : (targetY > y ? 1 : -1);
      var walkedX = 0;
      var walkedY = 0;

      while (walkedX < dx || walkedY < dy) {
        var decision = (1 + (2 * walkedX)) * dy - (1 + (2 * walkedY)) * dx;
        if (decision === 0) {
          x += stepX;
          y += stepY;
          walkedX++;
          walkedY++;
        } else if (decision < 0) {
          x += stepX;
          walkedX++;
        } else {
          y += stepY;
          walkedY++;
        }

        if (x === targetX && y === targetY) {
          return true;
        }

        if (GameRules.hasBlockingTileAt(x, y, tiles)) {
          return false;
        }
      }

      return true;
    },

    isTileInMonsterFrontArc: function (from, tile) {
      var dx = parseInt(tile.x, 10) - parseInt(from.x, 10);
      var dy = parseInt(tile.y, 10) - parseInt(from.y, 10);
      return (dx === 1 && Math.abs(dy) <= 1) || (dx === -1 && Math.abs(dy) <= 1) || (dy === 1 && Math.abs(dx) <= 1) || (dy === -1 && Math.abs(dx) <= 1);
    },

    // -- Range checks --

    isTileInPowerRange: function (from, tile, power) {
      var range = power.range || power.distance || [0, 0];
      if ((power.range_metric || 'orthogonal') === 'orthogonal' && String(from.x) !== String(tile.x) && String(from.y) !== String(tile.y)) {
        return false;
      }
      var distance = GameRules.powerDistance(from, tile, power.range_metric || 'orthogonal');
      return distance >= parseInt(range[0] || 0, 10) && distance <= parseInt(range[1] || range[0] || 0, 10);
    },

    isEntityInPowerRange: function (from, entity, power, tiles) {
      var tile = tiles[entity.tile_id];
      return !!tile && GameRules.isTileInPowerRange(from, tile, power) && GameRules.hasLineOfSight(from, tile, tiles);
    },

    isEntityInPowerRangeIgnoringLoS: function (from, entity, power, tiles) {
      var tile = tiles[entity.tile_id];
      return !!tile && GameRules.isTileInPowerRange(from, tile, power);
    },

    isTileInMonsterAttackRange: function (from, tile, monster, tiles) {
      var maxRange = parseInt(monster.range || 0, 10);
      var minRange = typeof monster.min_range !== 'undefined' ? parseInt(monster.min_range || 0, 10) : (maxRange > 0 ? 1 : 0);
      var metric = monster.range_metric || 'orthogonal';

      if (metric === 'front_arc') {
        return GameRules.isTileInMonsterFrontArc(from, tile) && GameRules.hasLineOfSight(from, tile, tiles);
      }

      if (metric === 'orthogonal' && String(from.x) !== String(tile.x) && String(from.y) !== String(tile.y)) {
        return false;
      }

      var distance = GameRules.powerDistance(from, tile, metric);
      return distance >= minRange && distance <= maxRange && GameRules.hasLineOfSight(from, tile, tiles);
    },

    // -- Tile / entity queries --

    isWalkableTile: function (tile) {
      return ['floor', 'spikes'].indexOf(tile.type) !== -1;
    },

    isTileOccupied: function (tileId, entities) {
      for (var entityId in entities) {
        var entity = entities[entityId];
        if ((entity.state || 'active') !== 'active') { continue; }
        if (String(entity.tile_id) === String(tileId)) { return true; }
      }
      return false;
    },

    /**
     * Find the first active entity on a tile, optionally filtered by type(s).
     * Returns entity ID string or null.
     */
    entityOnTile: function (tileId, entities, types) {
      for (var entityId in entities) {
        var entity = entities[entityId];
        if ((entity.state || 'active') !== 'active') { continue; }
        if (types && types.indexOf(entity.type) === -1) { continue; }
        if (String(entity.tile_id) === String(tileId)) { return entityId; }
      }
      return null;
    },

    /**
     * Find active entity IDs (of given types) adjacent (Chebyshev 1) to a tile.
     */
    entitiesAdjacentToTile: function (tileId, tiles, entities, types) {
      var selectedTile = tiles[tileId];
      var ids = [];
      if (!selectedTile) { return ids; }

      for (var entityId in entities) {
        var entity = entities[entityId];
        if ((entity.state || 'active') !== 'active') { continue; }
        if (types && types.indexOf(entity.type) === -1) { continue; }
        var entityTile = tiles[entity.tile_id];
        if (entityTile && GameRules.powerDistance(selectedTile, entityTile, 'chebyshev') === 1) {
          ids.push(entityId);
        }
      }
      return ids;
    },

    entitiesInPowerArea: function (tileId, tiles, entities, types, area, metric) {
      var selectedTile = tiles[tileId];
      var ids = [];
      var range = area || [0, 0];
      var areaMetric = metric || 'orthogonal';
      if (!selectedTile) { return ids; }

      for (var entityId in entities) {
        var entity = entities[entityId];
        if ((entity.state || 'active') !== 'active') { continue; }
        if (types && types.indexOf(entity.type) === -1) { continue; }
        var entityTile = tiles[entity.tile_id];
        if (!entityTile) { continue; }
        if (areaMetric === 'orthogonal' && String(selectedTile.x) !== String(entityTile.x) && String(selectedTile.y) !== String(entityTile.y)) { continue; }
        var distance = GameRules.powerDistance(selectedTile, entityTile, areaMetric);
        if (distance >= parseInt(range[0] || 0, 10) && distance <= parseInt(range[1] || range[0] || 0, 10)) {
          ids.push(entityId);
        }
      }
      return ids;
    },

    tilesInPowerArea: function (tileId, tiles, area, metric) {
      var selectedTile = tiles[tileId];
      var ids = [];
      var range = area || [0, 0];
      var areaMetric = metric || 'orthogonal';
      if (!selectedTile) { return ids; }

      for (var candidateTileId in tiles) {
        var tile = tiles[candidateTileId];
        if (areaMetric === 'orthogonal' && String(selectedTile.x) !== String(tile.x) && String(selectedTile.y) !== String(tile.y)) { continue; }
        var distance = GameRules.powerDistance(selectedTile, tile, areaMetric);
        if (distance >= parseInt(range[0] || 0, 10) && distance <= parseInt(range[1] || range[0] || 0, 10)) {
          ids.push(candidateTileId);
        }
      }
      return ids;
    },

    // -- Movement --

    isFreeMoveTile: function (from, tile, entities) {
      var distance = GameRules.powerDistance(from, tile, 'orthogonal');
      if (distance !== 1 || !GameRules.isWalkableTile(tile)) {
        return false;
      }
      return !GameRules.isTileOccupied(tile.id, entities);
    },

    hasDashAttackDestination: function (from, targetEntity, power, tiles, entities) {
      var targetTile = targetEntity && tiles[targetEntity.tile_id];
      if (!targetTile) {
        return false;
      }

      for (var tileId in tiles) {
        var tile = tiles[tileId];
        if (!GameRules.isTileInPowerRange(from, tile, power) || !GameRules.isWalkableTile(tile) || GameRules.isTileOccupied(tile.id, entities)) {
          continue;
        }
        if (GameRules.powerDistance(tile, targetTile, 'orthogonal') === 1 && (String(tile.x) === String(targetTile.x) || String(tile.y) === String(targetTile.y)) && GameRules.hasLineOfSight(tile, targetTile, tiles)) {
          return true;
        }
      }

      return false;
    },

    // -- Status effects --

    hasSlimeStatus: function (status) {
      return /(^|\s)slimed(\s|$)/.test(String(status || ''));
    },

    isMovementBlockedBySlime: function (power, hero, tiles, entities) {
      if (!power || !hero || !GameRules.isHeroHeldBySlime(hero, tiles, entities)) {
        return false;
      }

      if (power.effect === 'dash') {
        return false;
      }

      if (power.effect === 'dash_attack' || power.effect === 'jump') {
        return true;
      }

      return power.effect === 'move_area_attack' && power.distance && parseInt(power.distance[1] || 0, 10) > 0;
    },

    isHeroHeldBySlime: function (hero, tiles, entities) {
      if (!hero) {
        return false;
      }

      var heroTile = tiles[hero.tile_id];
      if (!heroTile) {
        return false;
      }

      for (var entityId in entities) {
        var entity = entities[entityId];
        if (entity.type !== 'monster' || parseInt(entity.type_arg || 0, 10) !== SLIME_TYPE_ARG || (entity.state || 'active') !== 'active') {
          continue;
        }

        var slimeTile = tiles[entity.tile_id];
        if (slimeTile && GameRules.powerDistance(heroTile, slimeTile, 'orthogonal') === 1) {
          return true;
        }
      }

      return false;
    },

    // -- Power targeting (pure logic, no DOM) --

    isEntityTargetableByPower: function (from, entity, power, tiles, entities, selectedPowerTileId) {
      if (power.effect === 'heal') {
        return entity.type === 'hero' && GameRules.isEntityInPowerRange(from, entity, power, tiles);
      }
      if (power.effect === 'dash_attack') {
        if (!selectedPowerTileId) {
          return false;
        }
        var selectedTile = tiles[selectedPowerTileId];
        var entityTile = tiles[entity.tile_id];
        return MONSTER_TYPES.indexOf(entity.type) !== -1 && selectedTile && entityTile && GameRules.powerDistance(selectedTile, entityTile, 'orthogonal') === 1 && GameRules.hasLineOfSight(selectedTile, entityTile, tiles);
      }
      if (power.effect === 'jump') {
        return MONSTER_TYPES.indexOf(entity.type) !== -1 && (parseInt(power.damage || 0, 10) > 0 || parseInt(power.push_distance || 0, 10) > 0) && GameRules.isEntityInPowerRangeIgnoringLoS(from, entity, power, tiles);
      }
      if (MONSTER_TYPES.indexOf(entity.type) === -1) {
        return false;
      }
      if (power.effect !== 'pull') {
        return GameRules.isEntityInPowerRange(from, entity, power, tiles);
      }
      if (!selectedPowerTileId) {
        return false;
      }
      var selectedTile = tiles[selectedPowerTileId];
      var entityTile = tiles[entity.tile_id];
      if (!selectedTile || !entityTile) {
        return false;
      }
      if ((power.area_metric || 'orthogonal') === 'orthogonal' && String(selectedTile.x) !== String(entityTile.x) && String(selectedTile.y) !== String(entityTile.y)) {
        return false;
      }
      var area = power.area || [1, 1];
      var distance = GameRules.powerDistance(selectedTile, entityTile, power.area_metric || 'orthogonal');
      return distance >= parseInt(area[0] || 0, 10) && distance <= parseInt(area[1] || area[0] || 0, 10);
    },

    isTileValidPowerTarget: function (from, tile, power, tiles, entities) {
      if (!GameRules.isTileInPowerRange(from, tile, power)) {
        return false;
      }

      if (['attack', 'area_attack', 'pull', 'move_area_attack'].indexOf(power.effect) !== -1 && !GameRules.hasLineOfSight(from, tile, tiles)) {
        return false;
      }

      if (power.effect === 'dash') {
        return GameRules.isWalkableTile(tile) && !GameRules.isTileOccupied(tile.id, entities);
      }

      if (power.effect === 'jump') {
        var monsterId = GameRules.entityOnTile(tile.id, entities, MONSTER_TYPES);
        if (monsterId !== null) {
          return parseInt(power.damage || 0, 10) > 0 || parseInt(power.push_distance || 0, 10) > 0;
        }
        return GameRules.isWalkableTile(tile) && !GameRules.isTileOccupied(tile.id, entities);
      }

      if (power.effect === 'move_area_attack') {
        return GameRules.isWalkableTile(tile) && !GameRules.isTileOccupied(tile.id, entities);
      }

      if (power.effect === 'attack') {
        return GameRules.entityOnTile(tile.id, entities, MONSTER_TYPES) !== null;
      }

      if (power.effect === 'pull') {
        return GameRules.entitiesInPowerArea(tile.id, tiles, entities, MONSTER_TYPES, power.area || [1, 1], power.area_metric || 'orthogonal').length > 0;
      }

      if (power.effect === 'area_attack') {
        return true;
      }

      if (power.effect === 'heal') {
        return GameRules.entityOnTile(tile.id, entities, ['hero']) !== null;
      }

      if (power.effect === 'dash_attack') {
        var range = power.range || [0, 0];
        var distance = GameRules.powerDistance(from, tile, 'orthogonal');
        return GameRules.isWalkableTile(tile) && !GameRules.isTileOccupied(tile.id, entities) && distance >= parseInt(range[0] || 0, 10) && distance <= parseInt(range[1] || range[0] || 0, 10);
      }

      return true;
    }
  };

  // =========================================================================
  //  SECTION 3 — AssetManager (URL resolution & asset maps)
  // =========================================================================

  var AssetManager = {
    baseUrl: '',

    init: function (themeUrl) {
      this.baseUrl = themeUrl + 'img/';
    },

    getUrl: function (path) {
      return this.baseUrl + path;
    },

    getMonsterKey: function (typeArg) {
      var keys = {
        1: 'goblin', 2: 'slimes', 3: 'evil-eye', 4: 'kamikaze',
        5: 'wizard', 6: 'bomber', 7: 'orc', 8: 'pig-rider', 9: 'wolf-rider'
      };
      return keys[typeArg] || 'goblin';
    },

    getTileImage: function (tile, tileGrid) {
      var type = tile.type;

      var simpleTypes = {
        entry: 'entrance', exit: 'exit', pillar: 'pillar', hole: 'hole', spikes: 'spikes'
      };
      if (simpleTypes[type]) {
        return AssetManager.getUrl('tiles/levels/' + simpleTypes[type] + '.webp');
      }
      if (type === 'wall') {
        return AssetManager.getUrl(AssetManager.pickWallVariant(tile, tileGrid || {}));
      }

      // Default = floor: pick a stable variant per coordinate.
      var variants = [
        'tiles/levels/floor.webp',
        'tiles/levels/floor-1.webp',
        'tiles/levels/floor-2.webp',
        'tiles/levels/floor-3.webp'
      ];
      var idx = Math.abs(((tile.x | 0) * 31 + (tile.y | 0) * 17)) % variants.length;
      return AssetManager.getUrl(variants[idx]);
    },

    pickWallVariant: function (tile, tileGrid) {
      var bounds = tileBounds(tileGrid || {});
      var x = parseInt(tile.x || 0, 10);
      var y = parseInt(tile.y || 0, 10);
      if (x === 0 && y === 0) { return 'tiles/levels/wall-top-left.webp'; }
      if (x === bounds.maxX && y === 0) { return 'tiles/levels/wall-top-right.webp'; }
      if (x === 0 && y === bounds.maxY) { return 'tiles/levels/wall-bottom-left.webp'; }
      if (x === bounds.maxX && y === bounds.maxY) { return 'tiles/levels/wall-bottom-right.webp'; }
      if (y === 0) { return 'tiles/levels/wall-top.webp'; }
      if (y === bounds.maxY) { return 'tiles/levels/wall-bottom.webp'; }
      if (x === 0) { return 'tiles/levels/wall-left.webp'; }
      if (x === bounds.maxX) { return 'tiles/levels/wall-right.webp'; }

      function isOpen(x, y) {
        var n = tileGrid[x + ',' + y];
        return !!n && n.type !== 'wall';
      }

      var open = {
        top: isOpen(tile.x, tile.y - 1),
        bottom: isOpen(tile.x, tile.y + 1),
        left: isOpen(tile.x - 1, tile.y),
        right: isOpen(tile.x + 1, tile.y)
      };

      if (open.top && open.left)    { return 'tiles/levels/wall-top-left.webp'; }
      if (open.top && open.right)   { return 'tiles/levels/wall-top-right.webp'; }
      if (open.bottom && open.left) { return 'tiles/levels/wall-bottom-left.webp'; }
      if (open.bottom && open.right){ return 'tiles/levels/wall-bottom-right.webp'; }
      if (open.left && open.right)  { return 'tiles/levels/wall--left-right.webp'; }
      if (open.top)    { return 'tiles/levels/wall-top.webp'; }
      if (open.bottom) { return 'tiles/levels/wall-bottom.webp'; }
      if (open.left)   { return 'tiles/levels/wall-left.webp'; }
      if (open.right)  { return 'tiles/levels/wall-right.webp'; }
      return 'tiles/levels/wall-top.webp';
    },

    getMonsterTileImage: function (monsterKey) {
      var files = {
        'goblin': 'tiles/monsters/goblin-pixel.webp',
        'slimes': 'tiles/monsters/slime-pixel.webp',
        'evil-eye': 'tiles/monsters/evil-eye-pixel.webp',
        'kamikaze': 'tiles/monsters/goblin-kamikaze-pixel.webp',
        'wizard': 'tiles/monsters/goblin-wizard-pixel.webp',
        'bomber': 'tiles/monsters/goblin-bomber-pixel.webp',
        'orc': 'tiles/monsters/orc-pixel.webp',
        'pig-rider': 'tiles/monsters/pig-rider-pixel.webp',
        'wolf-rider': 'tiles/monsters/wolf-rider-pixel.webp'
      };
      return AssetManager.getUrl(files[monsterKey] || 'tiles/monsters/goblin-pixel.webp');
    },

    getBossTileImage: function (bossKey) {
      var files = { 'slasher': 'tiles/monsters/slasher-pixel.webp', 'striker': 'tiles/monsters/striker-pixel.webp' };
      return AssetManager.getUrl(files[bossKey] || files.slasher);
    },

    getMonsterCardImage: function (monsterKey) {
      if (monsterKey.indexOf('slasher-') === 0) {
        return AssetManager.getBossCardImage('slasher', parseInt(monsterKey.replace('slasher-', ''), 10));
      }
      if (monsterKey.indexOf('striker-') === 0) {
        return AssetManager.getBossCardImage('striker', parseInt(monsterKey.replace('striker-', ''), 10));
      }

      var files = {
        'goblin': 'cards/monsters/gobelins.webp',
        'slimes': 'cards/monsters/slimes.webp',
        'evil-eye': 'cards/monsters/evil-eye.webp',
        'kamikaze': 'cards/monsters/goblin-kamikaze.webp',
        'wizard': 'cards/monsters/goblin-wizard.webp',
        'bomber': 'cards/monsters/goblin-bomber.webp',
        'orc': 'cards/monsters/orc.webp',
        'pig-rider': 'cards/monsters/pig-rider.webp',
        'wolf-rider': 'cards/monsters/wolf-rider.webp'
      };
      return AssetManager.getUrl(files[monsterKey] || 'cards/monsters/gobelins.webp');
    },

    getBossCardImage: function (bossKey, phase) {
      var files = {
        'slasher': { 1: 'cards/monsters/slasher-1.webp', 2: 'cards/monsters/slasher-2.webp', 3: 'cards/monsters/slasher-3.webp' },
        'striker': { 1: 'cards/monsters/striker-1.webp', 2: 'cards/monsters/striker-2.webp', 3: 'cards/monsters/striker-3.webp' }
      };
      var phases = files[bossKey] || files.slasher;
      return AssetManager.getUrl(phases[phase] || phases[1]);
    },

    getPowerCardImage: function (powerKey) {
      var files = {
        'attack': 'cards/powers/attack-0.webp',
        'strike': 'cards/powers/strike-0.webp',
        'dash_1': 'cards/powers/dash-1.webp',
        'dash_2': 'cards/powers/dash-2.webp',
        'dash_3': 'cards/powers/dash-3.webp',
        'dash_attack_1': 'cards/powers/dash-attack-1.webp',
        'dash_attack_2': 'cards/powers/dash-attack-2.webp',
        'dash_attack_3': 'cards/powers/dash-attack-3.webp',
        'fireball_1': 'cards/powers/fireball-1.webp',
        'fireball_2': 'cards/powers/fireball-2.webp',
        'fireball_3': 'cards/powers/fireball-3.webp',
        'grab_1': 'cards/powers/grab-1.webp',
        'grab_2': 'cards/powers/grab-2.webp',
        'grab_3': 'cards/powers/grab-3.webp',
        'heal_1': 'cards/powers/heal-1.webp',
        'heal_2': 'cards/powers/heal-2.webp',
        'heal_3': 'cards/powers/heal-3.webp',
        'jump_1': 'cards/powers/jump-1.webp',
        'jump_2': 'cards/powers/jump-2.webp',
        'jump_3': 'cards/powers/jump-3.webp',
        'leech_1': 'cards/powers/leech-1.webp',
        'leech_2': 'cards/powers/leech-2.webp',
        'leech_3': 'cards/powers/leech-3.webp',
        'point_blank_1': 'cards/powers/point-blank-1.webp',
        'point_blank_2': 'cards/powers/point-blank-2.webp',
        'point_blank_3': 'cards/powers/point-blank-3.webp',
        'power_strike_1': 'cards/powers/power-strike-1.webp',
        'power_strike_2': 'cards/powers/power-strike-2.webp',
        'power_strike_3': 'cards/powers/power-strike-3.webp',
        'quick_shot_1': 'cards/powers/quick-shot-1.webp',
        'quick_shot_2': 'cards/powers/quick-shot-2.webp',
        'quick_shot_3': 'cards/powers/quick-shot-3.webp',
        'quick_strike_1': 'cards/powers/quick-strike-1.webp',
        'quick_strike_2': 'cards/powers/quick-strike-2.webp',
        'quick_strike_3': 'cards/powers/quick-strike-3.webp',
        'reinforce_1': 'cards/powers/reinforce-1.webp',
        'reinforce_2': 'cards/powers/reinforce-2.webp',
        'reinforce_3': 'cards/powers/reinforce-3.webp',
        'vortex_1': 'cards/powers/vortex-1.webp',
        'vortex_2': 'cards/powers/vortex-2.webp',
        'vortex_3': 'cards/powers/vortex-3.webp',
        'whirlwind_1': 'cards/powers/whirlwind-1.webp',
        'whirlwind_2': 'cards/powers/whirlwind-2.webp',
        'whirlwind_3': 'cards/powers/whirlwind-3.webp'
      };
      return AssetManager.getUrl(files[powerKey] || 'cards/powers/attack-0.webp');
    }
  };

  // =========================================================================
  //  SECTION 4 — Standalone utility functions
  // =========================================================================

  function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, function (character) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\'': '&#39;', '"': '&quot;' }[character];
    });
  }

  function setNodePosition(node, left, top) {
    dojo.style(node, { left: left + 'px', top: top + 'px' });
  }

  function tileBounds(tiles) {
    var maxX = 0;
    var maxY = 0;
    for (var tileId in tiles) {
      maxX = Math.max(maxX, parseInt(tiles[tileId].x || 0, 10));
      maxY = Math.max(maxY, parseInt(tiles[tileId].y || 0, 10));
    }
    return { maxX: maxX, maxY: maxY };
  }

  function boardAxisOffset(index, maxIndex) {
    if (index <= 0) {
      return 0;
    }
    if (index >= maxIndex + 1) {
      return (Math.max(0, maxIndex - 1) * TILE_SIZE) + (2 * BORDER_TILE_SIZE);
    }
    return BORDER_TILE_SIZE + ((index - 1) * TILE_SIZE);
  }

  function tileBox(tile, tiles) {
    var bounds = tileBounds(tiles);
    var x = parseInt(tile.x || 0, 10);
    var y = parseInt(tile.y || 0, 10);
    return {
      left: boardAxisOffset(x, bounds.maxX),
      top: boardAxisOffset(y, bounds.maxY),
      width: (x === 0 || x === bounds.maxX) ? BORDER_TILE_SIZE : TILE_SIZE,
      height: (y === 0 || y === bounds.maxY) ? BORDER_TILE_SIZE : TILE_SIZE
    };
  }

  function createSkipHandler(actionName) {
    return function () {
      if (this.checkAction(actionName)) {
        this.bgaPerformAction(actionName);
      }
    };
  }

  // =========================================================================
  //  SECTION 5 — Templates
  // =========================================================================

  /* eslint-disable no-undef */
  jstpl_hns_tile = '<div id="hns_tile_${id}" class="hns_tile hns_tile_${type}" style="left:${left}px; top:${top}px; width:${width}px; height:${height}px; background-image:url(\'${image}\');" data-tile-id="${id}" role="button" tabindex="-1" aria-label="${type}" title="${type}"><span class="hns_spawn_label">${spawn_label}</span></div>';
  jstpl_hns_entity = '<div id="hns_entity_${id}" class="hns_entity hns_entity_${type} hns_entity_${slug} ${state_class}" data-entity-id="${id}" data-entity-type="${type}" data-monster-key="${monster_key}" role="button" tabindex="-1" aria-label="${label}"><img src="${image}" alt="${label}" /><span class="hns_entity_effects">${effects}</span><span class="hns_entity_health">${health}</span></div>';
  jstpl_hns_monster_card = '<div id="hns_monster_card_${key}" class="hns_monster_card ${state_class}" data-monster-key="${key}" role="button" tabindex="0" aria-label="${name}"><div class="hns_monster_card_effects">${effects}</div><img src="${image}" alt="${name}" /><div class="hns_monster_card_footer"><strong>${name}</strong><span>${count}</span></div><div class="hns_monster_card_losses">${losses}</div></div>';
  jstpl_hns_power_card = '<div id="hns_power_card_${key}" class="hns_power_card ${classes}" data-power-key="${power_key}" data-slot="${slot}" role="button" tabindex="0" aria-label="${name}" title="${name}"><img src="${image}" alt="${name}" /><div class="hns_power_cooldown_overlay">${cooldown_overlay}</div><div class="hns_power_card_badges">${badges}</div></div>';
  jstpl_hns_hero_card = '<div class="hns_hero_card_body"><img class="hns_hero_portrait" src="${portrait}" alt="${name}" /><div class="hns_hero_details"><div class="hns_hero_identity"><span class="hns_hero_color" style="background:#${color}"></span><strong>${name}</strong></div><div class="hns_hero_stats"><span>${health_label}: ${health}</span><span>${ap_label}: ${action_points}</span></div><div class="hns_hero_effects">${effects}</div></div></div><div class="hns_hero_mini_powers">${powers}</div>';
  jstpl_hns_event = '<div class="hns_event hns_event_${type}">${message}</div>';
  /* eslint-enable no-undef */

  // Test hook — exposes pure-logic objects for unit testing
  if (typeof window !== 'undefined' && window.__HNS_TEST__) {
    window._HNS = { GameRules: GameRules, MONSTER_TYPES: MONSTER_TYPES, SLIME_TYPE_ARG: SLIME_TYPE_ARG };
  }

  // =========================================================================
  //  SECTION 6 — Main game class
  // =========================================================================

  return declare('bgagame.hacknslash', ebg.core.gamegui, {

    // ----- Lifecycle -----

    constructor: function () {
      this.selectedEntityId = null;
      this.selectedPowerKey = null;
      this.selectedPowerSlot = null;
      this.selectedRewardPowerKey = null;
      this.eventMessages = [];
      this.rewardOffer = [];
      this.rewardUpgrades = [];
      this.rewardOffersByPlayer = {};
      this.rewardUpgradesByPlayer = {};
      this.powerCardZoom = 1;
      this.pendingFreeMoveHighlight = false;
      this.pendingMultiTargetAction = false;
      this._dirtyPanels = {};
      this._renderScheduled = false;
    },

    setup: function (gamedatas) {
      this.gamedatas = gamedatas;
      AssetManager.init(g_gamethemeurl);
      this.eventMessages = this.loadEventMessages();
      this.rewardOffersByPlayer = gamedatas.reward_offers || {};
      this.rewardUpgradesByPlayer = gamedatas.reward_upgrades_by_player || {};
      this.syncRewardForCurrentPlayer(gamedatas);
      this.buildPageLayout();
      this.renderBoard(gamedatas.tiles, gamedatas.entities);
      this.scheduleFreeMoveHighlight();
      this.renderAllPanels();
      this.renderEventMessages();
      this.connect($('hns_power_validate'), 'onclick', 'onValidatePowerSelection');
      this.connect($('hns_power_cancel'), 'onclick', 'onCancelPowerSelection');
      this.connect($('hns_wrap'), 'onkeydown', 'onWrapKeyDown');
      this.setupNotifications();
      this.updateRewardFocusState();
    },

    buildPageLayout: function () {
      var html = ''
        + '<div id="hns_wrap">'
        +   '<aside id="hns_monster_panel" class="whiteblock hns_panel" aria-label="' + _('Monsters') + '">'
        +     '<h3>' + _('Monsters') + '</h3>'
        +     '<div id="hns_monster_cards" class="hns_monster_cards"></div>'
        +   '</aside>'
        +   '<div id="hns_center">'
        +     '<div id="hns_main" class="whiteblock hns_panel">'
        +       '<div class="hns_board_header">'
        +         '<h3>' + _('Dungeon') + '</h3>'
        +         '<div id="hns_board_hint" role="status">' + _('Select a card, then choose a target on the board.') + '</div>'
        +       '</div>'
        +       '<div id="hns_power_confirm" class="hns_power_confirm hns_hidden" role="alert">'
        +         '<span id="hns_power_confirm_text"></span>'
        +         '<button type="button" id="hns_power_validate">' + _('Validate') + '</button>'
        +         '<button type="button" id="hns_power_cancel">' + _('Cancel') + '</button>'
        +       '</div>'
        +       '<div id="hns_board"></div>'
        +     '</div>'
        +     '<div id="hns_events" class="whiteblock hns_panel">'
        +       '<h3>' + _('Events') + '</h3>'
        +       '<div id="hns_event_list" class="hns_event_list" aria-live="polite">'
        +         '<div class="hns_event hns_event_empty">' + _('Events will appear here.') + '</div>'
        +       '</div>'
        +     '</div>'
        +     '<div id="hns_reward_panel" class="whiteblock hns_panel hns_reward_panel">'
        +       '<h3>' + _('Reward') + '</h3>'
        +       '<div id="hns_reward_cards"></div>'
        +     '</div>'
        +   '</div>'
        +   '<aside id="hns_side" class="hns_panel_stack" aria-label="' + _('Hero and powers') + '">'
        +     '<div id="hns_status" class="whiteblock hns_panel hns_hero_panel hns_active_hero_panel">'
        +       '<h3>' + _('Active hero') + '</h3>'
        +       '<div id="hns_active_hero" class="hns_hero_card"></div>'
        +     '</div>'
        +     '<div id="hns_hand" class="whiteblock hns_panel">'
        +       '<div class="hns_hand_header">'
        +         '<h3>' + _('Hero cards') + '</h3>'
        +         '<button type="button" id="hns_card_zoom" class="hns_card_zoom" aria-label="' + _('Zoom cards') + '" title="' + _('Zoom cards') + '">&#128269;<span id="hns_card_zoom_level">1</span></button>'
        +       '</div>'
        +       '<div id="hns_cards"></div>'
        +     '</div>'
        +     '<div id="hns_partner_status" class="whiteblock hns_panel hns_hero_panel hns_partner_panel">'
        +       '<h3>' + _('Partner') + '</h3>'
        +       '<div id="hns_partner_hero" class="hns_hero_card hns_hero_card_compact"></div>'
        +     '</div>'
        +   '</aside>'
        + '</div>';

      var gameArea = (typeof bga !== 'undefined' && bga.gameArea && bga.gameArea())
        ? bga.gameArea().getElement()
        : document.getElementById('game_play_area');

      if (gameArea) {
        gameArea.insertAdjacentHTML('beforeend', html);
        this.connect($('hns_card_zoom'), 'onclick', 'onPowerCardZoomClick');
      }
    },

    onPowerCardZoomClick: function () {
      this.powerCardZoom = this.powerCardZoom >= 3 ? 1 : this.powerCardZoom + 1;
      this.updatePowerCardZoom();
    },

    updatePowerCardZoom: function () {
      var cardsNode = $('hns_cards');
      if (!cardsNode) {
        return;
      }
      dojo.removeClass(cardsNode, 'hns_cards_zoom_1 hns_cards_zoom_2 hns_cards_zoom_3');
      dojo.addClass(cardsNode, 'hns_cards_zoom_' + (this.powerCardZoom || 1));
      if ($('hns_card_zoom_level')) {
        $('hns_card_zoom_level').textContent = String(this.powerCardZoom || 1);
      }
    },

    // ----- Board rendering -----

    renderBoard: function (tiles, entities) {
      dojo.empty('hns_board');
      this.resizeBoardToTiles(tiles);

      var tileGrid = {};
      for (var tIdx in tiles) {
        var t = tiles[tIdx];
        tileGrid[t.x + ',' + t.y] = t;
      }

      for (var tileId in tiles) {
        var tile = tiles[tileId];
        var box = tileBox(tile, tiles);
        dojo.place(this.format_block('jstpl_hns_tile', {
          id: tile.id,
          type: tile.type,
          left: box.left,
          top: box.top,
          width: box.width,
          height: box.height,
          spawn_label: escapeHtml(tile.spawn_label || ''),
          image: AssetManager.getTileImage(tile, tileGrid)
        }), 'hns_board');
        this.connect($('hns_tile_' + tile.id), 'onclick', 'onTileClick');
        this.connect($('hns_tile_' + tile.id), 'onmouseenter', 'onTileMouseEnter');
        this.connect($('hns_tile_' + tile.id), 'onmouseleave', 'onPowerAreaMouseLeave');
      }

      for (var entityId in entities) {
        this.placeEntity(entities[entityId], tiles);
      }

      this.updateAllEntityStackOrders();
    },

    resizeBoardToTiles: function (tiles) {
      var bounds = tileBounds(tiles);
      dojo.style('hns_board', {
        width: boardAxisOffset(bounds.maxX + 1, bounds.maxX) + 'px',
        height: boardAxisOffset(bounds.maxY + 1, bounds.maxY) + 'px'
      });
    },

    placeEntity: function (entity, tiles) {
      var tile = tiles[entity.tile_id];
      if (!tile) {
        return;
      }

      var entityInfo = this.getEntityInfo(entity);
      dojo.place(this.format_block('jstpl_hns_entity', {
        id: entity.id,
        type: entity.type,
        slug: entityInfo.slug,
        state_class: this.entityStateClasses(entity),
        monster_key: entityInfo.monsterKey,
        image: entityInfo.image,
        label: entityInfo.label,
        effects: this.renderEntityEffects(entity),
        health: entity.health || ''
      }), 'hns_board');

      var node = $('hns_entity_' + entity.id);
      var box = tileBox(tile, tiles);
      setNodePosition(node, box.left + (box.width / 2), box.top + (box.height / 2));
      this.connect(node, 'onclick', 'onEntityClick');
      this.connect(node, 'onmouseenter', 'onEntityMouseEnter');
      this.connect(node, 'onmouseleave', 'onPowerAreaMouseLeave');
    },

    updateEntityStackOrder: function (tileId) {
      var entities = this.gamedatas.entities || {};
      var stacked = [];
      for (var eid in entities) {
        var e = entities[eid];
        if (String(e.tile_id) === String(tileId) && e.state !== 'dead' && parseInt(e.health || 0, 10) > 0) {
          stacked.push(eid);
        }
      }
      if (stacked.length <= 1) {
        if (stacked.length === 1) {
          var singleNode = $('hns_entity_' + stacked[0]);
          if (singleNode) { dojo.style(singleNode, 'zIndex', 4); }
        }
        return;
      }
      stacked.sort(function (a, b) {
        return parseInt(entities[b].health || 0, 10) - parseInt(entities[a].health || 0, 10);
      });
      for (var i = 0; i < stacked.length; i++) {
        var node = $('hns_entity_' + stacked[i]);
        if (node) {
          dojo.style(node, 'zIndex', 4 + i);
        }
      }
    },

    updateAllEntityStackOrders: function () {
      var entities = this.gamedatas.entities || {};
      var seen = {};
      for (var eid in entities) {
        var tid = entities[eid].tile_id;
        if (tid && !seen[tid]) {
          seen[tid] = true;
          this.updateEntityStackOrder(tid);
        }
      }
    },

    entityStateClasses: function (entity) {
      var classes = [];
      if (entity.state === 'dead' || parseInt(entity.health || 0, 10) <= 0) {
        classes.push('hns_entity_dead');
      }
      if (this.hasActiveShield(entity)) {
        classes.push('hns_entity_shielded');
      }
      if (this.hasThorns(entity)) {
        classes.push('hns_entity_thorns');
      }
      if (entity.type === 'monster' && (entity.monster_size || 'small') === 'big') {
        classes.push('hns_entity_big');
      }
      return classes.join(' ');
    },

    // ----- Panel rendering -----

    renderAllPanels: function () {
      this.renderMonsterCards();
      this.renderHeroPanels();
      this.renderPowerCards();
      this.renderRewardOffer();
    },

    markPanelDirty: function (name) {
      this._dirtyPanels[name] = true;
      if (!this._renderScheduled) {
        this._renderScheduled = true;
        var self = this;
        window.requestAnimationFrame(function () {
          self._renderScheduled = false;
          self.flushDirtyPanels();
        });
      }
    },

    flushDirtyPanels: function () {
      var dirty = this._dirtyPanels;
      this._dirtyPanels = {};
      if (dirty.monsters) { this.renderMonsterCards(); }
      if (dirty.heroes) { this.renderHeroPanels(); }
      if (dirty.powers) { this.renderPowerCards(); }
      if (dirty.rewards) { this.renderRewardOffer(); }
    },

    renderMonsterCards: function () {
      dojo.empty('hns_monster_cards');

      var monsterGroups = this.getVisibleMonsterGroups();
      var hasMonsters = false;

      for (var monsterKey in monsterGroups) {
        hasMonsters = true;
        var group = monsterGroups[monsterKey];
        var effects = this.renderMonsterEffectIcons(group.effects);
        dojo.place(this.format_block('jstpl_hns_monster_card', {
          key: monsterKey,
          name: group.name,
          image: AssetManager.getMonsterCardImage(monsterKey),
          count: 'x' + group.count,
          state_class: group.count > 0 ? (group.deadCount > 0 ? 'hns_monster_card_damaged' : '') : 'hns_monster_card_dead',
          losses: group.deadCount > 0 ? '-' + group.deadCount : '',
          effects: effects
        }), 'hns_monster_cards');
        this.connect($('hns_monster_card_' + monsterKey), 'onclick', 'onMonsterCardClick');
      }

      if (!hasMonsters) {
        dojo.place('<div class="hns_empty_state">' + _('No monster in this room.') + '</div>', 'hns_monster_cards');
      }
    },

    renderHeroPanels: function () {
      var players = this.gamedatas.players || {};
      var activePlayerId = this.getActivePlayerId();
      var activePlayer = players[activePlayerId] || players[this.player_id];
      var partnerPlayer = null;

      for (var playerId in players) {
        if (!activePlayer || String(playerId) !== String(activePlayer.id)) {
          partnerPlayer = players[playerId];
          break;
        }
      }

      this.renderHeroPanel('hns_active_hero', activePlayer, false);
      this.renderHeroPanel('hns_partner_hero', partnerPlayer, true);
    },

    renderHeroPanel: function (nodeId, player, compact) {
      dojo.empty(nodeId);
      if (!player) {
        dojo.place('<div class="hns_empty_state">' + _('No partner in solo mode.') + '</div>', nodeId);
        return;
      }

      var effects = this.renderHeroEffects(player);
      var powers = compact ? this.renderHeroMiniPowers(player.id) : '';
      var maxActionPoints = this.getMaxActionPoints();
      var actionPoints = (player.action_points || 0) + '/' + maxActionPoints;

      dojo.place(this.format_block('jstpl_hns_hero_card', {
        color: player.color || 'ffffff',
        name: player.name || _('Hero'),
        portrait: this.getHeroPortraitImage(player.id),
        health_label: _('HP'),
        health: player.health || 0,
        ap_label: _('AP'),
        action_points: actionPoints,
        effects: effects,
        powers: powers
      }), nodeId);
    },

    renderPowerCards: function () {
      dojo.empty('hns_cards');
      this.updatePowerCardZoom();

      var activePlayerId = this.getActivePlayerId() || this.player_id;
      var powers = this.getPowersForPlayer(activePlayerId);
      if (powers.length === 0) {
        powers = [
          { power_key: 'strike', cooldown: 0 },
          { power_key: 'attack', cooldown: 0 },
          { power_key: 'dash_1', cooldown: 0 }
        ];
      }

      powers = powers.slice(0, MAX_POWER_CARDS);
      for (var i = 0; i < powers.length; i++) {
        var power = powers[i];
        var info = this.getPowerInfo(power.power_key);
        var cooldown = parseInt(power.cooldown || 0, 10);
        var playsRemaining = parseInt(power.plays_remaining || 0, 10);
        var isFree = this.isPowerFree(power.power_key, activePlayerId);
        var isPlayableCooldown = cooldown > 0 && playsRemaining <= 0;
        var classes = (isPlayableCooldown ? 'hns_cooldown ' : '') + (isFree ? 'hns_free ' : '');
        var badges = '';
        if (isFree) {
          badges += '<span class="hns_power_badge hns_power_badge_free">' + _('FREE') + '</span>';
        }
        if (playsRemaining > 0) {
          badges += '<span class="hns_power_badge hns_power_badge_free">x' + playsRemaining + '</span>';
        }
        if (isPlayableCooldown) {
          badges += '<span class="hns_power_badge hns_power_badge_cooldown">' + _('CD') + ' ' + cooldown + '</span>';
        }
        if (info.upgrades_to) {
          badges += '<button type="button" id="hns_upgrade_power_' + power.id + '" class="hns_upgrade_power" data-slot="' + power.slot + '">' + _('Upgrade') + '</button>';
        }

        dojo.place(this.format_block('jstpl_hns_power_card', {
          key: power.id || power.slot || power.power_key,
          power_key: power.power_key,
          slot: power.slot || '',
          name: info.name,
          image: AssetManager.getPowerCardImage(power.power_key),
          classes: classes,
          cooldown_overlay: isPlayableCooldown ? cooldown : '',
          badges: badges
        }), 'hns_cards');
        this.connect($('hns_power_card_' + (power.id || power.slot || power.power_key)), 'onclick', 'onPowerCardClick');
        if (info.upgrades_to && $('hns_upgrade_power_' + power.id)) {
          this.connect($('hns_upgrade_power_' + power.id), 'onclick', 'onUpgradePowerClick');
        }
      }
    },

    renderRewardOffer: function () {
      dojo.empty('hns_reward_cards');
      var hasOffer = this.rewardOffer && this.rewardOffer.length > 0;
      var hasUpgrades = this.rewardUpgrades && this.rewardUpgrades.length > 0;
      this.updateRewardFocusState();
      if (!hasOffer && !hasUpgrades) {
        dojo.place('<div class="hns_empty_state">' + _('No reward available.') + '</div>', 'hns_reward_cards');
        return;
      }

      dojo.place('<div class="hns_reward_title">' + _('Choose a new power, then click one of your cards to replace it.') + '</div>', 'hns_reward_cards');
      for (var i = 0; i < (this.rewardOffer || []).length; i++) {
        var powerKey = this.rewardOffer[i];
        var info = this.getPowerInfo(powerKey);
        dojo.place(this.format_block('jstpl_hns_power_card', {
          key: 'reward_' + powerKey,
          power_key: powerKey,
          slot: '',
          name: info.name,
          image: AssetManager.getPowerCardImage(powerKey),
          classes: 'hns_reward_offer' + (this.selectedRewardPowerKey === powerKey ? ' hns_selected' : ''),
          cooldown_overlay: '',
          badges: '<span class="hns_power_badge hns_power_badge_free">' + _('NEW') + '</span>'
        }), 'hns_reward_cards');
        this.connect($('hns_power_card_reward_' + powerKey), 'onclick', 'onRewardOfferClick');
      }

      if (hasUpgrades) {
        dojo.place('<div class="hns_reward_title hns_reward_upgrade_title">' + _('Available upgrades') + '</div>', 'hns_reward_cards');
      }
      for (var j = 0; j < (this.rewardUpgrades || []).length; j++) {
        var upgrade = this.rewardUpgrades[j];
        var upgradeInfo = this.getPowerInfo(upgrade.to);
        dojo.place(this.format_block('jstpl_hns_power_card', {
          key: 'upgrade_' + upgrade.slot,
          power_key: upgrade.to,
          slot: upgrade.slot,
          name: upgradeInfo.name,
          image: AssetManager.getPowerCardImage(upgrade.to),
          classes: 'hns_reward_upgrade',
          cooldown_overlay: '',
          badges: '<span class="hns_power_badge hns_power_badge_free">' + _('UPGRADE') + '</span>'
        }), 'hns_reward_cards');
        this.connect($('hns_power_card_upgrade_' + upgrade.slot), 'onclick', 'onRewardUpgradeClick');
      }
    },

    updateRewardFocusState: function (forceFocus) {
      var hasRewards = (this.rewardOffer && this.rewardOffer.length > 0) || (this.rewardUpgrades && this.rewardUpgrades.length > 0);
      var focused = typeof forceFocus === 'boolean' ? forceFocus : hasRewards;
      dojo.toggleClass('hns_wrap', 'hns_reward_focus', focused);
      dojo.toggleClass('hns_main', 'hns_panel_folded', focused);
      dojo.toggleClass('hns_events', 'hns_panel_folded', focused);
    },

    renderHeroMiniPowers: function (playerId) {
      var powers = this.getPowersForPlayer(playerId).slice(0, MAX_POWER_CARDS);
      var html = '';
      for (var i = 0; i < powers.length; i++) {
        var power = powers[i];
        var cooldown = parseInt(power.cooldown || 0, 10);
        var classes = (cooldown > 0 ? 'hns_cooldown ' : '') + (this.isPowerFree(power.power_key, playerId) ? 'hns_free ' : '');
        html += '<div class="hns_hero_mini_power ' + classes + '" title="' + escapeHtml(this.getPowerInfo(power.power_key).name) + '"><img src="' + AssetManager.getPowerCardImage(power.power_key) + '" alt="" /></div>';
      }
      return html;
    },

    renderMonsterEffectIcons: function (effects) {
      var html = '';
      if (effects.shield) {
        html += '<img class="hns_effect_icon" src="' + AssetManager.getUrl('tiles/markers/shield.webp') + '" alt="Shield" />';
      }
      if (effects.thorns) {
        html += '<img class="hns_effect_icon" src="' + AssetManager.getUrl('cards/monsters/thorns.webp') + '" alt="Thorns" />';
      }
      return html;
    },

    renderEntityEffects: function (entity) {
      var effects = '';
      if (this.hasActiveShield(entity)) {
        effects += '<img class="hns_entity_shield_icon" src="' + AssetManager.getUrl('tiles/markers/shield.webp') + '" alt="Shield" title="Shield" />';
      }
      if (this.hasThorns(entity)) {
        effects += '<img class="hns_entity_thorns_icon" src="' + AssetManager.getUrl('cards/monsters/thorns.webp') + '" alt="Thorns" title="Thorns" />';
      }
      return effects;
    },

    renderHeroEffects: function (player) {
      var effects = [];
      var hero = this.getHeroEntityForPlayer(player.id);
      var status = hero ? String(hero.status || '') : '';
      if (hero && GameRules.isHeroHeldBySlime(hero, this.gamedatas.tiles || {}, this.gamedatas.entities || {})) {
        effects.push('<img class="hns_effect_icon" src="' + AssetManager.getUrl('tiles/markers/slimed.webp') + '" alt="Slimed" title="Slimed" />');
      }
      return effects.length ? effects.join('') : '<span class="hns_empty_state">' + _('No effect') + '</span>';
    },

    // ----- Event log -----

    pushEvent: function (message, type) {
      this.eventMessages.unshift({ message: message, type: type || 'effect' });
      this.eventMessages = this.eventMessages.slice(0, MAX_EVENTS);
      this.saveEventMessages();
      this.renderEventMessages();
    },

    renderEventMessages: function () {
      dojo.empty('hns_event_list');
      if (this.eventMessages.length === 0) {
        dojo.place('<div class="hns_event hns_event_empty">' + _('Events will appear here.') + '</div>', 'hns_event_list');
        return;
      }

      for (var i = 0; i < this.eventMessages.length; i++) {
        dojo.place(this.format_block('jstpl_hns_event', this.eventMessages[i]), 'hns_event_list');
      }
    },

    eventStorageKey: function () {
      return 'hns_events_' + (this.table_id || (this.gamedatas && this.gamedatas.table_id) || 'local');
    },

    loadEventMessages: function () {
      try {
        var raw = localStorage.getItem(this.eventStorageKey());
        var messages = raw ? JSON.parse(raw) : [];
        return Array.isArray(messages) ? messages.slice(0, MAX_EVENTS) : [];
      } catch (e) {
        return [];
      }
    },

    saveEventMessages: function () {
      try {
        localStorage.setItem(this.eventStorageKey(), JSON.stringify(this.eventMessages.slice(0, MAX_EVENTS)));
      } catch (e) {
        // Ignore storage failures; the in-memory log still works.
      }
    },

    // ----- Event handlers -----

    onWrapKeyDown: function (evt) {
      if (evt.key !== 'Enter' && evt.key !== ' ') { return; }
      var target = evt.target;
      if (target.getAttribute('role') === 'button' && target.getAttribute('tabindex') === '0') {
        dojo.stopEvent(evt);
        target.click();
      }
    },

    onTileClick: function (evt) {
      dojo.stopEvent(evt);
      var tileId = evt.currentTarget.getAttribute('data-tile-id');
      var tiles = this.gamedatas.tiles || {};
      var entities = this.gamedatas.entities || {};

      if (this.selectedPowerKey) {
        var selectedPower = this.getPowerInfo(this.selectedPowerKey);
        if (selectedPower.effect === 'dash_attack') {
          if (!GameRules.isTileValidPowerTarget(tiles[this.getHeroEntityForPlayer(this.getActivePlayerId()).tile_id], tiles[tileId], selectedPower, tiles, entities)) {
            return;
          }
          this.selectedPowerTileId = tileId;
          this.selectedPowerTargetEntityIds = [];
          this.highlightPowerTargets();
          this.updatePowerConfirmControls();
          return;
        }

        if (this.requiresConfirmTargets()) {
          this.selectedPowerTileId = tileId;
          this.selectedPowerTargetEntityIds = [];
          this.highlightPowerTargets();
          this.updatePowerConfirmControls();
          return;
        }
        var payload = { target_tile_id: tileId, selected_tile_id: tileId };
        var power = this.getPowerInfo(this.selectedPowerKey);
        if (power.effect === 'attack' || power.effect === 'dash_attack') {
          var targetEntityId = GameRules.entityOnTile(tileId, entities, MONSTER_TYPES);
          if (targetEntityId !== null) {
            payload.target_entity_id = targetEntityId;
          }
        }
        if (power.effect === 'heal') {
          var heroEntityId = GameRules.entityOnTile(tileId, entities, ['hero']);
          if (heroEntityId !== null) {
            payload.target_entity_id = heroEntityId;
          }
        }
        this.playSelectedPower(payload);
        return;
      }

      var tile = tiles[tileId];
      var hero = this.getHeroEntityForPlayer(this.getActivePlayerId());
      if (this.checkAction('actMove') && tile && GameRules.isWalkableTile(tile) && !GameRules.isTileOccupied(tile.id, entities) && !GameRules.isHeroHeldBySlime(hero, tiles, entities)) {
        this.bgaPerformAction('actMove', { tile_id: tileId });
      }
    },

    onEntityClick: function (evt) {
      dojo.stopEvent(evt);
      var entityId = evt.currentTarget.getAttribute('data-entity-id');
      if (this.selectedPowerKey) {
        var power = this.getPowerInfo(this.selectedPowerKey);
        if (power.effect === 'dash_attack') {
          if (!this.selectedPowerTileId || !this.isConfirmTargetValid(entityId)) {
            return;
          }
          this.playSelectedPower({ selected_tile_id: this.selectedPowerTileId, target_tile_id: this.selectedPowerTileId, target_entity_id: entityId });
          return;
        }

        if (this.requiresConfirmTargets()) {
          if (!this.selectedPowerTileId) {
            var entity = this.gamedatas.entities && this.gamedatas.entities[entityId];
            if (entity && entity.tile_id) {
              this.selectedPowerTileId = String(entity.tile_id);
              this.selectedPowerTargetEntityIds = [];
              this.highlightPowerTargets();
            }
          }
          this.toggleConfirmTarget(entityId);
          return;
        }
        var payload = { target_entity_id: entityId };
        var clickedEntity = this.gamedatas.entities && this.gamedatas.entities[entityId];
        if ((power.effect === 'area_attack' || power.effect === 'jump' || power.effect === 'pull') && clickedEntity && clickedEntity.tile_id) {
          payload.target_tile_id = clickedEntity.tile_id;
          payload.selected_tile_id = clickedEntity.tile_id;
        }
        this.playSelectedPower(payload);
        return;
      }

      this.selectEntity(entityId);
    },

    onTileMouseEnter: function (evt) {
      var tileId = evt.currentTarget.getAttribute('data-tile-id');
      this.previewPowerArea(tileId);
    },

    onEntityMouseEnter: function (evt) {
      var entityId = evt.currentTarget.getAttribute('data-entity-id');
      var entity = this.gamedatas.entities && this.gamedatas.entities[entityId];
      if (entity && entity.tile_id) {
        this.previewPowerArea(entity.tile_id);
      }
    },

    onPowerAreaMouseLeave: function () {
      this.clearPowerAreaPreview();
    },

    onMonsterCardClick: function (evt) {
      dojo.stopEvent(evt);
      var monsterKey = evt.currentTarget.getAttribute('data-monster-key');
      var entities = this.gamedatas.entities || {};
      for (var entityId in entities) {
        var info = this.getEntityInfo(entities[entityId]);
        if (info.monsterKey === monsterKey) {
          this.selectEntity(entityId);
          return;
        }
      }
    },

    onPowerCardClick: function (evt) {
      dojo.stopEvent(evt);
      var powerKey = evt.currentTarget.getAttribute('data-power-key');
      var slot = evt.currentTarget.getAttribute('data-slot');
      if (this.selectedRewardPowerKey) {
        var power = this.getPowerForCurrentPlayer(powerKey, slot);
        if (power && this.checkAction('actChooseReward')) {
          this.bgaPerformAction('actChooseReward', { mode: 'replace', slot: power.slot, power_key: this.selectedRewardPowerKey });
        }
        return;
      }

      var info = this.getPowerInfo(powerKey);
      var hero = this.getHeroEntityForPlayer(this.getActivePlayerId());
      if (GameRules.isMovementBlockedBySlime(info, hero, this.gamedatas.tiles || {}, this.gamedatas.entities || {})) {
        return;
      }

      if (info.effect === 'move_area_attack' && info.distance && parseInt(info.distance[1] || 0, 10) === 0) {
        this.selectedPowerKey = powerKey;
        this.selectedPowerSlot = slot;
        this.playSelectedPower({});
        return;
      }

      if (this.selectedPowerKey === powerKey && String(this.selectedPowerSlot || '') === String(slot || '')) {
        this.clearSelectedPower();
        return;
      }

      this.selectedPowerKey = powerKey;
      this.selectedPowerSlot = slot;
      this.selectedPowerTileId = null;
      this.selectedPowerTargetEntityIds = [];
      this.pendingMultiTargetAction = false;
      this.clearMonsterAttackHighlights();
      dojo.query('.hns_power_card').removeClass('hns_selected');
      dojo.addClass(evt.currentTarget, 'hns_selected');
      this.highlightPowerTargets();
      this.updatePowerConfirmControls();
    },

    onRewardOfferClick: function (evt) {
      dojo.stopEvent(evt);
      this.selectedRewardPowerKey = evt.currentTarget.getAttribute('data-power-key');
      this.renderRewardOffer();
    },

    onUpgradePowerClick: function (evt) {
      dojo.stopEvent(evt);
      var slot = evt.currentTarget.getAttribute('data-slot');
      if (this.checkAction('actChooseReward')) {
        this.bgaPerformAction('actChooseReward', { mode: 'upgrade', slot: slot, power_key: '' });
      }
    },

    onRewardUpgradeClick: function (evt) {
      this.onUpgradePowerClick(evt);
    },

    onValidatePowerSelection: function (evt) {
      dojo.stopEvent(evt);
      this.validatePowerSelection();
    },

    onCancelPowerSelection: function (evt) {
      dojo.stopEvent(evt);
      this.clearSelectedPower();
    },

    // ----- Power selection -----

    playSelectedPower: function (payload) {
      if (!this.selectedPowerKey || !this.checkAction('actPlayCard')) {
        return;
      }

      var power = this.getPowerForCurrentPlayer(this.selectedPowerKey, this.selectedPowerSlot);
      if (!power) {
        return;
      }

      var args = payload || {};
      args.card_id = power.id;
      this.bgaPerformAction('actPlayCard', args);
      this.clearSelectedPower();
    },

    clearSelectedPower: function () {
      this.selectedPowerKey = null;
      this.selectedPowerSlot = null;
      this.selectedPowerTileId = null;
      this.selectedPowerTargetEntityIds = [];
      this.pendingMultiTargetAction = false;
      dojo.query('.hns_power_card').removeClass('hns_selected');
      this.clearPowerHighlights();
      this.updatePowerConfirmControls();
    },

    selectEntity: function (entityId) {
      this.selectedEntityId = entityId;
      this.clearPowerHighlights();
      this.clearMonsterAttackHighlights();
      dojo.query('.hns_entity').removeClass('hns_selected');
      dojo.query('.hns_monster_card').removeClass('hns_selected');

      var entityNode = $('hns_entity_' + entityId);
      if (entityNode) {
        dojo.addClass(entityNode, 'hns_selected');
      }

      var entity = this.gamedatas.entities && this.gamedatas.entities[entityId];
      if (!entity) {
        return;
      }

      var info = this.getEntityInfo(entity);
      var cardNode = $('hns_monster_card_' + info.monsterKey);
      if (cardNode) {
        dojo.addClass(cardNode, 'hns_selected');
      }

      this.highlightMonsterAttackTiles(entity);
    },

    isSelectedPowerPull: function () {
      return this.selectedPowerKey && this.getPowerInfo(this.selectedPowerKey).effect === 'pull';
    },

    isSelectedPowerMultiAttack: function () {
      var power = this.getPowerInfo(this.selectedPowerKey);
      return this.selectedPowerKey && power.effect === 'attack' && parseInt(power.targets || 1, 10) > 1;
    },

    isSelectedPowerDashAttack: function () {
      return this.selectedPowerKey && this.getPowerInfo(this.selectedPowerKey).effect === 'dash_attack';
    },

    requiresConfirmTargets: function () {
      return this.isSelectedPowerMultiAttack() || this.isSelectedPowerDashAttack();
    },

    toggleConfirmTarget: function (entityId) {
      if (!this.selectedPowerTileId || !this.isConfirmTargetValid(entityId)) {
        return;
      }

      var ids = this.selectedPowerTargetEntityIds || [];
      var index = ids.indexOf(String(entityId));
      var maxTargets = parseInt(this.getPowerInfo(this.selectedPowerKey).targets || 1, 10);
      if (index !== -1 && (!this.isSelectedPowerMultiAttack() || ids.length >= maxTargets)) {
        ids.splice(index, 1);
        this.selectedPowerTargetEntityIds = ids;
        this.updateConfirmTargetReticles();
        this.updatePowerConfirmControls();
        return;
      }

      if (ids.length >= maxTargets) {
        return;
      }

      ids.push(String(entityId));
      this.selectedPowerTargetEntityIds = ids;
      this.updateConfirmTargetReticles();
      this.updatePowerConfirmControls();
    },

    updateConfirmTargetReticles: function () {
      dojo.query('.hns_entity').removeClass('hns_selected');
      dojo.query('.hns_target_count').forEach(dojo.destroy);

      var counts = {};
      var ids = this.selectedPowerTargetEntityIds || [];
      for (var i = 0; i < ids.length; i++) {
        counts[ids[i]] = (counts[ids[i]] || 0) + 1;
      }

      for (var entityId in counts) {
        var node = $('hns_entity_' + entityId);
        if (!node) {
          continue;
        }

        dojo.addClass(node, 'hns_selected');
        dojo.place('<span class="hns_target_count">x' + counts[entityId] + '</span>', node);
      }
    },

    togglePullTarget: function (entityId) {
      this.toggleConfirmTarget(entityId);
    },

    maybePlayPullPower: function () {
      if (!this.selectedPowerTileId) {
        return;
      }
      var tiles = this.gamedatas.tiles || {};
      var entities = this.gamedatas.entities || {};
      var power = this.getPowerInfo(this.selectedPowerKey);
      var adjacentIds = GameRules.entitiesInPowerArea(this.selectedPowerTileId, tiles, entities, MONSTER_TYPES, power.area || [1, 1], power.area_metric || 'orthogonal');
      var requiredTargets = adjacentIds.length;
      if (requiredTargets === 0 || (this.selectedPowerTargetEntityIds || []).length >= requiredTargets) {
        this.playSelectedPower({
          selected_tile_id: this.selectedPowerTileId,
          target_tile_id: this.selectedPowerTileId,
          target_entity_ids: adjacentIds.join(' ')
        });
      }
    },

    validatePowerSelection: function () {
      if (!this.requiresConfirmTargets() || !this.selectedPowerTileId) {
        return;
      }

      var selectedIds = this.selectedPowerTargetEntityIds || [];
      if (selectedIds.length === 0 && !this.isSelectedPowerDashAttack()) {
        return;
      }

      var payload = {
        selected_tile_id: this.selectedPowerTileId,
        target_tile_id: this.selectedPowerTileId
      };
      if (selectedIds.length > 0) {
        payload.target_entity_ids = selectedIds.join(' ');
      }
      this.playSelectedPower(payload);
    },

    updatePowerConfirmControls: function () {
      var panel = $('hns_power_confirm');
      if (!panel) {
        return;
      }

      var shouldShow = this.requiresConfirmTargets() && !!this.selectedPowerTileId;
      dojo.toggleClass(panel, 'hns_hidden', !shouldShow);
      if (!shouldShow) {
        return;
      }

      var selectedCount = (this.selectedPowerTargetEntityIds || []).length;
      var maxTargets = parseInt(this.getPowerInfo(this.selectedPowerKey).targets || 1, 10);
      var adjacentCount = this.confirmTargetIdsForSelectedTile().length;
      var targetLimit = Math.min(maxTargets, adjacentCount);
      var text = $('hns_power_confirm_text');
      if (text) {
        text.innerHTML = _('Targets selected') + ': ' + selectedCount + '/' + targetLimit;
      }

      var validateButton = $('hns_power_validate');
      if (validateButton) {
        validateButton.disabled = selectedCount === 0 && !this.isSelectedPowerDashAttack();
      }
    },

    isConfirmTargetValid: function (entityId) {
      return this.confirmTargetIdsForSelectedTile().indexOf(String(entityId)) !== -1;
    },

    confirmTargetIdsForSelectedTile: function () {
      var tiles = this.gamedatas.tiles || {};
      var entities = this.gamedatas.entities || {};

      if (this.isSelectedPowerPull()) {
        var power = this.getPowerInfo(this.selectedPowerKey);
        return GameRules.entitiesInPowerArea(this.selectedPowerTileId, tiles, entities, MONSTER_TYPES, power.area || [1, 1], power.area_metric || 'orthogonal');
      }

      if (this.isSelectedPowerDashAttack()) {
        var selectedTile = tiles[this.selectedPowerTileId];
        var ids = [];
        if (!selectedTile) {
          return ids;
        }
        for (var dashEntityId in entities) {
          var dashEntity = entities[dashEntityId];
          if ((dashEntity.state || 'active') !== 'active' || !GameRules.isEntityTargetableByPower(selectedTile, dashEntity, this.getPowerInfo(this.selectedPowerKey), tiles, entities, this.selectedPowerTileId)) {
            continue;
          }
          ids.push(String(dashEntityId));
        }
        return ids;
      }

      var power = this.getPowerInfo(this.selectedPowerKey);
      var hero = this.getHeroEntityForPlayer(this.getActivePlayerId());
      var from = hero && tiles[hero.tile_id];
      var ids = [];
      if (!from || !power) {
        return ids;
      }

      for (var entityId in entities) {
        var entity = entities[entityId];
        if ((entity.state || 'active') !== 'active') {
          continue;
        }
        if (GameRules.isEntityTargetableByPower(from, entity, power, tiles, entities, this.selectedPowerTileId)) {
          ids.push(String(entityId));
        }
      }
      return ids;
    },

    // ----- BGA state machine -----

    onEnteringState: function (stateName, args) {
      var stateArgs = args || (this.gamedatas.gamestate && this.gamedatas.gamestate.args) || {};
      if (stateName === 'upgradeReward') {
        this.syncRewardForCurrentPlayer(stateArgs);
      }
      this.renderHeroPanels();
      this.renderPowerCards();
      this.updateRewardFocusState(stateName === 'upgradeReward');
      if (stateName === 'playerTurn') {
        this.highlightFreeMoveTiles(stateArgs);
      } else {
        this.clearFreeMoveHighlights();
      }
    },

    onLeavingState: function () {
      this.clearFreeMoveHighlights();
      this.clearMonsterAttackHighlights();
      this.updateRewardFocusState(false);
    },

    onUpdateActionButtons: function (stateName, args) {
      if (this.isCurrentPlayerActive() && stateName === 'upgradeReward') {
        this.addActionButton('hns_skip_reward_button', _('Skip'), 'onSkipReward');
        return;
      }
      if (this.isCurrentPlayerActive() && stateName === 'playerTurn') {
        args = args || (this.gamedatas.gamestate && this.gamedatas.gamestate.args) || {};
        if (parseInt(args.free_move_available || 0, 10) === 1 || args.free_move_available === true) {
          this.addActionButton('hns_skip_free_move_button', _('Skip free move'), 'onSkipFreeMove');
        }
        if (parseInt(args.free_action_available || 0, 10) === 1 || args.free_action_available === true) {
          this.addActionButton('hns_skip_free_action_button', _('Skip free action'), 'onSkipFreeMove');
        }
        if (parseInt(args.main_action_available || 0, 10) === 1 || args.main_action_available === true) {
          this.addActionButton('hns_skip_main_action_button', _('Skip action'), 'onSkipMainAction');
        }
        this.addActionButton('hns_end_turn_button', _('Ready'), 'onEndTurn');
      }
    },

    onSkipReward: createSkipHandler('actSkipReward'),
    onSkipFreeMove: createSkipHandler('actSkipFreeMove'),
    onSkipMainAction: createSkipHandler('actSkipMainAction'),
    onEndTurn: createSkipHandler('actEndTurn'),

    // ----- Notifications -----

    setupNotifications: function () {
      dojo.subscribe('heroMoved', this, 'notif_heroMoved');
      dojo.subscribe('rewardChosen', this, 'notif_rewardChosen');
      dojo.subscribe('rewardSkipped', this, 'notif_rewardSkipped');
      dojo.subscribe('playerActionState', this, 'notif_playerActionState');
      dojo.subscribe('powerCooldownsUpdated', this, 'notif_powerCooldownsUpdated');
      dojo.subscribe('roundStarted', this, 'notif_roundStarted');
      var eventTypes = [
        'afterCardPlayed', 'afterDash', 'afterKill',
        'afterPushOrPull', 'entityDamaged', 'entityHealed', 'entityStatusChanged',
        'thornsDamage', 'shieldBroken', 'monsterAttack', 'monsterSlime',
        'monsterCharge', 'monsterFrontArc', 'monsterMove', 'monsterSummon',
        'monsterExplode', 'trapDamage', 'bossPhaseDefeated', 'bossPhaseStarted',
        'bossSpawnMinion', 'bossGrantShield', 'bossTurnSkipped',
        'levelCleared', 'levelStarted', 'gameWon', 'gameLost'
      ];
      for (var i = 0; i < eventTypes.length; i++) {
        dojo.subscribe(eventTypes[i], this, 'notif_engineEvent');
      }
      this.setupSynchronousNotifications();
    },

    setupSynchronousNotifications: function () {
      if (!this.notifqueue || !this.notifqueue.setSynchronous) {
        return;
      }

      var animatedEvents = [
        'heroMoved', 'afterDash', 'afterPushOrPull',
        'monsterAttack', 'monsterSlime', 'monsterCharge',
        'monsterFrontArc', 'monsterMove', 'monsterSummon', 'monsterExplode'
      ];
      for (var i = 0; i < animatedEvents.length; i++) {
        this.notifqueue.setSynchronous(animatedEvents[i], ANIM_SYNC_MS);
      }
    },

    notif_heroMoved: function (notif) {
      var entityId = notif.args.entity_id || notif.args.player_id;
      var tileId = notif.args.tile_id;
      this.updatePlayerActionState(notif.args || {});
      this.moveEntityNode(entityId, tileId, true);
      this.pushEvent(_('Hero moves.'), 'effect');
      this.markPanelDirty('heroes');
      this.markPanelDirty('powers');
    },

    notif_engineEvent: function (notif) {
      if (!notif.args) {
        return;
      }
      this.pushEvent(this.describeEngineEvent(notif.args), this.getEventVisualType(notif.args));
      this.refreshFromEvent(notif.args);
    },

    notif_rewardChosen: function (notif) {
      this.updatePowerFromReward(notif.args || {});
      this.applyRewardMapsFromEvent(notif.args || {});
      if (String((notif.args || {}).player_id) === String(this.player_id)) {
        this.rewardOffer = [];
        this.rewardUpgrades = [];
        this.selectedRewardPowerKey = null;
        this.updateRewardFocusState(false);
      }
      this.pushEvent(_('Reward chosen.'), 'effect');
      this.markPanelDirty('powers');
      this.markPanelDirty('rewards');
    },

    notif_rewardSkipped: function (notif) {
      this.applyRewardMapsFromEvent(notif.args || {});
      if (String((notif.args || {}).player_id) === String(this.player_id)) {
        this.rewardOffer = [];
        this.rewardUpgrades = [];
        this.selectedRewardPowerKey = null;
        this.updateRewardFocusState(false);
      }
      this.pushEvent(_('Reward skipped.'), 'effect');
      this.markPanelDirty('rewards');
    },

    notif_playerActionState: function (notif) {
      var args = notif.args || {};
      this.updatePlayerActionState(args);
      this.markPanelDirty('heroes');
      this.markPanelDirty('powers');
      this.scheduleFreeMoveHighlight();
    },

    notif_powerCooldownsUpdated: function (notif) {
      var args = notif.args || {};
      if (args.player_powers) {
        this.gamedatas.player_powers = args.player_powers;
      }
      this.gamedatas.free_action_events = args.free_action_events || [];
      this.markPanelDirty('powers');
      this.markPanelDirty('heroes');
    },

    notif_roundStarted: function (notif) {
      var args = notif.args || {};
      if (args.players) {
        this.gamedatas.players = args.players;
      }
      if (args.entities) {
        this.gamedatas.entities = args.entities;
        this.renderBoard(this.gamedatas.tiles || {}, this.gamedatas.entities);
      }
      this.gamedatas.free_action_events = args.free_action_events || [];
      this.markPanelDirty('heroes');
      this.markPanelDirty('powers');
      this.scheduleFreeMoveHighlight();
    },

    // ----- Animation -----

    moveEntityNode: function (entityId, tileId, animate) {
      if (!entityId || !tileId || !this.gamedatas) {
        return;
      }

      var tiles = this.gamedatas.tiles || {};
      var tile = tiles[tileId];
      if (!tile) {
        return;
      }

      var entityNode = $('hns_entity_' + entityId);
      if (!entityNode) {
        return;
      }

      var fromPosition = animate ? this.entityNodePosition(entityNode) : null;
      var box = tileBox(tile, tiles);
      var toPosition = {
        left: box.left + (box.width / 2),
        top: box.top + (box.height / 2)
      };

      if (animate && fromPosition) {
        this.animateEntityMove(entityNode, fromPosition, toPosition);
      } else {
        setNodePosition(entityNode, toPosition.left, toPosition.top);
      }

      var oldTileId = null;
      if (this.gamedatas.entities && this.gamedatas.entities[entityId]) {
        oldTileId = this.gamedatas.entities[entityId].tile_id;
        this.gamedatas.entities[entityId].tile_id = tileId;
      }

      if (oldTileId && String(oldTileId) !== String(tileId)) {
        this.updateEntityStackOrder(oldTileId);
      }
      this.updateEntityStackOrder(tileId);

      this.clearFreeMoveHighlights();
    },

    entityNodePosition: function (entityNode) {
      return {
        left: parseFloat(entityNode.style.left || dojo.style(entityNode, 'left') || 0),
        top: parseFloat(entityNode.style.top || dojo.style(entityNode, 'top') || 0)
      };
    },

    animateEntityMove: function (entityNode, fromPosition, toPosition) {
      if (Math.abs(fromPosition.left - toPosition.left) < 1 && Math.abs(fromPosition.top - toPosition.top) < 1) {
        return;
      }

      setNodePosition(entityNode, fromPosition.left, fromPosition.top);
      dojo.removeClass(entityNode, 'hns_entity_moving');
      void entityNode.offsetWidth;
      dojo.addClass(entityNode, 'hns_entity_moving');
      dojo.animateProperty({
        node: entityNode,
        duration: ANIM_MOVE_MS,
        properties: {
          left: { start: fromPosition.left, end: toPosition.left, units: 'px' },
          top: { start: fromPosition.top, end: toPosition.top, units: 'px' }
        },
        onEnd: function () {
          setNodePosition(entityNode, toPosition.left, toPosition.top);
          dojo.removeClass(entityNode, 'hns_entity_moving');
        }
      }).play();
      window.setTimeout(function () {
        dojo.removeClass(entityNode, 'hns_entity_moving');
      }, ANIM_MOVE_MS);
    },

    animateMonsterAttack: function (event) {
      var actorIds = event.actor_entity_ids && event.actor_entity_ids.length ? event.actor_entity_ids : [event.source_entity_id];
      var targetId = event.target_entity_id || (event.target_entity_ids && event.target_entity_ids[0]);
      var targetNode = $('hns_entity_' + targetId);
      for (var i = 0; i < actorIds.length; i++) {
        this.animateMonsterActorAttack(actorIds[i], targetNode);
      }
    },

    animateMonsterActorAttack: function (sourceId, targetNode) {
      var sourceNode = $('hns_entity_' + sourceId);
      if (!sourceNode || !targetNode) {
        if (sourceNode) {
          dojo.removeClass(sourceNode, 'hns_entity_attacking');
          void sourceNode.offsetWidth;
          dojo.addClass(sourceNode, 'hns_entity_attacking');
        }
        return;
      }

      dojo.removeClass(sourceNode, 'hns_entity_attacking');
      void sourceNode.offsetWidth;
      dojo.addClass(sourceNode, 'hns_entity_attacking');
      var fromPosition = this.entityNodePosition(sourceNode);
      var sourceBox = sourceNode.getBoundingClientRect();
      var targetBox = targetNode.getBoundingClientRect();
      var lungePosition = {
        left: fromPosition.left + (((targetBox.left + targetBox.width / 2) - (sourceBox.left + sourceBox.width / 2)) * LUNGE_DISTANCE_RATIO),
        top: fromPosition.top + (((targetBox.top + targetBox.height / 2) - (sourceBox.top + sourceBox.height / 2)) * LUNGE_DISTANCE_RATIO)
      };
      dojo.animateProperty({
        node: sourceNode,
        duration: ANIM_LUNGE_FORWARD_MS,
        properties: {
          left: { start: fromPosition.left, end: lungePosition.left, units: 'px' },
          top: { start: fromPosition.top, end: lungePosition.top, units: 'px' }
        },
        onEnd: function () {
          dojo.animateProperty({
            node: sourceNode,
            duration: ANIM_LUNGE_RETURN_MS,
            properties: {
              left: { start: lungePosition.left, end: fromPosition.left, units: 'px' },
              top: { start: lungePosition.top, end: fromPosition.top, units: 'px' }
            },
            onEnd: function () {
              setNodePosition(sourceNode, fromPosition.left, fromPosition.top);
              dojo.removeClass(sourceNode, 'hns_entity_attacking');
            }
          }).play();
        }
      }).play();
    },

    animateMonsterExplosion: function (event) {
      var actorIds = event.actor_entity_ids && event.actor_entity_ids.length ? event.actor_entity_ids : [event.source_entity_id];
      for (var i = 0; i < actorIds.length; i++) {
        var node = $('hns_entity_' + actorIds[i]);
        if (!node) {
          continue;
        }
        dojo.addClass(node, 'hns_entity_exploding');
        window.setTimeout(function (explodingNode) {
          dojo.removeClass(explodingNode, 'hns_entity_exploding');
        }, ANIM_MOVE_MS, node);
      }
    },

    // ----- Highlighting -----

    highlightFreeMoveTiles: function (args) {
      this.clearFreeMoveHighlights();
      args = args || (this.gamedatas && this.gamedatas.gamestate && this.gamedatas.gamestate.args) || {};
      if (!this.canActivePlayerMove(args)) {
        return;
      }

      var tiles = this.gamedatas.tiles || {};
      var entities = this.gamedatas.entities || {};
      var hero = this.getHeroEntityForPlayer(this.getActivePlayerId());
      if (GameRules.isHeroHeldBySlime(hero, tiles, entities)) {
        return;
      }
      var from = hero && tiles[hero.tile_id];
      if (!from) {
        return;
      }

      for (var tileId in tiles) {
        if (GameRules.isFreeMoveTile(from, tiles[tileId], entities)) {
          dojo.addClass('hns_tile_' + tileId, 'hns_free_move_tile');
          dojo.attr('hns_tile_' + tileId, 'tabindex', '0');
        }
      }
    },

    scheduleFreeMoveHighlight: function () {
      if (this.pendingFreeMoveHighlight) {
        return;
      }
      this.pendingFreeMoveHighlight = true;
      var self = this;
      window.setTimeout(function () {
        self.pendingFreeMoveHighlight = false;
        self.highlightFreeMoveTiles();
      }, 0);
    },

    canActivePlayerMove: function (args) {
      var player = this.gamedatas.players && this.gamedatas.players[this.getActivePlayerId()];
      return (parseInt(args.free_move_available || 0, 10) === 1)
        || args.free_move_available === true
        || (player && parseInt(player.free_move_available || 0, 10) === 1)
        || (player && parseInt(player.action_points || 0, 10) > 0);
    },

    clearFreeMoveHighlights: function () {
      dojo.query('.hns_free_move_tile').forEach(function (node) {
        dojo.attr(node, 'tabindex', '-1');
      });
      dojo.query('.hns_free_move_tile').removeClass('hns_free_move_tile');
    },

    highlightPowerTargets: function () {
      this.clearPowerHighlights();
      var power = this.getPowerInfo(this.selectedPowerKey);
      var hero = this.getHeroEntityForPlayer(this.getActivePlayerId());
      var tiles = this.gamedatas.tiles || {};
      var entities = this.gamedatas.entities || {};
      var from = hero && tiles[hero.tile_id];
      if (!power || !from) {
        return;
      }

      if (GameRules.isMovementBlockedBySlime(power, hero, tiles, entities)) {
        return;
      }

      for (var tileId in tiles) {
        var tile = tiles[tileId];
        if (GameRules.isTileValidPowerTarget(from, tile, power, tiles, entities)) {
          dojo.addClass('hns_tile_' + tileId, 'hns_power_target_tile');
          dojo.attr('hns_tile_' + tileId, 'tabindex', '0');
        }
      }

      this.highlightPotentialPowerArea(from, power, tiles, entities);

      for (var entityId in entities) {
        var entity = entities[entityId];
        if ((entity.state || 'active') === 'active' && GameRules.isEntityTargetableByPower(from, entity, power, tiles, entities, this.selectedPowerTileId)) {
          dojo.addClass('hns_entity_' + entityId, 'hns_power_target_entity');
          dojo.attr('hns_entity_' + entityId, 'tabindex', '0');
        }
      }
    },

    highlightPotentialPowerArea: function (from, power, tiles, entities) {
      if (!this.isAreaPreviewPower(power)) {
        return;
      }

      var previewedTileIds = {};
      for (var centerTileId in tiles) {
        var centerTile = tiles[centerTileId];
        if (!GameRules.isTileValidPowerTarget(from, centerTile, power, tiles, entities)) {
          continue;
        }

        var areaTileIds = GameRules.tilesInPowerArea(centerTileId, tiles, power.area || [0, 0], power.area_metric || 'orthogonal');
        for (var i = 0; i < areaTileIds.length; i++) {
          previewedTileIds[areaTileIds[i]] = true;
        }
      }

      for (var tileId in previewedTileIds) {
        dojo.addClass('hns_tile_' + tileId, 'hns_power_area_candidate_tile');
      }
    },

    previewPowerArea: function (tileId) {
      this.clearPowerAreaPreview();
      var power = this.getPowerInfo(this.selectedPowerKey);
      if (!this.isAreaPreviewPower(power)) {
        return;
      }

      var hero = this.getHeroEntityForPlayer(this.getActivePlayerId());
      var tiles = this.gamedatas.tiles || {};
      var entities = this.gamedatas.entities || {};
      var from = hero && tiles[hero.tile_id];
      var centerTile = tiles[tileId];
      if (!from || !centerTile || !GameRules.isTileValidPowerTarget(from, centerTile, power, tiles, entities)) {
        return;
      }

      var areaTileIds = GameRules.tilesInPowerArea(tileId, tiles, power.area || [0, 0], power.area_metric || 'orthogonal');
      for (var i = 0; i < areaTileIds.length; i++) {
        dojo.addClass('hns_tile_' + areaTileIds[i], 'hns_power_area_preview_tile');
      }
    },

    isAreaPreviewPower: function (power) {
      return !!power && !!power.area && ['area_attack', 'move_area_attack', 'pull'].indexOf(power.effect) !== -1;
    },

    clearPowerAreaPreview: function () {
      dojo.query('.hns_power_area_preview_tile').removeClass('hns_power_area_preview_tile');
    },

    clearPowerHighlights: function () {
      this.clearPowerAreaPreview();
      dojo.query('.hns_power_area_candidate_tile').removeClass('hns_power_area_candidate_tile');
      dojo.query('.hns_power_target_tile').forEach(function (node) {
        dojo.attr(node, 'tabindex', '-1');
      });
      dojo.query('.hns_power_target_tile').removeClass('hns_power_target_tile');
      dojo.query('.hns_power_target_entity').forEach(function (node) {
        dojo.attr(node, 'tabindex', '-1');
      });
      dojo.query('.hns_power_target_entity').removeClass('hns_power_target_entity');
      dojo.query('.hns_target_count').forEach(dojo.destroy);
    },

    highlightMonsterAttackTiles: function (entity) {
      if (!entity || entity.type !== 'monster' || (entity.state || 'active') !== 'active') {
        return;
      }

      var monster = (this.gamedatas.monsters || {})[entity.type_arg] || {};
      if (monster.can_attack === false || parseInt(monster.range || 0, 10) <= 0) {
        return;
      }

      var tiles = this.gamedatas.tiles || {};
      var from = tiles[entity.tile_id];
      if (!from) {
        return;
      }

      for (var tileId in tiles) {
        if (GameRules.isTileInMonsterAttackRange(from, tiles[tileId], monster, tiles)) {
          dojo.addClass('hns_tile_' + tileId, 'hns_monster_attack_tile');
        }
      }
    },

    clearMonsterAttackHighlights: function () {
      dojo.query('.hns_monster_attack_tile').removeClass('hns_monster_attack_tile');
    },

    // ----- State mutation -----

    refreshFromEvent: function (event) {
      this.updatePlayerActionState(event);
      if (event.type === 'levelCleared') {
        this.applyRewardMapsFromEvent(event);
        this.syncRewardForCurrentPlayer(event);
        this.renderRewardOffer();
        this.updateRewardFocusState(true);
      }
      if (event.type === 'levelStarted') {
        this.applyLevelSnapshot(event);
      }
      if (event.type === 'afterKill') {
        this.markEntityDead(event.target_entity_id);
        this.scheduleFreeMoveHighlight();
      }
      if (event.type === 'monsterSummon') {
        this.addSummonedEntity(event);
      }
      if (event.type === 'bossPhaseStarted') {
        this.updateBossPhaseFromEvent(event);
      }
      if (event.type === 'shieldBroken') {
        this.updateEntityShieldFromEvent(event);
      }
      if (event.type === 'monsterSlime') {
        this.updateEntityStatusFromEvent({ entity_id: event.target_entity_id, status: 'slimed' });
      }
      if (event.type === 'entityStatusChanged') {
        this.updateEntityStatusFromEvent(event);
      }
      if (event.type === 'bossGrantShield') {
        this.applyBossGrantShield(event);
      }
      if (['monsterAttack', 'monsterSlime', 'monsterCharge', 'monsterFrontArc', 'monsterSummon'].indexOf(event.type) !== -1) {
        this.animateMonsterAttack(event);
      }
      if (event.type === 'monsterExplode') {
        this.animateMonsterExplosion(event);
        this.updateExplosionTargetHealth(event);
      }

      if (event.source_entity_id && event.target_tile_id) {
        this.moveEventEntities(event);
      }

      this.updateEntityHealthFromEvent(event);

      if (event.entity_id && this.gamedatas.entities && this.gamedatas.entities[event.entity_id]) {
        if (typeof event.health !== 'undefined') {
          this.gamedatas.entities[event.entity_id].health = event.health;
          this.updatePlayerHealthFromHeroEntity(event.entity_id);
        }
        if (event.tile_id) {
          this.moveEntityNode(event.entity_id, event.tile_id);
        }
      }
      this.markPanelDirty('monsters');
      this.markPanelDirty('heroes');
      this.markPanelDirty('powers');
      this.markPanelDirty('rewards');
    },

    updateBossPhaseFromEvent: function (event) {
      var entityId = event.entity_id || event.source_entity_id;
      if (!entityId || !this.gamedatas.entities || !this.gamedatas.entities[entityId]) {
        return;
      }

      var entity = this.gamedatas.entities[entityId];
      entity.type = 'boss';
      entity.boss_key = event.boss_key || entity.boss_key;
      entity.phase = parseInt(event.phase || entity.phase || 1, 10);
      if (typeof event.health !== 'undefined') {
        entity.health = parseInt(event.health || 0, 10);
        entity.state = entity.health > 0 ? 'active' : entity.state;
        this.updateEntityHealthBadge(entityId, entity.health);
      }
    },

    updateExplosionTargetHealth: function (event) {
      var healthByEntityId = event.target_health_by_entity_id || {};
      for (var entityId in healthByEntityId) {
        if (!this.gamedatas.entities || !this.gamedatas.entities[entityId]) {
          continue;
        }

        var health = parseInt(healthByEntityId[entityId] || 0, 10);
        this.gamedatas.entities[entityId].health = health;
        this.updateEntityHealthBadge(entityId, health);
        if (health <= 0) {
          this.markEntityDead(entityId);
        }
        this.updatePlayerHealthFromHeroEntity(entityId);
      }
    },

    moveEventEntities: function (event) {
      var entityIds = event.moved_entity_ids && event.moved_entity_ids.length ? event.moved_entity_ids : [event.source_entity_id];
      for (var i = 0; i < entityIds.length; i++) {
        this.moveEntityNode(entityIds[i], event.target_tile_id, true);
      }
    },

    addSummonedEntity: function (event) {
      var entity = event.summoned_entity || null;
      if (!entity && event.summoned_entity_id) {
        entity = {
          id: event.summoned_entity_id,
          type: 'monster',
          type_arg: event.monster_id || 0,
          monster_size: 'small',
          tile_id: event.target_tile_id,
          health: 1,
          state: 'active'
        };
      }
      if (!entity || !entity.id) {
        return;
      }

      this.gamedatas.entities = this.gamedatas.entities || {};
      this.gamedatas.entities[entity.id] = entity;
      if (!$('hns_entity_' + entity.id)) {
        this.placeEntity(entity, this.gamedatas.tiles || {});
      }
    },

    updatePlayerActionState: function (args) {
      if (!args || !args.player_id || !this.gamedatas.players || !this.gamedatas.players[args.player_id]) {
        return;
      }

      var player = this.gamedatas.players[args.player_id];
      if (typeof args.action_points !== 'undefined') {
        player.action_points = parseInt(args.action_points || 0, 10);
      }
      if (typeof args.free_move_available !== 'undefined') {
        player.free_move_available = parseInt(args.free_move_available || 0, 10);
      }
      if (typeof args.main_action_available !== 'undefined') {
        player.main_action_available = parseInt(args.main_action_available || 0, 10);
      }

      if (typeof args.free_action_events !== 'undefined') {
        this.gamedatas.free_action_events = args.free_action_events || [];
      }

      if (args.player_power_id && this.gamedatas.player_powers && this.gamedatas.player_powers[args.player_power_id]) {
        if (args.power_key) {
          this.gamedatas.player_powers[args.player_power_id].power_key = args.power_key;
        }
        this.gamedatas.player_powers[args.player_power_id].cooldown = parseInt(args.power_cooldown || 0, 10);
        if (typeof args.power_plays_remaining !== 'undefined') {
          this.gamedatas.player_powers[args.player_power_id].plays_remaining = parseInt(args.power_plays_remaining || 0, 10);
        }
      }
    },

    syncRewardForCurrentPlayer: function (source) {
      source = source || {};
      if (source.reward_offers) {
        this.rewardOffersByPlayer = source.reward_offers;
      }
      if (source.reward_upgrades_by_player) {
        this.rewardUpgradesByPlayer = source.reward_upgrades_by_player;
      }

      this.rewardOffer = this.rewardOffersByPlayer[this.player_id] || source.reward_offer || [];
      this.rewardUpgrades = this.rewardUpgradesByPlayer[this.player_id] || source.reward_upgrades || [];
    },

    applyRewardMapsFromEvent: function (event) {
      if (event.reward_offers) {
        this.rewardOffersByPlayer = event.reward_offers;
      }
      if (event.reward_upgrades_by_player) {
        this.rewardUpgradesByPlayer = event.reward_upgrades_by_player;
      }
      if (String(event.player_id || '') !== String(this.player_id)) {
        this.syncRewardForCurrentPlayer(event);
      }
    },

    applyLevelSnapshot: function (event) {
      if (!event.tiles || !event.entities) {
        return;
      }

      this.gamedatas.tiles = event.tiles;
      this.gamedatas.entities = event.entities;
      if (event.players) {
        this.gamedatas.players = event.players;
      }
      if (event.player_powers) {
        this.gamedatas.player_powers = event.player_powers;
      }
      if (event.level_monster_abilities) {
        this.gamedatas.level_monster_abilities = event.level_monster_abilities;
      }

      this.selectedEntityId = null;
      this.clearFreeMoveHighlights();
      this.renderBoard(this.gamedatas.tiles, this.gamedatas.entities);
      this.renderPowerCards();
      this.scheduleFreeMoveHighlight();
    },

    updateEntityHealthFromEvent: function (event) {
      var entityId = event.target_entity_id || event.entity_id;
      var health = typeof event.target_health !== 'undefined' ? event.target_health : event.health;
      if (!entityId || typeof health === 'undefined' || !this.gamedatas.entities || !this.gamedatas.entities[entityId]) {
        return;
      }

      this.gamedatas.entities[entityId].health = health;
      this.updateEntityHealthBadge(entityId, health);
      if (parseInt(health || 0, 10) <= 0) {
        this.markEntityDead(entityId);
      }
      this.updatePlayerHealthFromHeroEntity(entityId);
      var entity = this.gamedatas.entities[entityId];
      if (entity && entity.tile_id) {
        this.updateEntityStackOrder(entity.tile_id);
      }
    },

    updateEntityShieldFromEvent: function (event) {
      var entityId = event.source_entity_id;
      if (!entityId || !this.gamedatas.entities || !this.gamedatas.entities[entityId]) {
        return;
      }

      this.gamedatas.entities[entityId].shield_broken = true;
      var node = $('hns_entity_' + entityId);
      if (node) {
        dojo.removeClass(node, 'hns_entity_shielded');
        dojo.query('.hns_entity_shield_icon', node).forEach(function (shieldIcon) {
          dojo.destroy(shieldIcon);
        });
      }
      this.updateEntityEffects(entityId);
    },

    applyBossGrantShield: function (event) {
      var ids = event.target_entity_ids || [];
      for (var i = 0; i < ids.length; i++) {
        var entityId = ids[i];
        if (!this.gamedatas.entities || !this.gamedatas.entities[entityId]) {
          continue;
        }
        this.gamedatas.entities[entityId].has_shield = true;
        this.gamedatas.entities[entityId].shield_broken = false;
        this.updateEntityEffects(entityId);
      }
    },

    updateEntityStatusFromEvent: function (event) {
      var entityId = event.entity_id;
      if (!entityId || !this.gamedatas.entities || !this.gamedatas.entities[entityId]) {
        return;
      }

      this.gamedatas.entities[entityId].status = event.status || '';
    },

    updateEntityEffects: function (entityId) {
      var node = $('hns_entity_' + entityId);
      var entity = this.gamedatas.entities && this.gamedatas.entities[entityId];
      if (!node || !entity) {
        return;
      }

      var effects = dojo.query('.hns_entity_effects', node);
      if (effects.length > 0) {
        effects[0].innerHTML = this.renderEntityEffects(entity);
      }
    },

    markEntityDead: function (entityId) {
      if (!entityId || !this.gamedatas.entities || !this.gamedatas.entities[entityId]) {
        return;
      }

      var entity = this.gamedatas.entities[entityId];
      var node = $('hns_entity_' + entityId);
      if (entity.type === 'monster' || entity.type === 'boss') {
        delete this.gamedatas.entities[entityId];
        if (node) {
          dojo.destroy(node);
        }
        this.clearExpiredSlimeStatuses();
        if (String(this.selectedEntityId) === String(entityId)) {
          this.selectedEntityId = null;
          this.clearMonsterAttackHighlights();
        }
        return;
      }

      entity.state = 'dead';
      entity.health = 0;
      this.updateEntityHealthBadge(entityId, 0);
      if (node) {
        dojo.addClass(node, 'hns_entity_dead');
      }
    },

    updateEntityHealthBadge: function (entityId, health) {
      var node = $('hns_entity_' + entityId);
      if (!node) {
        return;
      }
      var badges = dojo.query('.hns_entity_health', node);
      if (badges.length > 0) {
        badges[0].innerHTML = parseInt(health || 0, 10) > 0 ? health : '0';
      }
    },

    updatePlayerHealthFromHeroEntity: function (entityId) {
      var entity = this.gamedatas.entities && this.gamedatas.entities[entityId];
      if (!entity || entity.type !== 'hero' || !this.gamedatas.players || !this.gamedatas.players[entity.owner]) {
        return;
      }

      this.gamedatas.players[entity.owner].health = entity.health;
    },

    clearExpiredSlimeStatuses: function () {
      var tiles = this.gamedatas.tiles || {};
      var entities = this.gamedatas.entities || {};
      for (var entityId in entities) {
        var entity = entities[entityId];
        if (entity.type !== 'hero' || !GameRules.hasSlimeStatus(entity.status) || GameRules.isHeroHeldBySlime(entity, tiles, entities)) {
          continue;
        }

        entity.status = '';
      }
    },

    updatePowerFromReward: function (args) {
      var playerPowers = this.gamedatas.player_powers || {};
      for (var powerId in playerPowers) {
        if (String(playerPowers[powerId].player_id) === String(args.player_id) && String(playerPowers[powerId].slot) === String(args.slot)) {
          playerPowers[powerId].power_key = args.power_key;
          playerPowers[powerId].cooldown = 0;
          return;
        }
      }
    },

    // ----- Data access -----

    getEntityInfo: function (entity) {
      if (entity.type === 'hero') {
        var heroKey = this.getHeroVisualKey(entity.owner);
        return {
          slug: heroKey,
          monsterKey: 'hero',
          label: _('Hero'),
          image: AssetManager.getUrl('tiles/' + heroKey + '-pixel.webp')
        };
      }

      if (entity.type === 'boss') {
        var bossKey = entity.boss_key || 'slasher';
        var boss = (this.gamedatas.bosses || {})[bossKey] || {};
        var phase = parseInt(entity.phase || 1, 10);
        return {
          slug: bossKey,
          monsterKey: bossKey + '-' + phase,
          label: boss.name || bossKey,
          image: AssetManager.getBossTileImage(bossKey)
        };
      }

      var monsterKey = AssetManager.getMonsterKey(entity.type_arg);
      var monster = (this.gamedatas.monsters || {})[entity.type_arg] || {};
      return {
        slug: monsterKey,
        monsterKey: monsterKey,
        label: monster.name || monsterKey,
        image: AssetManager.getMonsterTileImage(monsterKey)
      };
    },

    getHeroVisualKey: function (playerId) {
      var ids = [];
      var players = this.gamedatas.players || {};
      for (var id in players) {
        ids.push(String(id));
      }
      ids.sort(function (a, b) { return parseInt(a, 10) - parseInt(b, 10); });
      var index = Math.max(0, ids.indexOf(String(playerId)));
      return index === 1 ? 'hero-2' : 'hero-1';
    },

    getHeroPortraitImage: function (playerId) {
      return AssetManager.getUrl('tiles/' + this.getHeroVisualKey(playerId) + '-pixel.webp');
    },

    getVisibleMonsterGroups: function () {
      var groups = {};
      var entities = this.gamedatas.entities || {};
      for (var entityId in entities) {
        var entity = entities[entityId];
        if (MONSTER_TYPES.indexOf(entity.type) === -1) {
          continue;
        }

        var info = this.getEntityInfo(entity);
        if (!groups[info.monsterKey]) {
          groups[info.monsterKey] = {
            name: info.label,
            count: 0,
            deadCount: 0,
            effects: {}
          };
        }
        if (entity.state === 'dead' || parseInt(entity.health || 0, 10) <= 0) {
          groups[info.monsterKey].deadCount++;
          continue;
        }
        groups[info.monsterKey].count++;
        this.collectMonsterEffects(entity, groups[info.monsterKey].effects);
      }
      return groups;
    },

    collectMonsterEffects: function (entity, effects) {
      var status = String(entity.status || '');
      if (status.indexOf('shield') !== -1) {
        effects.shield = true;
      }
      if (status.indexOf('thorn') !== -1) {
        effects.thorns = true;
      }

      var levelAbilities = this.gamedatas.level_monster_abilities || [];
      for (var i = 0; i < levelAbilities.length; i++) {
        if (levelAbilities[i] === 'shield' || levelAbilities[i] === 'thorns') {
          effects[levelAbilities[i]] = true;
        }
      }
    },

    hasActiveShield: function (entity) {
      return !!entity
        && MONSTER_TYPES.indexOf(entity.type) !== -1
        && parseInt(entity.has_shield || 0, 10) === 1
        && parseInt(entity.shield_broken || 0, 10) !== 1;
    },

    hasThorns: function (entity) {
      if (!entity || MONSTER_TYPES.indexOf(entity.type) === -1) {
        return false;
      }
      var status = String(entity.status || '');
      if (status.indexOf('thorn') !== -1) {
        return true;
      }
      return (this.gamedatas.level_monster_abilities || []).indexOf('thorns') !== -1;
    },

    getHeroEntityForPlayer: function (playerId) {
      var entities = this.gamedatas.entities || {};
      for (var entityId in entities) {
        var entity = entities[entityId];
        if (entity.type === 'hero' && String(entity.owner) === String(playerId)) {
          return entity;
        }
      }
      return null;
    },

    getPowersForPlayer: function (playerId) {
      var powers = [];
      var playerPowers = this.gamedatas.player_powers || {};
      for (var powerId in playerPowers) {
        if (String(playerPowers[powerId].player_id) === String(playerId)) {
          powers.push(playerPowers[powerId]);
        }
      }
      powers.sort(function (a, b) { return parseInt(a.slot || 0, 10) - parseInt(b.slot || 0, 10); });
      return powers;
    },

    getPowerForCurrentPlayer: function (powerKey, slot) {
      var activePlayerId = this.player_id;
      var powers = this.getPowersForPlayer(activePlayerId);
      for (var i = 0; i < powers.length; i++) {
        if (slot && String(powers[i].slot) === String(slot)) {
          return powers[i];
        }
        if (!slot && powers[i].power_key === powerKey) {
          return powers[i];
        }
      }
      return null;
    },

    getPowerInfo: function (powerKey) {
      var bonusCards = this.gamedatas.bonus_cards || {};
      return bonusCards[powerKey] || { name: powerKey };
    },

    isPowerFree: function (powerKey, playerId) {
      var info = this.getPowerInfo(powerKey);
      var events = this.gamedatas.free_action_events || [];
      var power = this.getPowerForCurrentPlayer(powerKey, null);
      if (!info.free_triggers || info.free_triggers.length === 0) {
        return false;
      }
      for (var i = 0; i < info.free_triggers.length; i++) {
        if (events.indexOf(info.free_triggers[i]) === -1) {
          continue;
        }
        if (power && parseInt(power.plays_remaining || 0, 10) > 0) {
          return true;
        }
        if (!power || parseInt(power.cooldown || 0, 10) === 0) {
          return true;
        }
      }
      return false;
    },

    getMaxActionPoints: function () {
      return this.getPlayerCount() === 1 ? SOLO_ACTION_POINTS : MULTI_ACTION_POINTS;
    },

    getPlayerCount: function () {
      var players = this.gamedatas.players || {};
      var count = 0;
      for (var playerId in players) {
        count++;
      }
      return count;
    },

    getActivePlayerId: function () {
      if (this.gamedatas && this.gamedatas.gamestate && this.gamedatas.gamestate.active_player && this.gamedatas.gamestate.type !== 'multipleactiveplayer') {
        return this.gamedatas.gamestate.active_player;
      }
      return this.player_id;
    },

    // ----- Utility -----

    describeEngineEvent: function (event) {
      var type = event.type || 'event';
      if (type === 'damage')       { return _('Damage dealt.'); }
      if (type === 'heal')         { return _('Hero heals.'); }
      if (type === 'entityHealed') { return _('Hero heals.'); }
      if (type === 'levelCleared') { return _('Room cleared.'); }
      if (type === 'levelStarted') { return _('New room revealed.'); }
      if (type === 'gameWon')      { return _('Victory!'); }
      if (type === 'freeAction')   { return _('A free action is available.'); }
      return event.message || type;
    },

    getEventVisualType: function (event) {
      var type = event.type || '';
      if (type.indexOf('damage') !== -1) { return 'damage'; }
      if (type.indexOf('heal') !== -1 || type.indexOf('Healed') !== -1) { return 'heal'; }
      if (type.indexOf('free') !== -1) { return 'free'; }
      return 'effect';
    }
  });
});
