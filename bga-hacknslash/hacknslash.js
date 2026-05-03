define([
  'dojo',
  'dojo/_base/declare',
  'ebg/core/gamegui',
  'ebg/counter'
], function (dojo, declare) {
  // Templates that were previously declared in hacknslash_hacknslash.tpl. They
  // are kept on the global scope so this.format_block('jstpl_hns_*', ...) keeps
  // working as before.
  /* eslint-disable no-undef */
  jstpl_hns_tile = '<div id="hns_tile_${id}" class="hns_tile hns_tile_${type}" style="left:${left}px; top:${top}px; width:${width}px; height:${height}px; background-image:url(\'${image}\');" data-tile-id="${id}" title="${type}"></div>';
  jstpl_hns_entity = '<div id="hns_entity_${id}" class="hns_entity hns_entity_${type} hns_entity_${slug} ${state_class}" data-entity-id="${id}" data-entity-type="${type}" data-monster-key="${monster_key}"><img src="${image}" alt="${label}" /><span class="hns_entity_health">${health}</span></div>';
  jstpl_hns_monster_card = '<div id="hns_monster_card_${key}" class="hns_monster_card ${state_class}" data-monster-key="${key}"><div class="hns_monster_card_effects">${effects}</div><img src="${image}" alt="${name}" /><div class="hns_monster_card_footer"><strong>${name}</strong><span>${count}</span></div><div class="hns_monster_card_losses">${losses}</div></div>';
  jstpl_hns_power_card = '<div id="hns_power_card_${key}" class="hns_power_card ${classes}" data-power-key="${power_key}" data-slot="${slot}"><img src="${image}" alt="${name}" /><div class="hns_power_cooldown_overlay">${cooldown_overlay}</div><div class="hns_power_card_badges">${badges}</div></div>';
  jstpl_hns_hero_card = '<div class="hns_hero_identity"><span class="hns_hero_color" style="background:#${color}"></span><strong>${name}</strong></div><div class="hns_hero_stats"><span>${health_label}: ${health}</span><span>${ap_label}: ${action_points}</span></div><div class="hns_hero_effects">${effects}</div><div class="hns_hero_mini_powers">${powers}</div>';
  jstpl_hns_event = '<div class="hns_event hns_event_${type}">${message}</div>';
  /* eslint-enable no-undef */

  return declare('bgagame.hacknslash', ebg.core.gamegui, {
    constructor: function () {
      this.boardTileSize = 70;
      this.boardBorderTileSize = 36;
      this.selectedEntityId = null;
      this.selectedPowerKey = null;
      this.selectedPowerSlot = null;
      this.selectedRewardPowerKey = null;
      this.eventMessages = [];
      this.rewardOffer = [];
      this.rewardUpgrades = [];
      this.pendingFreeMoveHighlight = false;
      this.pendingMultiTargetAction = false;
    },

    /**
     * Build the static page layout in JS so we don't need a .tpl/.view.php pair.
     * Uses the modern bga.gameArea().getElement() entry point.
     */
    buildPageLayout: function () {
      var html = ''
        + '<div id="hns_wrap">'
        +   '<aside id="hns_monster_panel" class="whiteblock hns_panel">'
        +     '<h3>' + _('Monsters') + '</h3>'
        +     '<div id="hns_monster_cards" class="hns_monster_cards"></div>'
        +   '</aside>'
        +   '<div id="hns_center">'
        +     '<div id="hns_main" class="whiteblock hns_panel">'
        +       '<div class="hns_board_header">'
        +         '<h3>' + _('Dungeon') + '</h3>'
        +         '<div id="hns_board_hint">' + _('Select a card, then choose a target on the board.') + '</div>'
        +       '</div>'
        +       '<div id="hns_power_confirm" class="hns_power_confirm hns_hidden">'
        +         '<span id="hns_power_confirm_text"></span>'
        +         '<button type="button" id="hns_power_validate">' + _('Validate') + '</button>'
        +         '<button type="button" id="hns_power_cancel">' + _('Cancel') + '</button>'
        +       '</div>'
        +       '<div id="hns_board"></div>'
        +     '</div>'
        +     '<div id="hns_events" class="whiteblock hns_panel">'
        +       '<h3>' + _('Events') + '</h3>'
        +       '<div id="hns_event_list" class="hns_event_list">'
        +         '<div class="hns_event hns_event_empty">' + _('Events will appear here.') + '</div>'
        +       '</div>'
        +     '</div>'
        +     '<div id="hns_reward_panel" class="whiteblock hns_panel hns_reward_panel">'
        +       '<h3>' + _('Reward') + '</h3>'
        +       '<div id="hns_reward_cards"></div>'
        +     '</div>'
        +   '</div>'
        +   '<aside id="hns_side" class="hns_panel_stack">'
        +     '<div id="hns_status" class="whiteblock hns_panel hns_hero_panel hns_active_hero_panel">'
        +       '<h3>' + _('Active hero') + '</h3>'
        +       '<div id="hns_active_hero" class="hns_hero_card"></div>'
        +     '</div>'
        +     '<div id="hns_hand" class="whiteblock hns_panel">'
        +       '<h3>' + _('Hero cards') + '</h3>'
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
      }
    },

    setup: function (gamedatas) {
      this.gamedatas = gamedatas;
      this.eventMessages = this.loadEventMessages();
      this.rewardOffer = gamedatas.reward_offer || [];
      this.rewardUpgrades = gamedatas.reward_upgrades || [];
      this.buildPageLayout();
      this.renderBoard(gamedatas.tiles, gamedatas.entities);
      this.scheduleFreeMoveHighlight();
      this.renderMonsterCards();
      this.renderHeroPanels();
      this.renderPowerCards();
      this.renderRewardOffer();
      this.renderEventMessages();
      this.connect($('hns_power_validate'), 'onclick', 'onValidatePowerSelection');
      this.connect($('hns_power_cancel'), 'onclick', 'onCancelPowerSelection');
      this.setupNotifications();
      this.updateRewardFocusState();
    },

    renderBoard: function (tiles, entities) {
      dojo.empty('hns_board');
      this.resizeBoardToTiles(tiles);

      // Index tiles by "x,y" for neighbour lookup when picking wall variants.
      var tileGrid = {};
      for (var tIdx in tiles) {
        var t = tiles[tIdx];
        tileGrid[t.x + ',' + t.y] = t;
      }

      for (var tileId in tiles) {
        var tile = tiles[tileId];
        var box = this.tileBox(tile, tiles);
        dojo.place(this.format_block('jstpl_hns_tile', {
          id: tile.id,
          type: tile.type,
          left: box.left,
          top: box.top,
          width: box.width,
          height: box.height,
          image: this.getTileImage(tile, tileGrid)
        }), 'hns_board');
        this.connect($('hns_tile_' + tile.id), 'onclick', 'onTileClick');
      }

      for (var entityId in entities) {
        this.placeEntity(entities[entityId], tiles);
      }
    },

    resizeBoardToTiles: function (tiles) {
      var bounds = this.tileBounds(tiles);

      dojo.style('hns_board', {
        width: this.boardAxisOffset(bounds.maxX + 1, bounds.maxX) + 'px',
        height: this.boardAxisOffset(bounds.maxY + 1, bounds.maxY) + 'px'
      });
    },

    tileBox: function (tile, tiles) {
      var bounds = this.tileBounds(tiles || this.gamedatas.tiles || {});
      var x = parseInt(tile.x || 0, 10);
      var y = parseInt(tile.y || 0, 10);
      return {
        left: this.boardAxisOffset(x, bounds.maxX),
        top: this.boardAxisOffset(y, bounds.maxY),
        width: (x === 0 || x === bounds.maxX) ? this.boardBorderTileSize : this.boardTileSize,
        height: (y === 0 || y === bounds.maxY) ? this.boardBorderTileSize : this.boardTileSize
      };
    },

    boardAxisOffset: function (index, maxIndex) {
      if (index <= 0) {
        return 0;
      }
      if (index >= maxIndex + 1) {
        return (Math.max(0, maxIndex - 1) * this.boardTileSize) + (2 * this.boardBorderTileSize);
      }
      return this.boardBorderTileSize + ((index - 1) * this.boardTileSize);
    },

    tileBounds: function (tiles) {
      var maxX = 0;
      var maxY = 0;
      for (var tileId in tiles) {
        maxX = Math.max(maxX, parseInt(tiles[tileId].x || 0, 10));
        maxY = Math.max(maxY, parseInt(tiles[tileId].y || 0, 10));
      }
      return { maxX: maxX, maxY: maxY };
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
        state_class: entity.state === 'dead' || parseInt(entity.health || 0, 10) <= 0 ? 'hns_entity_dead' : '',
        monster_key: entityInfo.monsterKey,
        image: entityInfo.image,
        label: entityInfo.label,
        health: entity.health || ''
      }), 'hns_board');

      var node = $('hns_entity_' + entity.id);
      var box = this.tileBox(tile, tiles);
      dojo.style(node, {
        left: (box.left + (box.width / 2)) + 'px',
        top: (box.top + (box.height / 2)) + 'px'
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
        if (info.upgrades_to) {
          badges += '<button type="button" id="hns_upgrade_power_' + power.id + '" class="hns_upgrade_power" data-slot="' + power.slot + '">' + _('Upgrade') + '</button>';
        }

        dojo.place(this.format_block('jstpl_hns_power_card', {
          key: power.id || power.slot || power.power_key,
          power_key: power.power_key,
          slot: power.slot || '',
          name: info.name,
          image: this.getPowerCardImage(power.power_key),
          classes: classes,
          cooldown_overlay: cooldown > 0 ? cooldown : '',
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
          image: this.getPowerCardImage(powerKey),
          classes: 'hns_reward_offer' + (this.selectedRewardPowerKey === powerKey ? ' hns_selected' : ''),
          cooldown_overlay: '',
          badges: '<span class="hns_power_badge hns_power_badge_free">NEW</span>'
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
          image: this.getPowerCardImage(upgrade.to),
          classes: 'hns_reward_upgrade',
          cooldown_overlay: '',
          badges: '<span class="hns_power_badge hns_power_badge_free">UPGRADE</span>'
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

      if (this.selectedPowerKey) {
        if (this.requiresConfirmTargets()) {
          this.selectedPowerTileId = tileId;
          this.selectedPowerTargetEntityIds = [];
          this.highlightPowerTargets();
          this.updatePowerConfirmControls();
          return;
        }
        var payload = { target_tile_id: tileId, selected_tile_id: tileId };
        var power = this.getPowerInfo(this.selectedPowerKey);
        if (power.effect === 'attack') {
          var targetEntityId = this.monsterIdOnTile(tileId);
          if (targetEntityId !== null) {
            payload.target_entity_id = targetEntityId;
          }
        }
        this.playSelectedPower(payload);
        return;
      }

      var tile = this.gamedatas.tiles && this.gamedatas.tiles[tileId];
      if (this.checkAction('actMove') && tile && this.isWalkableTile(tile) && !this.isTileOccupied(tile.id) && !this.isHeroHeldByAdjacentSlime()) {
        this.bgaPerformAction('actMove', { tile_id: tileId });
      }
    },

    onEntityClick: function (evt) {
      dojo.stopEvent(evt);
      var entityId = evt.currentTarget.getAttribute('data-entity-id');
      if (this.selectedPowerKey) {
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
        this.playSelectedPower({ target_entity_id: entityId });
        return;
      }

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
      var slot = evt.currentTarget.getAttribute('data-slot');
      if (this.selectedRewardPowerKey) {
        var power = this.getPowerForCurrentPlayer(powerKey, slot);
        if (power && this.checkAction('actChooseReward')) {
          this.bgaPerformAction('actChooseReward', { mode: 'replace', slot: power.slot, power_key: this.selectedRewardPowerKey });
        }
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
      this.selectedRewardPowerKey = evt.currentTarget.getAttribute('data-power-key').replace(/^reward_/, '');
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
      dojo.stopEvent(evt);
      var slot = evt.currentTarget.getAttribute('data-slot');
      if (this.checkAction('actChooseReward')) {
        this.bgaPerformAction('actChooseReward', { mode: 'upgrade', slot: slot, power_key: '' });
      }
    },

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

    onEnteringState: function (stateName, args) {
      this.renderHeroPanels();
      this.renderPowerCards();
      this.updateRewardFocusState(stateName === 'upgradeReward');
      if (stateName === 'playerTurn') {
        this.highlightFreeMoveTiles(args || (this.gamedatas.gamestate && this.gamedatas.gamestate.args) || {});
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
        this.addActionButton('hns_end_turn_button', _('End turn'), 'onEndTurn');
      }
    },

    onSkipReward: function () {
      if (this.checkAction('actSkipReward')) {
        this.bgaPerformAction('actSkipReward');
      }
    },

    onSkipFreeMove: function () {
      if (this.checkAction('actSkipFreeMove')) {
        this.bgaPerformAction('actSkipFreeMove');
      }
    },

    onSkipMainAction: function () {
      if (this.checkAction('actSkipMainAction')) {
        this.bgaPerformAction('actSkipMainAction');
      }
    },

    onEndTurn: function () {
      if (this.checkAction('actEndTurn')) {
        this.bgaPerformAction('actEndTurn');
      }
    },

    setupNotifications: function () {
      dojo.subscribe('heroMoved', this, 'notif_heroMoved');
      dojo.subscribe('rewardChosen', this, 'notif_rewardChosen');
      dojo.subscribe('playerActionState', this, 'notif_playerActionState');
      dojo.subscribe('powerCooldownsUpdated', this, 'notif_powerCooldownsUpdated');
      dojo.subscribe('roundStarted', this, 'notif_roundStarted');
      var eventTypes = [
        'cardPlayed',
        'afterCardPlayed',
        'afterDash',
        'afterKill',
        'afterPushOrPull',
        'entityDamaged',
        'thornsDamage',
        'shieldBroken',
        'monsterAttack',
        'monsterStick',
        'monsterCharge',
        'monsterFrontArcAttack',
        'monsterMove',
        'monsterSummon',
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
      this.setupSynchronousNotifications();
    },

    setupSynchronousNotifications: function () {
      if (!this.notifqueue || !this.notifqueue.setSynchronous) {
        return;
      }

      var animatedEvents = [
        'heroMoved',
        'afterDash',
        'afterPushOrPull',
        'monsterAttack',
        'monsterStick',
        'monsterCharge',
        'monsterFrontArcAttack',
        'monsterMove',
        'monsterSummon',
        'monsterExplode'
      ];
      for (var i = 0; i < animatedEvents.length; i++) {
        this.notifqueue.setSynchronous(animatedEvents[i], 620);
      }
    },

    notif_heroMoved: function (notif) {
      var entityId = notif.args.entity_id || notif.args.player_id;
      var tileId = notif.args.tile_id;
      this.updatePlayerActionState(notif.args || {});
      this.moveEntityNode(entityId, tileId, true);
      this.pushEvent(_('Hero moves.'), 'effect');
      this.renderHeroPanels();
      this.renderPowerCards();
    },

    notif_engineEvent: function (notif) {
      if (!notif.args) {
        return;
      }
      this.pushEvent(this.describeEngineEvent(notif.args), this.getEventVisualType(notif.args));
      this.refreshFromEvent(notif.args);
    },

    notif_rewardChosen: function (notif) {
      this.rewardOffer = [];
      this.rewardUpgrades = [];
      this.selectedRewardPowerKey = null;
      this.updatePowerFromReward(notif.args || {});
      this.pushEvent(_('Reward chosen.'), 'effect');
      this.renderPowerCards();
      this.renderRewardOffer();
    },

    notif_playerActionState: function (notif) {
      var args = notif.args || {};
      this.updatePlayerActionState(args);
      this.renderHeroPanels();
      this.renderPowerCards();
      this.scheduleFreeMoveHighlight();
    },

    notif_powerCooldownsUpdated: function (notif) {
      var args = notif.args || {};
      if (args.player_powers) {
        this.gamedatas.player_powers = args.player_powers;
      }
      this.gamedatas.free_action_events = args.free_action_events || [];
      this.renderPowerCards();
      this.renderHeroPanels();
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
      this.renderHeroPanels();
      this.renderPowerCards();
      this.scheduleFreeMoveHighlight();
    },

    moveEntityNode: function (entityId, tileId, animate) {
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

      var fromPosition = animate ? this.entityNodePosition(entityNode) : null;
      var box = this.tileBox(tile, this.gamedatas.tiles || {});
      var toPosition = {
        left: box.left + (box.width / 2),
        top: box.top + (box.height / 2)
      };

      if (animate && fromPosition) {
        this.animateEntityMove(entityNode, fromPosition, toPosition);
      } else {
        dojo.style(entityNode, {
          left: toPosition.left + 'px',
          top: toPosition.top + 'px'
        });
      }

      if (this.gamedatas.entities && this.gamedatas.entities[entityId]) {
        this.gamedatas.entities[entityId].tile_id = tileId;
      }

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

      dojo.style(entityNode, {
        left: fromPosition.left + 'px',
        top: fromPosition.top + 'px'
      });
      dojo.removeClass(entityNode, 'hns_entity_moving');
      void entityNode.offsetWidth;
      dojo.addClass(entityNode, 'hns_entity_moving');
      dojo.animateProperty({
        node: entityNode,
        duration: 520,
        properties: {
          left: { start: fromPosition.left, end: toPosition.left, units: 'px' },
          top: { start: fromPosition.top, end: toPosition.top, units: 'px' }
        },
        onEnd: function () {
          dojo.style(entityNode, {
            left: toPosition.left + 'px',
            top: toPosition.top + 'px'
          });
          dojo.removeClass(entityNode, 'hns_entity_moving');
        }
      }).play();
      window.setTimeout(function () {
        dojo.removeClass(entityNode, 'hns_entity_moving');
      }, 520);
    },

    highlightFreeMoveTiles: function (args) {
      this.clearFreeMoveHighlights();
      args = args || (this.gamedatas && this.gamedatas.gamestate && this.gamedatas.gamestate.args) || {};
      if (!this.canActivePlayerMove(args)) {
        return;
      }

      var hero = this.getHeroEntityForPlayer(this.getActivePlayerId());
      if (this.isHeroHeldByAdjacentSlime(hero)) {
        return;
      }
      var tiles = this.gamedatas.tiles || {};
      var from = hero && tiles[hero.tile_id];
      if (!from) {
        return;
      }

      for (var tileId in tiles) {
        if (this.isFreeMoveTile(from, tiles[tileId])) {
          dojo.addClass('hns_tile_' + tileId, 'hns_free_move_tile');
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

    isHeroHeldByAdjacentSlime: function (hero) {
      hero = hero || this.getHeroEntityForPlayer(this.getActivePlayerId());
      if (!hero || !this.hasSlimeStatus(hero.status)) {
        return false;
      }

      var heroTile = this.gamedatas.tiles && this.gamedatas.tiles[hero.tile_id];
      var entities = this.gamedatas.entities || {};
      if (!heroTile) {
        return false;
      }

      for (var entityId in entities) {
        var entity = entities[entityId];
        if (entity.type !== 'monster' || parseInt(entity.type_arg || 0, 10) !== 2 || (entity.state || 'active') !== 'active') {
          continue;
        }

        var slimeTile = this.gamedatas.tiles && this.gamedatas.tiles[entity.tile_id];
        if (slimeTile && this.powerDistance(heroTile, slimeTile, 'orthogonal') === 1) {
          return true;
        }
      }

      return false;
    },

    hasSlimeStatus: function (status) {
      return /(^|\s)(slimed|stuck|stick)(\s|$)/.test(String(status || ''));
    },

    clearFreeMoveHighlights: function () {
      dojo.query('.hns_free_move_tile').removeClass('hns_free_move_tile');
    },

    highlightPowerTargets: function () {
      this.clearPowerHighlights();
      var power = this.getPowerInfo(this.selectedPowerKey);
      var hero = this.getHeroEntityForPlayer(this.getActivePlayerId());
      var tiles = this.gamedatas.tiles || {};
      var from = hero && tiles[hero.tile_id];
      if (!power || !from) {
        return;
      }

      for (var tileId in tiles) {
        var tile = tiles[tileId];
        if (this.isTileValidPowerTarget(from, tile, power)) {
          dojo.addClass('hns_tile_' + tileId, 'hns_power_target_tile');
        }
      }

      var entities = this.gamedatas.entities || {};
      for (var entityId in entities) {
        var entity = entities[entityId];
        if (entity.type === 'monster' && (entity.state || 'active') === 'active' && this.isEntityTargetableBySelectedPower(from, entity, power)) {
          dojo.addClass('hns_entity_' + entityId, 'hns_power_target_entity');
        }
      }
    },

    clearPowerHighlights: function () {
      dojo.query('.hns_power_target_tile').removeClass('hns_power_target_tile');
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
        if (this.isTileInMonsterAttackRange(from, tiles[tileId], monster)) {
          dojo.addClass('hns_tile_' + tileId, 'hns_monster_attack_tile');
        }
      }
    },

    clearMonsterAttackHighlights: function () {
      dojo.query('.hns_monster_attack_tile').removeClass('hns_monster_attack_tile');
    },

    isTileInMonsterAttackRange: function (from, tile, monster) {
      var maxRange = parseInt(monster.range || 0, 10);
      var minRange = typeof monster.min_range !== 'undefined' ? parseInt(monster.min_range || 0, 10) : (maxRange > 0 ? 1 : 0);
      var metric = monster.range_metric || 'orthogonal';

      if (metric === 'front_arc') {
        return this.isTileInMonsterFrontArc(from, tile);
      }

      if (metric === 'orthogonal' && String(from.x) !== String(tile.x) && String(from.y) !== String(tile.y)) {
        return false;
      }

      var distance = this.powerDistance(from, tile, metric);
      return distance >= minRange && distance <= maxRange;
    },

    isTileInMonsterFrontArc: function (from, tile) {
      var dx = parseInt(tile.x, 10) - parseInt(from.x, 10);
      var dy = parseInt(tile.y, 10) - parseInt(from.y, 10);
      return (dx === 1 && Math.abs(dy) <= 1) || (dx === -1 && Math.abs(dy) <= 1) || (dy === 1 && Math.abs(dx) <= 1) || (dy === -1 && Math.abs(dx) <= 1);
    },

    isEntityTargetableBySelectedPower: function (from, entity, power) {
      if (power.effect !== 'pull') {
        return this.isEntityInPowerRange(from, entity, power);
      }
      if (!this.selectedPowerTileId) {
        return false;
      }
      var selectedTile = this.gamedatas.tiles && this.gamedatas.tiles[this.selectedPowerTileId];
      var entityTile = this.gamedatas.tiles && this.gamedatas.tiles[entity.tile_id];
      return !!selectedTile && !!entityTile && this.powerDistance(selectedTile, entityTile, 'chebyshev') === 1;
    },

    isTileValidPowerTarget: function (from, tile, power) {
      if (!this.isTileInPowerRange(from, tile, power)) {
        return false;
      }

      if (power.effect === 'dash') {
        return this.isWalkableTile(tile) && !this.isTileOccupied(tile.id);
      }

      if (power.effect === 'attack') {
        return this.monsterIdOnTile(tile.id) !== null;
      }

      if (power.effect === 'pull') {
        return this.monsterIdsAdjacentToTile(tile.id).length > 0;
      }

      return true;
    },

    isSelectedPowerPull: function () {
      return this.selectedPowerKey && this.getPowerInfo(this.selectedPowerKey).effect === 'pull';
    },

    isSelectedPowerMultiAttack: function () {
      var power = this.getPowerInfo(this.selectedPowerKey);
      return this.selectedPowerKey && power.effect === 'attack' && parseInt(power.targets || 1, 10) > 1;
    },

    requiresConfirmTargets: function () {
      return this.isSelectedPowerPull() || this.isSelectedPowerMultiAttack();
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
      var adjacentIds = this.monsterIdsAdjacentToTile(this.selectedPowerTileId);
      var maxTargets = parseInt(this.getPowerInfo(this.selectedPowerKey).targets || 1, 10);
      var requiredTargets = Math.min(maxTargets, adjacentIds.length);
      if (requiredTargets === 0 || (this.selectedPowerTargetEntityIds || []).length >= requiredTargets) {
        this.playSelectedPower({
          selected_tile_id: this.selectedPowerTileId,
          target_tile_id: this.selectedPowerTileId,
          target_entity_ids: (this.selectedPowerTargetEntityIds || adjacentIds).slice(0, requiredTargets).join(' ')
        });
      }
    },

    onValidatePowerSelection: function (evt) {
      dojo.stopEvent(evt);
      this.validatePowerSelection();
    },

    onCancelPowerSelection: function (evt) {
      dojo.stopEvent(evt);
      this.clearSelectedPower();
    },

    validatePowerSelection: function () {
      if (!this.requiresConfirmTargets() || !this.selectedPowerTileId) {
        return;
      }

      var selectedIds = this.selectedPowerTargetEntityIds || [];
      if (selectedIds.length === 0) {
        return;
      }

      this.playSelectedPower({
        selected_tile_id: this.selectedPowerTileId,
        target_tile_id: this.selectedPowerTileId,
        target_entity_ids: selectedIds.join(' ')
      });
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
        validateButton.disabled = selectedCount === 0;
      }
    },

    isPullTargetAdjacent: function (entityId) {
      var adjacentIds = this.monsterIdsAdjacentToTile(this.selectedPowerTileId);
      return adjacentIds.indexOf(String(entityId)) !== -1;
    },

    isConfirmTargetValid: function (entityId) {
      return this.confirmTargetIdsForSelectedTile().indexOf(String(entityId)) !== -1;
    },

    confirmTargetIdsForSelectedTile: function () {
      if (this.isSelectedPowerPull()) {
        return this.monsterIdsAdjacentToTile(this.selectedPowerTileId);
      }

      var entity = this.gamedatas.entities && this.gamedatas.entities[this.selectedPowerTargetEntityIds && this.selectedPowerTargetEntityIds[0]];
      var power = this.getPowerInfo(this.selectedPowerKey);
      var hero = this.getHeroEntityForPlayer(this.getActivePlayerId());
      var from = hero && this.gamedatas.tiles && this.gamedatas.tiles[hero.tile_id];
      var ids = [];
      if (!from || !power) {
        return ids;
      }

      var entities = this.gamedatas.entities || {};
      for (var entityId in entities) {
        entity = entities[entityId];
        if ((entity.type !== 'monster' && entity.type !== 'boss') || (entity.state || 'active') !== 'active') {
          continue;
        }
        if (this.isEntityInPowerRange(from, entity, power)) {
          ids.push(String(entityId));
        }
      }
      return ids;
    },

    isFreeMoveTile: function (from, tile) {
      var distance = Math.abs(parseInt(from.x, 10) - parseInt(tile.x, 10)) + Math.abs(parseInt(from.y, 10) - parseInt(tile.y, 10));
      if (distance !== 1 || !this.isWalkableTile(tile)) {
        return false;
      }

      return !this.isTileOccupied(tile.id);
    },

    isWalkableTile: function (tile) {
      return ['floor', 'spikes'].indexOf(tile.type) !== -1;
    },

    isTileOccupied: function (tileId) {
      var entities = this.gamedatas.entities || {};
      for (var entityId in entities) {
        var entity = entities[entityId];
        if ((entity.state || 'active') !== 'active') {
          continue;
        }
        if (String(entity.tile_id) === String(tileId)) {
          return true;
        }
      }
      return false;
    },

    monsterIdOnTile: function (tileId) {
      var entities = this.gamedatas.entities || {};
      for (var entityId in entities) {
        var entity = entities[entityId];
        if ((entity.type !== 'monster' && entity.type !== 'boss') || (entity.state || 'active') !== 'active') {
          continue;
        }
        if (String(entity.tile_id) === String(tileId)) {
          return entityId;
        }
      }
      return null;
    },

    isTileInPowerRange: function (from, tile, power) {
      var range = power.range || power.distance || [0, 0];
      if ((power.range_metric || 'orthogonal') === 'orthogonal' && String(from.x) !== String(tile.x) && String(from.y) !== String(tile.y)) {
        return false;
      }
      var distance = this.powerDistance(from, tile, power.range_metric || 'orthogonal');
      return distance >= parseInt(range[0] || 0, 10) && distance <= parseInt(range[1] || range[0] || 0, 10);
    },

    isEntityInPowerRange: function (from, entity, power) {
      var tile = this.gamedatas.tiles && this.gamedatas.tiles[entity.tile_id];
      return !!tile && this.isTileInPowerRange(from, tile, power);
    },

    powerDistance: function (from, to, metric) {
      var dx = Math.abs(parseInt(from.x, 10) - parseInt(to.x, 10));
      var dy = Math.abs(parseInt(from.y, 10) - parseInt(to.y, 10));
      return metric === 'chebyshev' ? Math.max(dx, dy) : dx + dy;
    },

    monsterIdsAdjacentToTile: function (tileId) {
      var selectedTile = this.gamedatas.tiles && this.gamedatas.tiles[tileId];
      var entities = this.gamedatas.entities || {};
      var ids = [];
      if (!selectedTile) {
        return ids;
      }

      for (var entityId in entities) {
        var entity = entities[entityId];
        var entityTile = this.gamedatas.tiles && this.gamedatas.tiles[entity.tile_id];
        if ((entity.type !== 'monster' && entity.type !== 'boss') || (entity.state || 'active') !== 'active' || !entityTile) {
          continue;
        }
        if (this.powerDistance(selectedTile, entityTile, 'chebyshev') === 1) {
          ids.push(entityId);
        }
      }
      return ids;
    },

    pushEvent: function (message, type) {
      this.eventMessages.unshift({ message: message, type: type || 'effect' });
      this.eventMessages = this.eventMessages.slice(0, 3);
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
        return Array.isArray(messages) ? messages.slice(0, 3) : [];
      } catch (e) {
        return [];
      }
    },

    saveEventMessages: function () {
      try {
        localStorage.setItem(this.eventStorageKey(), JSON.stringify(this.eventMessages.slice(0, 3)));
      } catch (e) {
        // Ignore storage failures; the in-memory log still works.
      }
    },

    refreshFromEvent: function (event) {
      this.updatePlayerActionState(event);
      if (event.type === 'levelCleared') {
        this.rewardOffer = event.reward_offer || [];
        this.rewardUpgrades = event.reward_upgrades || [];
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
      if (['monsterAttack', 'monsterStick', 'monsterCharge', 'monsterFrontArcAttack', 'monsterSummon'].indexOf(event.type) !== -1) {
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
      this.renderMonsterCards();
      this.renderHeroPanels();
      this.renderPowerCards();
      this.renderRewardOffer();
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
        left: fromPosition.left + (((targetBox.left + targetBox.width / 2) - (sourceBox.left + sourceBox.width / 2)) * 0.42),
        top: fromPosition.top + (((targetBox.top + targetBox.height / 2) - (sourceBox.top + sourceBox.height / 2)) * 0.42)
      };
      dojo.animateProperty({
        node: sourceNode,
        duration: 170,
        properties: {
          left: { start: fromPosition.left, end: lungePosition.left, units: 'px' },
          top: { start: fromPosition.top, end: lungePosition.top, units: 'px' }
        },
        onEnd: function () {
          dojo.animateProperty({
            node: sourceNode,
            duration: 230,
            properties: {
              left: { start: lungePosition.left, end: fromPosition.left, units: 'px' },
              top: { start: lungePosition.top, end: fromPosition.top, units: 'px' }
            },
            onEnd: function () {
              dojo.style(sourceNode, {
                left: fromPosition.left + 'px',
                top: fromPosition.top + 'px'
              });
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
        }, 520, node);
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
      if (event.level_monster_abilities) {
        this.gamedatas.level_monster_abilities = event.level_monster_abilities;
      }

      this.selectedEntityId = null;
      this.clearFreeMoveHighlights();
      this.renderBoard(this.gamedatas.tiles, this.gamedatas.entities);
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
      var entities = this.gamedatas.entities || {};
      for (var entityId in entities) {
        var entity = entities[entityId];
        if (entity.type !== 'hero' || !this.hasSlimeStatus(entity.status) || this.isHeroHeldByAdjacentSlime(entity)) {
          continue;
        }

        entity.status = '';
      }
    },

    getVisibleMonsterGroups: function () {
      var groups = {};
      var entities = this.gamedatas.entities || {};
      for (var entityId in entities) {
        var entity = entities[entityId];
        if (entity.type !== 'monster' && entity.type !== 'boss') {
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

    renderMonsterEffectIcons: function (effects) {
      var html = '';
      if (effects.shield) {
        html += '<img class="hns_effect_icon" src="' + this.getAssetUrl('tiles/markers/shield.webp') + '" alt="Shield" />';
      }
      if (effects.thorns) {
        html += '<img class="hns_effect_icon" src="' + this.getAssetUrl('cards/monsters/thorns.webp') + '" alt="Thorns" />';
      }
      return html;
    },

    renderHeroEffects: function (player) {
      var effects = [];
      var hero = this.getHeroEntityForPlayer(player.id);
      var status = hero ? String(hero.status || '') : '';
      if (status.indexOf('slimed') !== -1 || status.indexOf('stuck') !== -1 || status.indexOf('stick') !== -1) {
        effects.push('<img class="hns_effect_icon" src="' + this.getAssetUrl('tiles/markers/slimed.webp') + '" alt="Slimed" title="Slimed" />');
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

    getPowerForCurrentPlayer: function (powerKey, slot) {
      var activePlayerId = this.getActivePlayerId();
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

    getPowerInfo: function (powerKey) {
      var bonusCards = this.gamedatas.bonus_cards || {};
      return bonusCards[powerKey] || { name: powerKey };
    },

    isPowerFree: function (powerKey, playerId) {
      var info = this.getPowerInfo(powerKey);
      var events = this.gamedatas.free_action_events || [];
      if (!info.free_triggers || info.free_triggers.length === 0) {
        return false;
      }
      for (var i = 0; i < info.free_triggers.length; i++) {
        if (events.indexOf(info.free_triggers[i]) !== -1) {
          return true;
        }
      }
      return false;
    },

    getMaxActionPoints: function () {
      return this.getPlayerCount() === 1 ? 2 : 1;
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
          image: this.getAssetUrl('tiles/markers/checker-white.webp')
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
          image: this.getBossTileImage(bossKey)
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
        1: 'goblin',
        2: 'slimes',
        3: 'evil-eye',
        4: 'kamikaze',
        5: 'wizard',
        6: 'bomber',
        7: 'orc',
        8: 'pig-rider',
        9: 'wolf-rider'
      };
      return keys[typeArg] || 'goblin';
    },

    /**
     * Pick the icon used as background for a level tile, taken from
     * img/tiles/levels/. Walls receive a directional variant based on the
     * neighbouring tiles, floors get a deterministic variation between
     * floor.webp / floor-1 / floor-2 / floor-3.
     */
    getTileImage: function (tile, tileGrid) {
      var type = tile.type;

      if (type === 'entry') {
        return this.getAssetUrl('tiles/levels/entrance.webp');
      }
      if (type === 'exit') {
        return this.getAssetUrl('tiles/levels/exit.webp');
      }
      if (type === 'pillar') {
        return this.getAssetUrl('tiles/levels/pillar.webp');
      }
      if (type === 'hole') {
        return this.getAssetUrl('tiles/levels/hole.webp');
      }
      if (type === 'spikes') {
        return this.getAssetUrl('tiles/levels/spikes.webp');
      }
      if (type === 'wall') {
        return this.getAssetUrl(this.pickWallVariant(tile, tileGrid || {}));
      }

      // Default = floor: pick a stable variant per coordinate so the floor
      // does not change on every redraw.
      var variants = [
        'tiles/levels/floor.webp',
        'tiles/levels/floor-1.webp',
        'tiles/levels/floor-2.webp',
        'tiles/levels/floor-3.webp'
      ];
      var idx = Math.abs(((tile.x | 0) * 31 + (tile.y | 0) * 17)) % variants.length;
      return this.getAssetUrl(variants[idx]);
    },

    /**
     * Choose the wall asset that matches the surrounding floor/non-wall tiles.
     * Available assets:
     *   wall-top, wall-bottom, wall-left, wall-right, wall--left-right,
     *   wall-top-left, wall-top-right, wall-bottom-left, wall-bottom-right
     */
    pickWallVariant: function (tile, tileGrid) {
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

      if (open.top && open.left) {
        return 'tiles/levels/wall-top-left.webp';
      }
      if (open.top && open.right) {
        return 'tiles/levels/wall-top-right.webp';
      }
      if (open.bottom && open.left) {
        return 'tiles/levels/wall-bottom-left.webp';
      }
      if (open.bottom && open.right) {
        return 'tiles/levels/wall-bottom-right.webp';
      }
      if (open.left && open.right) {
        return 'tiles/levels/wall--left-right.webp';
      }
      if (open.top) {
        return 'tiles/levels/wall-top.webp';
      }
      if (open.bottom) {
        return 'tiles/levels/wall-bottom.webp';
      }
      if (open.left) {
        return 'tiles/levels/wall-left.webp';
      }
      if (open.right) {
        return 'tiles/levels/wall-right.webp';
      }
      // Fallback: solid wall, reuse top variant.
      return 'tiles/levels/wall-top.webp';
    },

    getMonsterTileImage: function (monsterKey) {
      var files = {
        'gobelins': 'tiles/monsters/goblin-pixel.webp',
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
      return this.getAssetUrl(files[monsterKey] || 'tiles/monsters/goblin-pixel.webp');
    },

    getBossTileImage: function (bossKey) {
      var files = {
        'slasher': 'tiles/monsters/slasher-pixel.webp'
      };
      return this.getAssetUrl(files[bossKey] || files.slasher);
    },

    getMonsterCardImage: function (monsterKey) {
      if (monsterKey.indexOf('slasher-') === 0) {
        return this.getBossCardImage('slasher', parseInt(monsterKey.replace('slasher-', ''), 10));
      }

      var files = {
        'gobelins': 'cards/monsters/gobelins.webp',
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
      return this.getAssetUrl(files[monsterKey] || 'cards/monsters/gobelins.webp');
    },

    getBossCardImage: function (bossKey, phase) {
      var files = {
        'slasher': {
          1: 'cards/monsters/slasher-1.webp',
          2: 'cards/monsters/slasher-2.webp',
          3: 'cards/monsters/slasher-3.webp'
        }
      };
      var phases = files[bossKey] || files.slasher;
      return this.getAssetUrl(phases[phase] || phases[1]);
    },

    getPowerCardImage: function (powerKey) {
      var files = {
        'attack': 'cards/powers/attack-0.webp',
        'strike': 'cards/powers/strike-0.webp',
        'dash_1': 'cards/powers/dash-1.webp',
        'dash_2': 'cards/powers/dash-2.webp',
        'dash_3': 'cards/powers/dash-3.webp',
        'dash-attack_1': 'cards/powers/dash-attack-1.webp',
        'dash-attack_2': 'cards/powers/dash-attack-2.webp',
        'dash-attack_3': 'cards/powers/dash-attack-3.webp',
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
        'point-blank_1': 'cards/powers/point-blank-1.webp',
        'point-blank_2': 'cards/powers/point-blank-2.webp',
        'point-blank_3': 'cards/powers/point-blank-3.webp',
        'power-strike_1': 'cards/powers/power-strike-1.webp',
        'power-strike_2': 'cards/powers/power-strike-2.webp',
        'power-strike_3': 'cards/powers/power-strike-3.webp',
        'quick-shot_1': 'cards/powers/quick-shot-1.webp',
        'quick-shot_2': 'cards/powers/quick-shot-2.webp',
        'quick-shot_3': 'cards/powers/quick-shot-3.webp',
        'quick-strike_1': 'cards/powers/quick-strike-1.png',
        'quick-strike_2': 'cards/powers/quick-strike-2.webp',
        'quick-strike_3': 'cards/powers/quick-strike-3.webp',
        'reenforce_1': 'cards/powers/reenforce-1.webp',
        'reenforce_2': 'cards/powers/reenforce-2.webp',
        'reenforce_3': 'cards/powers/reenforce-3.webp',
        'vortex': 'cards/powers/vortex-1.webp',
        'vortex_1': 'cards/powers/vortex-1.webp',
        'vortex_2': 'cards/powers/vortex-2.webp',
        'vortex_3': 'cards/powers/vortex-3.webp',
        'whirlwind_1': 'cards/powers/whirlwind-1.webp',
        'whirlwind_2': 'cards/powers/whirlwind-2.webp',
        'whirlwind_3': 'cards/powers/whirlwind-3.webp'
      };
      return this.getAssetUrl(files[powerKey] || 'cards/powers/attack-0.webp');
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
