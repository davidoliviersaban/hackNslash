<div id="hns_wrap">
  <div id="hns_main" class="whiteblock">
    <h3>{BOARD_TITLE}</h3>
    <div id="hns_board"></div>
  </div>

  <div id="hns_side">
    <div id="hns_status" class="whiteblock">
      <h3>{STATUS_TITLE}</h3>
      <div id="hns_action_points"></div>
    </div>

    <div id="hns_hand" class="whiteblock">
      <h3>{HAND_TITLE}</h3>
      <div id="hns_cards"></div>
    </div>
  </div>
</div>

<script type="text/javascript">
var jstpl_hns_tile = '<div id="hns_tile_${id}" class="hns_tile hns_tile_${type}" style="left:${left}px; top:${top}px;" data-tile-id="${id}">${type}</div>';
var jstpl_hns_entity = '<div id="hns_entity_${id}" class="hns_entity hns_entity_${type}" data-entity-id="${id}"></div>';
</script>
