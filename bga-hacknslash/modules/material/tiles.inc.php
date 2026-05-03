<?php

$tile_types = [
    'floor' => ['name' => clienttranslate('Floor'), 'walkable' => true],
    'wall' => ['name' => clienttranslate('Wall'), 'walkable' => false],
    'pillar' => ['name' => clienttranslate('Pillar'), 'walkable' => false],
    'entry' => ['name' => clienttranslate('Entry'), 'walkable' => false],
    'exit' => ['name' => clienttranslate('Exit'), 'walkable' => false],
    'hole' => ['name' => clienttranslate('Hole'), 'walkable' => false],
    'spikes' => ['name' => clienttranslate('Spikes'), 'walkable' => true],
];

return $tile_types;
