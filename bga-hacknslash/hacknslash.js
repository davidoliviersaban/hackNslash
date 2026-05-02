define([
  'dojo',
  'dojo/_base/declare',
  'ebg/core/gamegui',
  'ebg/counter'
], function (dojo, declare) {
  return declare('bgagame.hacknslash', ebg.core.gamegui, {
    constructor: function () {
      this.selectedEntityId = null;
      this.selectedPowerKey = null;
      this.eventMessages = [];
    },

    setup: function (gamedatas) {
      this.gamedatas = gamedatas;
      this.renderBoard(gamedatas.tiles, gamedatas.entities);
      this.renderMonsterCards();
      this.renderHeroPanels();
      this.renderPowerCards();
      this.setupNotifications();
    },

    renderBoard: function (tiles, entities) {
      dojo.empty('hns_board');

      for (var tileId in tiles) {
        var tile = tiles[tileId];
        dojo.place(this.format_block('jstpl_hns_tile', {
          id: tile.id,
          type: tile.type,
          left: tile.x * 96 + 24,
          top: tile.y * 96 + 24
        }), 'hns_board');
        this.connect($('hns_tile_' + tile.id), 'onclick', 'onTileClick');
      }

      for (var entityId in entities) {
        this.placeEntity(entities[entityId], tiles);
      }
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
        monster_key: entityInfo.monsterKey,
        image: entityInfo.image,
        label: entityInfo.label,
        health: entity.health || ''
      }), 'hns_board');

      var node = $('hns_entity_' + entity.id);
      dojo.style(node, {
        left: (tile.x * 96 + 68) + 'px',
        top: (tile.y * 96 + 68) + 'px'
      });

      this.connect(node, 'onclick', 'onEntityClick');
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
          image: this.getMonsterCardImage(monsterKey),
          count: 'x' + group.count,
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
      var maxActionPoints = this.getPlayerCount() === 1 ? 2 : 1;
      var actionPoints = compact ? '-' : ((player.action_points || 0) + '/' + maxActionPoints);

      dojo.place(this.format_block('jstpl_hns_hero_card', {
        color: player.color || 'ffffff',
        name: player.name || _('Hero'),
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

      var activePlayerId = this.getActivePlayerId() || this.player_id;
      var powers = this.getPowersForPlayer(activePlayerId);
      if (powers.length === 0) {
        powers = [
          { power_key: 'strike', cooldown: 0 },
          { power_key: 'attack', cooldown: 0 },
          { power_key: 'dash_1', cooldown: 0 }
        ];
      }

      powers = powers.slice(0, 3);
      for (var i = 0; i < powers.length; i++) {
        var power = powers[i];
        var info = this.getPowerInfo(power.power_key);
        var cooldown = parseInt(power.cooldown || 0, 10);
        var isFree = this.isPowerFree(power.power_key, activePlayerId);
        var classes = (cooldown > 0 ? 'hns_cooldown ' : '') + (isFree ? 'hns_free ' : '');
        var badges = '';
        if (isFree) {
          badges += '<span class="hns_power_badge hns_power_badge_free">FREE</span>';
        }
        if (cooldown > 0) {
          badges += '<span class="hns_power_badge hns_power_badge_cooldown">CD ' + cooldown + '</span>';
        }

        dojo.place(this.format_block('jstpl_hns_power_card', {
          key: power.power_key,
          name: info.name,
          image: this.getPowerCardImage(power.power_key),
          classes: classes,
          badges: badges
        }), 'hns_cards');
        this.connect($('hns_power_card_' + power.power_key), 'onclick', 'onPowerCardClick');
      }
    },

    renderHeroMiniPowers: function (playerId) {
      var powers = this.getPowersForPlayer(playerId).slice(0, 3);
      var html = '';
      for (var i = 0; i < powers.length; i++) {
        var power = powers[i];
        var cooldown = parseInt(power.cooldown || 0, 10);
        var classes = (cooldown > 0 ? 'hns_cooldown ' : '') + (this.isPowerFree(power.power_key, playerId) ? 'hns_free ' : '');
        html += '<div class="hns_hero_mini_power ' + classes + '" title="' + this.escapeHtml(this.getPowerInfo(power.power_key).name) + '"><img src="' + this.getPowerCardImage(power.power_key) + '" alt="" /></div>';
      }
      return html;
    },

    onTileClick: function (evt) {
      dojo.stopEvent(evt);
      var tileId = evt.currentTarget.getAttribute('data-tile-id');

      if (this.checkAction('actMove')) {
        this.bgaPerformAction('actMove', { tile_id: tileId });
      }
    },

    onEntityClick: function (evt) {
      dojo.stopEvent(evt);
      var entityId = evt.currentTarget.getAttribute('data-entity-id');
      this.selectEntity(entityId);
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
      this.selectedPowerKey = powerKey;
      dojo.query('.hns_power_card').removeClass('hns_selected');
      dojo.addClass(evt.currentTarget, 'hns_selected');
    },

    selectEntity: function (entityId) {
      this.selectedEntityId = entityId;
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
    },

    onEnteringState: function (stateName, args) {
      this.renderHeroPanels();
      this.renderPowerCards();
    },

    onLeavingState: function () {},

    onUpdateActionButtons: function (stateName) {
      if (this.isCurrentPlayerActive() && stateName === 'playerTurn') {
        this.addActionButton('hns_end_turn_button', _('End turn'), 'onEndTurn');
      }
    },

    onEndTurn: function () {
      if (this.checkAction('actEndTurn')) {
        this.bgaPerformAction('actEndTurn');
      }
    },

    setupNotifications: function () {
      dojo.subscribe('heroMoved', this, 'notif_heroMoved');
      var eventTypes = [
        'cardPlayed',
        'afterCardPlayed',
        'afterDash',
        'afterKill',
        'afterPushOrPull',
        'thornsDamage',
        'shieldBroken',
        'monsterAttack',
        'monsterMove',
        'monsterExplode',
        'trapDamage',
        'bossPhaseDefeated',
        'bossPhaseStarted',
        'bossSpawnMinion',
        'levelCleared',
        'levelStarted',
        'gameWon'
      ];
      for (var i = 0; i < eventTypes.length; i++) {
        dojo.subscribe(eventTypes[i], this, 'notif_engineEvent');
      }
    },

    notif_heroMoved: function (notif) {
      var entityId = notif.args.entity_id || notif.args.player_id;
      var tileId = notif.args.tile_id;
      this.moveEntityNode(entityId, tileId);
      this.pushEvent(_('Hero moves.'), 'effect');
    },

    notif_engineEvent: function (notif) {
      if (!notif.args) {
        return;
      }
      this.pushEvent(this.describeEngineEvent(notif.args), this.getEventVisualType(notif.args));
      this.refreshFromEvent(notif.args);
    },

    moveEntityNode: function (entityId, tileId) {
      if (!entityId || !tileId || !this.gamedatas) {
        return;
      }

      var tile = this.gamedatas.tiles && this.gamedatas.tiles[tileId];
      if (!tile) {
        return;
      }

      var entityNode = $('hns_entity_' + entityId);
      if (!entityNode) {
        return;
      }

      dojo.style(entityNode, {
        left: (tile.x * 96 + 68) + 'px',
        top: (tile.y * 96 + 68) + 'px'
      });

      if (this.gamedatas.entities && this.gamedatas.entities[entityId]) {
        this.gamedatas.entities[entityId].tile_id = tileId;
      }
    },

    pushEvent: function (message, type) {
      this.eventMessages.unshift({ message: message, type: type || 'effect' });
      this.eventMessages = this.eventMessages.slice(0, 3);
      dojo.empty('hns_event_list');
      for (var i = 0; i < this.eventMessages.length; i++) {
        dojo.place(this.format_block('jstpl_hns_event', this.eventMessages[i]), 'hns_event_list');
      }
    },

    refreshFromEvent: function (event) {
      if (event.entity_id && this.gamedatas.entities && this.gamedatas.entities[event.entity_id]) {
        if (typeof event.health !== 'undefined') {
          this.gamedatas.entities[event.entity_id].health = event.health;
        }
        if (event.tile_id) {
          this.moveEntityNode(event.entity_id, event.tile_id);
        }
      }
      this.renderMonsterCards();
      this.renderHeroPanels();
      this.renderPowerCards();
    },

    getVisibleMonsterGroups: function () {
      var groups = {};
      var entities = this.gamedatas.entities || {};
      for (var entityId in entities) {
        var entity = entities[entityId];
        if (entity.type !== 'monster' || entity.state === 'dead' || parseInt(entity.health || 0, 10) <= 0) {
          continue;
        }

        var info = this.getEntityInfo(entity);
        if (!groups[info.monsterKey]) {
          groups[info.monsterKey] = {
            name: info.label,
            count: 0,
            effects: {}
          };
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

    renderMonsterEffectIcons: function (effects) {
      var html = '';
      if (effects.shield) {
        html += '<img class="hns_effect_icon" src="' + this.getAssetUrl('cards/monsters/shield.png') + '" alt="Shield" />';
      }
      if (effects.thorns) {
        html += '<img class="hns_effect_icon" src="' + this.getAssetUrl('cards/monsters/thorns.png') + '" alt="Thorns" />';
      }
      return html;
    },

    renderHeroEffects: function (player) {
      var effects = [];
      var hero = this.getHeroEntityForPlayer(player.id);
      var status = hero ? String(hero.status || '') : '';
      if (status.indexOf('slimed') !== -1 || status.indexOf('stuck') !== -1 || status.indexOf('stick') !== -1) {
        effects.push('<span class="hns_effect_badge">Slimed</span>');
      }
      return effects.length ? effects.join('') : '<span class="hns_empty_state">' + _('No effect') + '</span>';
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

    getPowerInfo: function (powerKey) {
      var bonusCards = this.gamedatas.bonus_cards || {};
      return bonusCards[powerKey] || { name: powerKey };
    },

    isPowerFree: function (powerKey, playerId) {
      var info = this.getPowerInfo(powerKey);
      var player = this.gamedatas.players && this.gamedatas.players[playerId];
      return !!(info.free_triggers && info.free_triggers.length > 0 && player && parseInt(player.main_action_available || 0, 10) === 0);
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
      if (this.gamedatas && this.gamedatas.gamestate && this.gamedatas.gamestate.active_player) {
        return this.gamedatas.gamestate.active_player;
      }
      return this.player_id;
    },

    getEntityInfo: function (entity) {
      if (entity.type === 'hero') {
        return {
          slug: 'hero',
          monsterKey: 'hero',
          label: _('Hero'),
          image: this.getAssetUrl('tiles/markers/checker-white.png')
        };
      }

      var monsterKey = this.getMonsterKey(entity.type_arg);
      var monster = (this.gamedatas.monsters || {})[entity.type_arg] || {};
      return {
        slug: monsterKey,
        monsterKey: monsterKey,
        label: monster.name || monsterKey,
        image: this.getMonsterTileImage(monsterKey)
      };
    },

    getMonsterKey: function (typeArg) {
      var keys = {
        1: 'gobelins',
        2: 'slimes',
        3: 'evil-eye',
        4: 'kamikaze',
        5: 'wizard',
        6: 'bomber',
        7: 'orc',
        8: 'pig-rider',
        9: 'wolf-rider'
      };
      return keys[typeArg] || 'monster';
    },

    getMonsterTileImage: function (monsterKey) {
      var files = {
        'gobelins': 'tiles/monsters/goblin-pixel.png',
        'slimes': 'tiles/monsters/slime-pixel.png',
        'evil-eye': 'tiles/monsters/evil-eye-pixel.png',
        'kamikaze': 'tiles/monsters/goblin-kamikaze-pixel.png',
        'wizard': 'tiles/monsters/goblin-wizard-pixel.png',
        'bomber': 'tiles/monsters/goblin-bomber-pixel.png',
        'orc': 'tiles/monsters/orc-pixel.png',
        'pig-rider': 'tiles/monsters/pig-rider-pixel.png',
        'wolf-rider': 'tiles/monsters/wolf-rider-pixel.png'
      };
      return this.getAssetUrl(files[monsterKey] || 'tiles/markers/monster.png');
    },

    getMonsterCardImage: function (monsterKey) {
      return this.getAssetUrl('cards/monsters/' + monsterKey + '.png');
    },

    getPowerCardImage: function (powerKey) {
      var files = {
        'attack': 'cards/powers/attack-0.png',
        'strike': 'cards/powers/strike-0.png',
        'dash_1': 'cards/powers/dash-1.png',
        'dash_2': 'cards/powers/dash-2.png',
        'dash_3': 'cards/powers/dash.png',
        'vortex': 'cards/powers/vortex.png'
      };
      return this.getAssetUrl(files[powerKey] || 'cards/powers/powers-1.png');
    },

    getAssetUrl: function (path) {
      return g_gamethemeurl + 'img/' + path;
    },

    describeEngineEvent: function (event) {
      var type = event.type || 'event';
      if (type === 'damage') {
        return _('Damage dealt.');
      }
      if (type === 'heal') {
        return _('Hero heals.');
      }
      if (type === 'levelCleared') {
        return _('Room cleared.');
      }
      if (type === 'levelStarted') {
        return _('New room revealed.');
      }
      if (type === 'gameWon') {
        return _('Victory!');
      }
      if (type === 'freeAction') {
        return _('A free action is available.');
      }
      return event.message || type;
    },

    getEventVisualType: function (event) {
      var type = event.type || '';
      if (type.indexOf('damage') !== -1) {
        return 'damage';
      }
      if (type.indexOf('heal') !== -1) {
        return 'heal';
      }
      if (type.indexOf('free') !== -1) {
        return 'free';
      }
      return 'effect';
    },

    escapeHtml: function (value) {
      return String(value).replace(/[&<>'"]/g, function (character) {
        return {
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '\'': '&#39;',
          '"': '&quot;'
        }[character];
      });
    }
  });
});
