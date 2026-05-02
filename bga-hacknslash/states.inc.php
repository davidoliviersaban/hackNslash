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
        'description' => clienttranslate('${actplayer} must choose an action'),
        'descriptionmyturn' => clienttranslate('${you} must choose an action'),
        'type' => 'activeplayer',
        'possibleactions' => ['actMove', 'actPlayCard', 'actAttack', 'actEndTurn'],
        'args' => 'argPlayerTurn',
        'transitions' => [
            'resolveAction' => 20,
            'endTurn' => 70,
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
            'gameEnd' => 99,
        ],
    ],

    99 => [
        'name' => 'gameEnd',
        'description' => clienttranslate('End of game'),
        'type' => 'manager',
        'action' => 'stGameEnd',
        'args' => 'argGameEnd',
    ],
];
