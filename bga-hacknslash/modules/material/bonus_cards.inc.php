<?php

$bonus_cards = [
    'attack' => [
        'name' => clienttranslate('Attack'),
        'rank' => 0,
        'effect' => 'attack',
        'targets' => 1,
        'damage' => 1,
        'range' => [0, 1],
        'cooldown' => 0,
        'free_triggers' => [],
    ],
    'dash' => [
        'name' => clienttranslate('Dash'),
        'rank' => 1,
        'effect' => 'dash',
        'distance' => [1, 2],
        'cooldown' => 1,
        'free_triggers' => ['afterCardPlayed'],
    ],
    'vortex' => [
        'name' => clienttranslate('Vortex'),
        'rank' => 1,
        'effect' => 'pull',
        'targets' => 2,
        'range' => [1, 2],
        'pull_distance' => 1,
        'cooldown' => 2,
        'free_triggers' => [],
    ],
];
