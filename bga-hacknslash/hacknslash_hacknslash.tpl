<div id="hns_wrap">
  <aside id="hns_monster_panel" class="whiteblock hns_panel">
    <h3>{MONSTERS_TITLE}</h3>
    <div id="hns_monster_cards" class="hns_monster_cards"></div>
  </aside>

  <div id="hns_center">
    <div id="hns_main" class="whiteblock hns_panel">
      <div class="hns_board_header">
        <h3>{BOARD_TITLE}</h3>
        <div id="hns_board_hint">{BOARD_HINT}</div>
      </div>
      <div id="hns_board"></div>
    </div>

    <div id="hns_events" class="whiteblock hns_panel">
      <h3>{EVENTS_TITLE}</h3>
      <div id="hns_event_list" class="hns_event_list">
        <div class="hns_event hns_event_empty">{EVENTS_EMPTY}</div>
      </div>
    </div>

    <div id="hns_hand" class="whiteblock hns_panel">
      <h3>{HAND_TITLE}</h3>
      <div id="hns_cards"></div>
    </div>
  </div>

  <aside id="hns_side" class="hns_panel_stack">
    <div id="hns_status" class="whiteblock hns_panel hns_hero_panel hns_active_hero_panel">
      <h3>{STATUS_TITLE}</h3>
      <div id="hns_active_hero" class="hns_hero_card"></div>
    </div>

    <div id="hns_partner_status" class="whiteblock hns_panel hns_hero_panel hns_partner_panel">
      <h3>{PARTNER_TITLE}</h3>
      <div id="hns_partner_hero" class="hns_hero_card hns_hero_card_compact"></div>
    </div>
  </aside>
</div>

<script type="text/javascript">
var jstpl_hns_tile = '<div id="hns_tile_${id}" class="hns_tile hns_tile_${type}" style="left:${left}px; top:${top}px;" data-tile-id="${id}" title="${type}"><span class="hns_tile_label">${type}</span></div>';
var jstpl_hns_entity = '<div id="hns_entity_${id}" class="hns_entity hns_entity_${type} hns_entity_${slug}" data-entity-id="${id}" data-entity-type="${type}" data-monster-key="${monster_key}"><img src="${image}" alt="${label}" /><span class="hns_entity_health">${health}</span></div>';
var jstpl_hns_monster_card = '<div id="hns_monster_card_${key}" class="hns_monster_card" data-monster-key="${key}"><div class="hns_monster_card_effects">${effects}</div><img src="${image}" alt="${name}" /><div class="hns_monster_card_footer"><strong>${name}</strong><span>${count}</span></div></div>';
var jstpl_hns_power_card = '<div id="hns_power_card_${key}" class="hns_power_card ${classes}" data-power-key="${key}"><img src="${image}" alt="${name}" /><div class="hns_power_card_badges">${badges}</div></div>';
var jstpl_hns_hero_card = '<div class="hns_hero_identity"><span class="hns_hero_color" style="background:#${color}"></span><strong>${name}</strong></div><div class="hns_hero_stats"><span>${health_label}: ${health}</span><span>${ap_label}: ${action_points}</span></div><div class="hns_hero_effects">${effects}</div><div class="hns_hero_mini_powers">${powers}</div>';
var jstpl_hns_event = '<div class="hns_event hns_event_${type}">${message}</div>';
</script>
