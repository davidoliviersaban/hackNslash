<?php
/**
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * HackNSlash implementation: David Saban
 *
 * This code is prepared for the BGA Studio platform.
 */

$gameinfos = [
    'game_name' => 'HackNSlash',
    'publisher' => 'Self-published',
    'publisher_website' => '',
    'publisher_bgg_id' => 999999,
    'bgg_id' => 999999,

    'players' => [1, 2],
    'player_colors' => ['ff0000', '008000', '0000ff', 'ffa500'],
    'favorite_colors_support' => true,

    'suggest_player_number' => 2,
    'not_recommend_player_number' => null,
    'disable_player_order_swap_on_rematch' => false,

    'estimated_duration' => 45,
    'fast_additional_time' => 30,
    'medium_additional_time' => 40,
    'slow_additional_time' => 50,

    'tie_breaker_description' => totranslate('The player with the most victory points wins'),

    'losers_not_ranked' => false,
    'solo_mode_ranked' => true,
    'is_coop' => 1,
    'language_dependency' => false,
    'db_undo_support' => true,
];
