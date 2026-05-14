<?php

$bosses = [
    'slasher' => [
        'name' => clienttranslate('Slasher'),
        'default_order' => 1,
        'phases' => [
            1 => [
                'health' => 8,
                'move' => 2,
                'move_metric' => 'orthogonal',
                'range' => 1,
                'range_metric' => 'chebyshev',
                'damage' => 2,
                'size' => 'boss',
                'can_attack' => true,
                'can_move' => true,
                'can_attack_and_move' => true,
            ],
            2 => [
                'health' => 8,
                'move' => 2,
                'move_metric' => 'orthogonal',
                'range' => 1,
                'range_metric' => 'chebyshev',
                'damage' => 2,
                'size' => 'boss',
                'can_attack' => true,
                'can_move' => true,
                'can_attack_and_move' => true,
                'pre_actions' => [
                    ['type' => 'spawn_minions', 'count' => 2, 'monster_ids' => [1, 2]],
                ],
            ],
            3 => [
                'health' => 8,
                'move' => 2,
                'move_metric' => 'orthogonal',
                'range' => 1,
                'range_metric' => 'chebyshev',
                'damage' => 2,
                'size' => 'boss',
                'can_attack' => true,
                'can_move' => true,
                'can_attack_and_move' => true,
                'pre_actions' => [
                    ['type' => 'grant_shield'],
                    ['type' => 'spawn_minions', 'count' => 2, 'monster_ids' => [1, 2]],
                ],
            ],
        ],
    ],
    'striker' => [
        'name' => clienttranslate('Striker'),
        'default_order' => 2,
        'phases' => [
            1 => [
                'health' => 8,
                'move' => 1,
                'move_metric' => 'orthogonal',
                'range' => 3,
                'range_metric' => 'orthogonal',
                'damage' => 1,
                'size' => 'boss',
                'can_attack' => true,
                'can_move' => true,
                'can_attack_and_move' => true,
            ],
            2 => [
                'health' => 9,
                'move' => 1,
                'move_metric' => 'orthogonal',
                'range' => 3,
                'range_metric' => 'orthogonal',
                'damage' => 2,
                'size' => 'boss',
                'can_attack' => true,
                'can_move' => true,
                'can_attack_and_move' => true,
                'pre_actions' => [
                    ['type' => 'spawn_minions', 'count' => 2, 'monster_ids' => [1, 2]],
                ],
            ],
            3 => [
                'health' => 10,
                'move' => 1,
                'move_metric' => 'orthogonal',
                'range' => 3,
                'range_metric' => 'orthogonal',
                'damage' => 2,
                'size' => 'boss',
                'can_attack' => true,
                'can_move' => true,
                'can_attack_and_move' => true,
                'pre_actions' => [
                    ['type' => 'grant_shield'],
                    ['type' => 'spawn_minions', 'count' => 2, 'monster_ids' => [1, 2]],
                ],
            ],
        ],
    ],
];

return $bosses;
