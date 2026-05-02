<?php
/**
 * Static material entry point.
 *
 * Material files now `return` their data so we can include them more than once
 * (BGA reuses the Hacknslash instance across requests; previously a second
 * `require_once` would silently no-op and leave the local variables undefined).
 */

require_once(__DIR__ . '/modules/material/constants.inc.php');

$this->tile_types = require(__DIR__ . '/modules/material/tiles.inc.php');
$this->monsters = require(__DIR__ . '/modules/material/monsters.inc.php');
$this->bosses = require(__DIR__ . '/modules/material/bosses.inc.php');
$this->bonus_cards = require(__DIR__ . '/modules/material/bonus_cards.inc.php');
