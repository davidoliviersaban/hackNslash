define([
  'dojo',
  'dojo/_base/declare',
  'ebg/core/gamegui',
  'ebg/counter'
], function (dojo, declare) {
  return declare('bgagame.hacknslash', ebg.core.gamegui, {
    constructor: function () {
      this.actionPointsCounter = new ebg.counter();
    },

    setup: function (gamedatas) {
      this.gamedatas = gamedatas;
      this.actionPointsCounter.create('hns_action_points');
      this.renderBoard(gamedatas.tiles, gamedatas.entities);
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

      dojo.place(this.format_block('jstpl_hns_entity', {
        id: entity.id,
        type: entity.type
      }), 'hns_board');

      dojo.style('hns_entity_' + entity.id, {
        left: (tile.x * 96 + 56) + 'px',
        top: (tile.y * 96 + 56) + 'px'
      });
    },

    onTileClick: function (evt) {
      dojo.stopEvent(evt);
      var tileId = evt.currentTarget.getAttribute('data-tile-id');

      if (this.checkAction('actMove')) {
        this.bgaPerformAction('actMove', { tile_id: tileId });
      }
    },

    onEnteringState: function (stateName, args) {
      if (stateName === 'playerTurn' && args.args) {
        this.actionPointsCounter.setValue(args.args.action_points || 0);
      }
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
    },

    notif_heroMoved: function () {
      this.ajaxcall('/hacknslash/hacknslash/reload.html', {}, this, function () {}, function () {});
    }
  });
});
