<?php
/**
 * Static material entry point.
 *
 * Generated or hand-maintained material files are split under modules/material.
 */

require_once(__DIR__ . '/modules/material/constants.inc.php');
require_once(__DIR__ . '/modules/material/tiles.inc.php');
require_once(__DIR__ . '/modules/material/monsters.inc.php');
require_once(__DIR__ . '/modules/material/bonus_cards.inc.php');

$this->tile_types = $tile_types;
$this->monsters = $monsters;
$this->bonus_cards = $bonus_cards;
