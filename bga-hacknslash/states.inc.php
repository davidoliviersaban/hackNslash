<?php
/**
 * HackNSlash game states.
 *
 * This is a deliberately small BGA state machine scaffold. Detailed rules will be
 * added once the online flow is specified.
 */

$machinestates = [
    2 => [
        'name' => 'gameSetup',
        'description' => '',
        'type' => 'manager',
        'action' => 'stGameSetup',
        'transitions' => ['' => 10],
    ],

    10 => [
        'name' => 'playerTurn',
        'description' => clienttranslate('${name}'),
        'descriptionmyturn' => clienttranslate('${you} ${name}'),
        'type' => 'multipleactiveplayer',
        'action' => 'stEnterPlayerTurn',
        'possibleactions' => ['actMove', 'actPlayCard', 'actChooseReward', 'actSkipFreeMove', 'actSkipMainAction', 'actEndTurn'],
        'args' => 'argPlayerTurn',
        'transitions' => [
            'continueTurn' => 10,
            'resolveAction' => 20,
            'endTurn' => 70,
            'roundEnd' => 30,
            'levelEndCheck' => 60,
            'gameEnd' => 99,
        ],
    ],

    20 => [
        'name' => 'resolveAction',
        'description' => clienttranslate('Resolving action...'),
        'type' => 'game',
        'action' => 'stResolveAction',
        'transitions' => [
            'continueTurn' => 10,
            'endTurn' => 70,
            'roundEnd' => 30,
            'gameEnd' => 99,
        ],
    ],

    30 => [
        'name' => 'cooldown',
        'description' => clienttranslate('Cooldowns tick down...'),
        'type' => 'game',
        'action' => 'stCooldown',
        'transitions' => ['activateTraps' => 40],
    ],

    40 => [
        'name' => 'activateTraps',
        'description' => clienttranslate('Traps activate...'),
        'type' => 'game',
        'action' => 'stActivateTraps',
        'transitions' => ['activateMonsters' => 50, 'gameEnd' => 99],
    ],

    50 => [
        'name' => 'activateMonsters',
        'description' => clienttranslate('Monsters activate...'),
        'type' => 'game',
        'action' => 'stActivateMonsters',
        'transitions' => ['levelEndCheck' => 60, 'gameEnd' => 99],
    ],

    60 => [
        'name' => 'levelEndCheck',
        'description' => clienttranslate('Checking level end...'),
        'type' => 'game',
        'action' => 'stLevelEndCheck',
        'transitions' => ['nextRound' => 10, 'upgradeReward' => 65, 'gameEnd' => 99],
    ],

    65 => [
        'name' => 'upgradeReward',
        'description' => clienttranslate('Players may upgrade and pick a card'),
        'descriptionmyturn' => clienttranslate('${you} may upgrade and pick a card'),
        'type' => 'multipleactiveplayer',
        'action' => 'stEnterUpgradeReward',
        'possibleactions' => ['actChooseReward', 'actSkipReward'],
        'args' => 'argUpgradeReward',
        'transitions' => [
            'nextLevel' => 66,
            'gameEnd' => 99,
        ],
    ],

    66 => [
        'name' => 'startNextLevel',
        'description' => clienttranslate('Starting next level...'),
        'type' => 'game',
        'action' => 'stStartNextLevel',
        'transitions' => [
            'nextLevel' => 10,
            'gameEnd' => 99,
        ],
    ],

    70 => [
        'name' => 'nextPlayer',
        'description' => '',
        'type' => 'game',
        'action' => 'stNextPlayer',
        'transitions' => [
            'nextTurn' => 10,
            'roundEnd' => 30,
            'gameEnd' => 99,
        ],
    ],
];
