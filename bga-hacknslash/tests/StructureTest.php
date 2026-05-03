<?php

use PHPUnit\Framework\TestCase;

final class StructureTest extends TestCase
{
    private static function readFile(string $path): string
    {
        $contents = '';
        $file = new SplFileObject($path);
        while (!$file->eof()) {
            $contents .= $file->fgets();
        }
        return $contents;
    }

    public function testRequiredBgaFilesExist(): void
    {
        $root = dirname(__DIR__);
        $required = [
            'gameinfos.inc.php',
            'dbmodel.sql',
            'material.inc.php',
            'states.inc.php',
            'hacknslash.game.php',
            'hacknslash.action.php',
            'hacknslash.js',
            'hacknslash.css',
        ];

        foreach ($required as $file) {
            $this->assertFileExists($root . '/' . $file);
        }
    }

    public function testStateMachineContainsFullRoundCycleStates(): void
    {
        $states = self::readFile(dirname(__DIR__) . '/states.inc.php');

        $this->assertStringContainsString("'name' => 'cooldown'", $states);
        $this->assertStringContainsString("'name' => 'activateTraps'", $states);
        $this->assertStringContainsString("'name' => 'activateMonsters'", $states);
        $this->assertStringContainsString("'name' => 'levelEndCheck'", $states);
        $this->assertStringContainsString("'gameEnd' => 99", $states);
    }

    public function testDoesNotOverrideFrameworkFinalGameEndArgs(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringNotContainsString('function argGameEnd', $game);
    }

    public function testFreeMoveCanContinueSameTurnAndNotifiesHeroEntity(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $states = self::readFile(dirname(__DIR__) . '/states.inc.php');

        $this->assertStringContainsString("'continueTurn' => 10", $states);
        $this->assertStringContainsString('$entityId = $this->getHeroEntityIdForPlayer($playerId);', $game);
        $this->assertStringContainsString("'entity_id' => \$entityId", $game);
        $this->assertStringContainsString("\$this->gamestate->nextState('continueTurn');", $game);
    }

    public function testFreeMoveIsValidatedAsOneOrthogonalStep(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('assertFreeMoveTarget', $game);
        $this->assertStringContainsString('HNS_BoardRules::isExactStep', $game);
        $this->assertStringContainsString('HNS_BoardRules::isTileWalkable($to)', $game);
        $this->assertStringContainsString('Move target is not available.', $game);
        $this->assertStringContainsString('this.isWalkableTile(tile) && !this.isTileOccupied(tile.id)', self::readFile(dirname(__DIR__) . '/hacknslash.js'));
        $this->assertStringContainsString('Free move is limited to one orthogonal step.', $game);
    }

    public function testEndTurnConsumesRemainingActivePlayerActions(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('HNS_RoundEngine::endPlayerTurn', $game);
        $this->assertStringContainsString('persistPlayerActionFlags', $game);
        $this->assertStringContainsString("\$this->gamestate->nextState('endTurn');", $game);
    }

    public function testNextPlayerTransitionsToEnemyPhaseWhenAllHeroActionsAreSpent(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $states = self::readFile(dirname(__DIR__) . '/states.inc.php');

        $this->assertStringContainsString('HNS_RoundEngine::nextPlayerWithActions', $game);
        $this->assertStringContainsString('$nextPlayerId === null', $game);
        $this->assertStringContainsString("\$this->gamestate->nextState('roundEnd');", $game);
        $this->assertStringContainsString("'roundEnd' => 30", $states);
        $this->assertStringContainsString("'activateTraps' => 40", $states);
        $this->assertStringContainsString("'activateMonsters' => 50", $states);
    }

    public function testGameWonActionGoesDirectlyToGameEndBeforeTrapPhase(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('hasGameWon', $game);
        $this->assertStringContainsString('scoreCooperativeVictory', $game);
        $this->assertStringContainsString('$gameWon = $this->hasGameWon($events);', $game);
        $this->assertStringContainsString('if ($gameWon) {', $game);
        $this->assertStringContainsString('$this->scoreCooperativeVictory();', $game);
        $this->assertStringContainsString('$this->bga->playerScore->set((int) $player[\'id\'], 1);', $game);
        $this->assertStringContainsString("\$this->gamestate->nextState('gameEnd');", $game);
        $this->assertStringContainsString('!$gameWon', $game);
    }

    public function testClearingLevelDuringPlayerActionSkipsTrapPhase(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $states = self::readFile(dirname(__DIR__) . '/states.inc.php');

        $this->assertStringContainsString('private function isCurrentLevelCleared(): bool', $game);
        $this->assertStringContainsString('HNS_GameEngine::isLevelCleared($this->loadEngineState())', $game);
        $this->assertStringContainsString('$levelCleared = $this->isCurrentLevelCleared();', $game);
        $this->assertStringContainsString('if ($levelCleared) {', $game);
        $this->assertStringContainsString('$this->gamestate->nextState(\'levelEndCheck\');', $game);
        $this->assertStringContainsString("'levelEndCheck' => 60", $states);
    }

    public function testZombieTurnConsumesSkippedPlayerActionsBeforeAdvancing(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('HNS_RoundEngine::endPlayerTurn($this->loadEngineState(), (int) $active_player)', $game);
        $this->assertStringContainsString('$this->persistPlayerActionFlags($engineState[\'players\'][(int) $active_player]);', $game);
    }

    public function testPlayCardUsesDisplayedPlayerPowers(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('FROM player_power WHERE player_power_id = $powerId AND player_id = $playerId', $game);
        $this->assertStringContainsString('args.card_id = power.id;', $js);
        $this->assertStringContainsString('playSelectedPower', $js);
        $this->assertStringContainsString('cooldownAfterPowerUse', $game);
        $this->assertStringContainsString("'quick-shot_1': 'cards/powers/quick-shot-1.webp'", $js);
        $this->assertStringContainsString("'quick-strike_1': 'cards/powers/quick-strike-1.webp'", $js);
        $this->assertStringContainsString("'quick-strike_3': 'cards/powers/quick-strike-3.webp'", $js);
    }

    public function testHeroCardsRenderInRightSidebarBelowActiveHero(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');

        $sidebarPosition = strpos($js, '<aside id="hns_side"');
        $activeHeroPosition = strpos($js, 'id="hns_status"');
        $handPosition = strpos($js, 'id="hns_hand"');
        $partnerPosition = strpos($js, 'id="hns_partner_status"');

        $this->assertNotFalse($sidebarPosition);
        $this->assertNotFalse($activeHeroPosition);
        $this->assertNotFalse($handPosition);
        $this->assertNotFalse($partnerPosition);
        $this->assertGreaterThan($sidebarPosition, $handPosition);
        $this->assertGreaterThan($activeHeroPosition, $handPosition);
        $this->assertGreaterThan($handPosition, $partnerPosition);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(96px, 1fr));', $css);
    }

    public function testClientMovesEntitiesFromEngineMoveEvents(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString("event.source_entity_id && event.target_tile_id", $js);
        $this->assertStringContainsString('this.moveEventEntities(event);', $js);
    }

    public function testClientUpdatesHeroPanelHealthFromTargetEntityEvents(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('updateEntityHealthFromEvent', $js);
        $this->assertStringContainsString('updatePlayerHealthFromHeroEntity', $js);
    }

    public function testClientMovesAllEntitiesFromStackMoveEvents(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('moveEventEntities', $js);
        $this->assertStringContainsString('event.moved_entity_ids && event.moved_entity_ids.length', $js);
        $this->assertStringContainsString('this.moveEntityNode(entityIds[i], event.target_tile_id, true)', $js);
        $this->assertStringContainsString('animateEntityMove', $js);
        $this->assertStringContainsString('hns_entity_moving', self::readFile(dirname(__DIR__) . '/hacknslash.css'));
    }

    public function testClientAddsSummonedMonstersFromNotifications(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('monsterSummon', $js);
        $this->assertStringContainsString('addSummonedEntity', $js);
        $this->assertStringContainsString('event.type === \'monsterSummon\'', $js);
        $this->assertStringContainsString('event.summoned_entity', $js);
        $this->assertStringContainsString('this.placeEntity(entity, this.gamedatas.tiles || {})', $js);
    }

    public function testClientRemovesKilledEnemiesFromBoardAndCards(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');

        $this->assertStringContainsString('markEntityDead', $js);
        $this->assertStringContainsString('updateEntityHealthBadge', $js);
        $this->assertStringContainsString("dojo.query('.hns_entity_health', node)", $js);
        $this->assertStringContainsString("event.type === 'afterKill'", $js);
        $this->assertStringContainsString("'entityDamaged'", $js);
        $this->assertStringContainsString("'entityDamaged' => ''", self::readFile(dirname(__DIR__) . '/modules/HNS_EventDispatcher.php'));
        $this->assertStringContainsString("entity.type === 'monster' || entity.type === 'boss'", $js);
        $this->assertStringContainsString('delete this.gamedatas.entities[entityId]', $js);
        $this->assertStringContainsString('dojo.destroy(node)', $js);
        $this->assertStringContainsString('hns_entity_dead', $js);
        $this->assertStringContainsString('hns_monster_card_dead', $js);
        $this->assertStringContainsString('hns_monster_card_damaged', $js);
        $this->assertStringContainsString('deadCount', $js);
        $this->assertStringContainsString('hns_monster_card_losses', $js);
        $this->assertStringContainsString('.hns_entity_dead', $css);
        $this->assertStringContainsString('.hns_monster_card_dead', $css);
        $this->assertStringContainsString('.hns_monster_card_damaged', $css);
        $this->assertStringContainsString('.hns_monster_card_losses', $css);
    }

    public function testClientRendersBossWithPhaseCardArt(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString("entity.type === 'boss'", $js);
        $this->assertStringContainsString('getBossTileImage', $js);
        $this->assertStringContainsString('getBossCardImage', $js);
        $this->assertStringContainsString("monsterKey: bossKey + '-' + phase", $js);
        $this->assertStringContainsString("'slasher': {", $js);
    }

    public function testClientHighlightsFreeMoveTiles(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');

        $this->assertStringContainsString('highlightFreeMoveTiles', $js);
        $this->assertStringContainsString('scheduleFreeMoveHighlight', $js);
        $this->assertStringContainsString('canActivePlayerMove', $js);
        $this->assertStringContainsString('isHeroHeldByAdjacentSlime', $js);
        $this->assertStringContainsString('!this.isHeroHeldByAdjacentSlime()', $js);
        $this->assertStringContainsString("parseInt(entity.type_arg || 0, 10) !== 2", $js);
        $this->assertStringContainsString('parseInt(player.free_move_available || 0, 10) === 1', $js);
        $this->assertStringContainsString('clearFreeMoveHighlights', $js);
        $this->assertStringContainsString('isFreeMoveTile', $js);
        $this->assertStringContainsString('hns_free_move_tile', $js);
        $this->assertStringContainsString('.hns_tile.hns_free_move_tile', $css);
        $this->assertStringNotContainsString('.hns_tile.hns_free_move_tile::after', $css);
    }

    public function testClientHighlightsPowerTargetsAndValidatesVortexSelection(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');
        $actions = self::readFile(dirname(__DIR__) . '/hacknslash.action.php');

        $this->assertStringContainsString('highlightPowerTargets', $js);
        $this->assertStringContainsString('clearPowerHighlights', $js);
        $this->assertStringContainsString('hns_power_target_tile', $js);
        $this->assertStringContainsString('target_entity_ids', $js);
        $this->assertStringContainsString('monsterIdsAdjacentToTile', $js);
        $this->assertStringContainsString('selectedPowerTileId', $js);
        $this->assertStringContainsString('selectedPowerTargetEntityIds', $js);
        $this->assertStringContainsString('togglePullTarget', $js);
        $this->assertStringContainsString('updateConfirmTargetReticles', $js);
        $this->assertStringContainsString('hns_target_count', $js);
        $this->assertStringContainsString('hns_power_validate', $js);
        $this->assertStringContainsString('hns_power_cancel', $js);
        $this->assertStringContainsString('onValidatePowerSelection', $js);
        $this->assertStringContainsString('onCancelPowerSelection', $js);
        $this->assertStringContainsString('validatePowerSelection', $js);
        $this->assertStringContainsString('updatePowerConfirmControls', $js);
        $this->assertStringContainsString('clearSelectedPower', $js);
        $this->assertStringContainsString('isTileValidPowerTarget', $js);
        $this->assertStringContainsString("(power.range_metric || 'orthogonal') === 'orthogonal'", $js);
        $this->assertStringContainsString('String(from.x) !== String(tile.x) && String(from.y) !== String(tile.y)', $js);
        $this->assertStringContainsString('monsterIdOnTile', $js);
        $this->assertStringContainsString('payload.target_entity_id = targetEntityId', $js);
        $this->assertStringContainsString('this.isWalkableTile(tile) && !this.isTileOccupied(tile.id)', $js);
        $this->assertStringContainsString("['floor', 'spikes'].indexOf(tile.type) !== -1", $js);
        $this->assertStringContainsString('this.monsterIdsAdjacentToTile(tile.id).length > 0', $js);
        $this->assertStringContainsString("join(' ')", $js);
        $this->assertStringContainsString("this.selectedPowerTileId = String(entity.tile_id)", $js);
        $this->assertStringContainsString("preg_split('/\\s+/'", $actions);
        $this->assertStringContainsString('.hns_tile.hns_power_target_tile', $css);
        $this->assertStringNotContainsString('.hns_tile.hns_power_target_tile::after', $css);
        $this->assertStringContainsString('.hns_target_count', $css);
        $this->assertStringContainsString('z-index: 3', $css);
        $this->assertStringContainsString('.hns_power_confirm', $css);
        $this->assertStringContainsString('.hns_hidden', $css);
        $this->assertStringContainsString('#hns_power_validate', $css);
        $this->assertStringContainsString('hns_spawn_label', $js);
        $this->assertStringContainsString('.hns_spawn_label', $css);
    }

    public function testClickingSelectedPowerCardClearsSelection(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('this.selectedPowerKey === powerKey', $js);
        $this->assertStringContainsString('String(this.selectedPowerSlot || \'\') === String(slot || \'\')', $js);
        $this->assertStringContainsString('this.clearSelectedPower();', $js);
    }

    public function testClientHighlightsMonsterAttackTilesOnMonsterClick(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');

        $this->assertStringContainsString('highlightMonsterAttackTiles', $js);
        $this->assertStringContainsString('clearMonsterAttackHighlights', $js);
        $this->assertStringContainsString('isTileInMonsterAttackRange', $js);
        $this->assertStringContainsString('isTileInMonsterFrontArc', $js);
        $this->assertStringContainsString('hns_monster_attack_tile', $js);
        $this->assertStringContainsString('monster.range_metric || \'orthogonal\'', $js);
        $this->assertStringContainsString('.hns_tile.hns_monster_attack_tile', $css);
        $this->assertStringNotContainsString('.hns_tile.hns_monster_attack_tile::after', $css);
    }

    public function testClientUpdatesActionStateAfterCardUse(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('playerActionState', $js);
        $this->assertStringContainsString('updatePlayerActionState', $js);
        $this->assertStringContainsString('notifyPlayerActionState', $game);
        $this->assertStringContainsString('player_power_id', $game);
        $this->assertStringContainsString("'power_key' => \$powerKey", $game);
        $this->assertStringContainsString('this.gamedatas.player_powers[args.player_power_id].power_key = args.power_key', $js);
        $this->assertStringContainsString('power_cooldown', $game);
        $this->assertStringContainsString('shouldEndActivePlayerTurn', $game);
        $this->assertStringContainsString("power.effect === 'attack' && parseInt(power.targets || 1, 10) > 1", $js);
        $this->assertStringContainsString("power.effect === 'dash_attack'", $js);
        $multiAttackPosition = strpos($js, 'isSelectedPowerMultiAttack: function ()');
        $requiresConfirmPosition = strpos($js, 'requiresConfirmTargets: function ()');
        $this->assertNotFalse($multiAttackPosition);
        $this->assertNotFalse($requiresConfirmPosition);
        $this->assertStringNotContainsString("dash_attack", substr($js, $multiAttackPosition, $requiresConfirmPosition - $multiAttackPosition));
        $this->assertStringContainsString('!$shouldEndTurn', $game);
        $this->assertStringContainsString('if ($shouldEndTurn)', $game);
        $this->assertStringContainsString("\$this->gamestate->nextState('endTurn');", $game);
    }

    public function testClientSynchronizesCooldownTickNotifications(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('powerCooldownsUpdated', $game);
        $this->assertStringContainsString('powerCooldownsUpdated', $js);
        $this->assertStringContainsString('notif_powerCooldownsUpdated', $js);
        $this->assertStringContainsString('this.gamedatas.player_powers = args.player_powers', $js);
        $this->assertStringContainsString('hns_power_cooldown_overlay', $js);
        $this->assertStringContainsString('cooldown_overlay: cooldown > 0 ? cooldown :', $js);
        $this->assertStringContainsString("cooldown_overlay: ''", $js);
        $this->assertStringContainsString('.hns_power_cooldown_overlay', $css);
        $this->assertStringContainsString('.hns_power_cooldown_overlay:empty', $css);
    }

    public function testClientSynchronizesRoundStartActionFlags(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $player = self::readFile(dirname(__DIR__) . '/modules/HNS_Player.php');
        $board = self::readFile(dirname(__DIR__) . '/modules/HNS_Board.php');

        $this->assertStringContainsString('roundStarted', $player);
        $this->assertStringContainsString('deleteDeadEnemiesForCurrentLevel', $player);
        $this->assertStringContainsString("entity_type IN ('monster', 'boss')", $board);
        $this->assertStringContainsString("entity_state = 'dead' OR e.entity_health <= 0", $board);
        $this->assertStringContainsString('roundStarted', $js);
        $this->assertStringContainsString('notif_roundStarted', $js);
        $this->assertStringContainsString('this.gamedatas.players = args.players', $js);
        $this->assertStringContainsString("'entities' => \$this->getEntities()", $player);
        $this->assertStringContainsString('this.gamedatas.entities = args.entities', $js);
        $this->assertStringContainsString('this.renderBoard(this.gamedatas.tiles || {}, this.gamedatas.entities)', $js);
        $this->assertStringContainsString('this.scheduleFreeMoveHighlight()', $js);
    }

    public function testFreeActionsUsePersistedEventChain(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('loadFreeActionChain', $game);
        $this->assertStringContainsString('saveFreeActionChain', $game);
        $this->assertStringContainsString('consumeFreePower', $game);
        $this->assertStringContainsString('startFreeActionChain($events)', $game);
        $this->assertStringContainsString('free_action_events', $game);
        $this->assertStringContainsString('this.gamedatas.free_action_events', $js);
        $this->assertStringContainsString('events.indexOf(info.free_triggers[i])', $js);
    }

    public function testClientEventLogPersistsAcrossStateRefreshes(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('loadEventMessages', $js);
        $this->assertStringContainsString('saveEventMessages', $js);
        $this->assertStringContainsString('renderEventMessages', $js);
        $this->assertStringContainsString('localStorage.setItem', $js);
        $this->assertStringContainsString('localStorage.getItem', $js);
    }

    public function testLevelClearSendsAndDisplaysRewardOffer(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('HNS_LevelReward::drawOffer', $game);
        $this->assertStringContainsString("'reward_offer' =>", $game);
        $this->assertStringContainsString("'reward_upgrades' =>", $game);
        $this->assertStringContainsString('this.rewardOffer = event.reward_offer || []', $js);
        $this->assertStringContainsString('this.rewardUpgrades = event.reward_upgrades || []', $js);
        $this->assertStringContainsString('renderRewardOffer', $js);
    }

    public function testLevelStartedRefreshesClientBoardSnapshot(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString("'tiles' => \$this->getTilesForLevel", $game);
        $this->assertStringContainsString("'entities' => \$this->getEntities()", $game);
        $this->assertStringContainsString("'player_powers' => \$this->getPlayerPowers()", $game);
        $this->assertStringContainsString('applyLevelSnapshot', $js);
        $this->assertStringContainsString("event.type === 'levelStarted'", $js);
        $this->assertStringContainsString('this.gamedatas.player_powers = event.player_powers', $js);
        $this->assertStringContainsString('this.renderPowerCards();', $js);
        $this->assertStringContainsString('this.renderBoard(this.gamedatas.tiles, this.gamedatas.entities)', $js);
    }

    public function testRewardChoiceUsesDedicatedUpgradeStateAndFocusesRewardUi(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $actions = self::readFile(dirname(__DIR__) . '/hacknslash.action.php');
        $states = self::readFile(dirname(__DIR__) . '/states.inc.php');
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');

        $this->assertStringContainsString('actChooseReward', $states);
        $this->assertStringContainsString('upgradeReward', $states);
        $this->assertStringContainsString('actSkipReward', $states);
        $this->assertStringContainsString("'upgradeReward' => 65", $states);
        $this->assertStringContainsString('function actChooseReward()', $actions);
        $this->assertStringContainsString("\$_REQUEST['power_key']", $actions);
        $this->assertStringContainsString("preg_match('/\\A[A-Za-z0-9_-]*\\z/'", $actions);
        $this->assertStringContainsString('function actSkipReward()', $actions);
        $this->assertStringContainsString('public function actChooseReward', $game);
        $this->assertStringContainsString('public function actSkipReward', $game);
        $this->assertStringContainsString('argUpgradeReward', $game);
        $this->assertStringContainsString('startNextLevelAfterReward', $game);
        $this->assertStringContainsString('UPDATE player_power SET power_cooldown = 0', $game);
        $this->assertStringContainsString('HNS_LevelReward::takeOfferedPower', $game);
        $this->assertStringContainsString('HNS_LevelReward::upgradeExistingPower', $game);
        $this->assertStringContainsString('remainingPowerDeckKeys', $game);
        $this->assertStringContainsString('ensureMissingRewardCardsInDeck', $game);
        $this->assertStringContainsString('foreach ([\'deck\', \'discard\'] as $location)', $game);
        $this->assertStringContainsString('$this->getPlayerPowers() as $playerPower', $game);
        $this->assertStringContainsString('$this->cards->createCards([[\'type\' => \'bonus\', \'type_arg\' => $index + 1, \'nbr\' => 1]], \'deck\')', $game);
        $this->assertStringContainsString('$this->cards->getCardsInLocation(\'deck\')', $game);
        $this->assertStringContainsString('shuffle($powerKeys)', $game);
        $this->assertStringContainsString('removePowerFromDeck($chosenPowerKey)', $game);
        $this->assertStringContainsString('$this->cards->moveCard($cardId, \'discard\')', $game);
        $this->assertStringContainsString('hns_reward_panel', $js);
        $this->assertStringContainsString('hns_power_card_name', $js);
        $this->assertStringContainsString('.hns_power_card_name', $css);
        $this->assertStringContainsString('updateRewardFocusState', $js);
        $this->assertStringContainsString('hns_reward_focus', $js);
        $this->assertStringContainsString('hns_panel_folded', $css);
        $this->assertStringContainsString('onRewardOfferClick', $js);
        $this->assertStringContainsString('onRewardUpgradeClick', $js);
        $this->assertStringContainsString('hns_reward_upgrade', $js);
        $this->assertStringContainsString('reward_upgrades', $game);
        $this->assertStringContainsString('onUpgradePowerClick', $js);
    }

    public function testRewardStateChangesActionPromptAndShowsOnlySkip(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $states = self::readFile(dirname(__DIR__) . '/states.inc.php');
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString("'name' => 'upgradeReward'", $states);
        $this->assertStringContainsString("'descriptionmyturn' => clienttranslate('\${you} may upgrade and pick a card')", $states);
        $this->assertStringContainsString('isRewardPending', $game);
        $this->assertStringContainsString('upgrade your character and pick a card', $game);
        $this->assertStringContainsString("'reward_pending' => \$this->isRewardPending() ? 1 : 0", $game);
        $this->assertStringContainsString("stateName === 'upgradeReward'", $js);
        $this->assertStringContainsString("this.addActionButton('hns_skip_reward_button', _('Skip'), 'onSkipReward')", $js);
        $this->assertStringContainsString("this.bgaPerformAction('actSkipReward')", $js);
        $this->assertStringContainsString('onSkipReward', $js);
    }

    public function testBoardResizesToGeneratedTileGrid(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');

        $this->assertStringContainsString('resizeBoardToTiles', $js);
        $this->assertStringContainsString('this.resizeBoardToTiles(tiles)', $js);
        $this->assertStringContainsString('this.boardTileSize = 70', $js);
        $this->assertStringContainsString('this.boardBorderTileSize = 36', $js);
        $this->assertStringContainsString('tileBox', $js);
        $this->assertStringContainsString('boardAxisOffset', $js);
        $this->assertStringContainsString('tileBounds', $js);
        $this->assertStringContainsString('width:${width}px; height:${height}px;', $js);
        $this->assertStringContainsString("width: this.boardAxisOffset(bounds.maxX + 1, bounds.maxX) + 'px'", $js);
        $this->assertStringContainsString("height: this.boardAxisOffset(bounds.maxY + 1, bounds.maxY) + 'px'", $js);
        $this->assertStringContainsString('overflow: visible;', $css);
        $this->assertStringContainsString('width: 70px;', $css);
        $this->assertStringContainsString('height: 70px;', $css);
    }

    public function testOptionalActionsCanBeSkippedIndependently(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $actions = self::readFile(dirname(__DIR__) . '/hacknslash.action.php');
        $states = self::readFile(dirname(__DIR__) . '/states.inc.php');
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('actSkipFreeMove', $states);
        $this->assertStringContainsString('actSkipMainAction', $states);
        $this->assertStringContainsString('function actSkipFreeMove()', $actions);
        $this->assertStringContainsString('function actSkipMainAction()', $actions);
        $this->assertStringContainsString('public function actSkipFreeMove(): void', $game);
        $this->assertStringContainsString('public function actSkipMainAction(): void', $game);
        $this->assertStringContainsString('nextStateAfterOptionalActionSkip', $game);
        $this->assertStringContainsString('shouldEndActivePlayerTurn', $game);
        $this->assertStringContainsString('isPlayerFreeActionAvailable', $game);
        $this->assertStringContainsString('free_action_available', $game);
        $this->assertStringContainsString('Skip free action', $js);
        $this->assertStringContainsString('Skip free move', $js);
        $this->assertStringContainsString('Skip action', $js);
    }

    public function testMonsterAttacksAnimateTowardTheirTarget(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');

        $this->assertStringContainsString("'monsterAttack'", $js);
        $this->assertStringContainsString('animateMonsterAttack', $js);
        $this->assertStringContainsString('monsterStick', $js);
        $this->assertStringContainsString('monsterCharge', $js);
        $this->assertStringContainsString('monsterFrontArcAttack', $js);
        $this->assertStringContainsString('actor_entity_ids', $js);
        $this->assertStringContainsString('animateMonsterActorAttack', $js);
        $this->assertStringContainsString('lungePosition', $js);
        $this->assertStringContainsString('dojo.animateProperty', $js);
        $this->assertStringContainsString('hns_entity_attacking', $js);
        $this->assertStringContainsString('@keyframes hns_entity_attack_lunge', $css);
        $this->assertStringContainsString('.hns_entity_attacking', $css);
    }

    public function testKamikazeExplosionAnimatesOnDeath(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');

        $this->assertStringContainsString('monsterExplode', $js);
        $this->assertStringContainsString('animateMonsterExplosion', $js);
        $this->assertStringContainsString('updateExplosionTargetHealth', $js);
        $this->assertStringContainsString('target_health_by_entity_id', $js);
        $this->assertStringContainsString('event.actor_entity_ids && event.actor_entity_ids.length', $js);
        $this->assertStringContainsString('hns_entity_exploding', $js);
        $this->assertStringContainsString('@keyframes hns_entity_explosion', $css);
        $this->assertStringContainsString('.hns_entity_exploding::before', $css);
    }

    public function testTurnActionsAreValidatedAgainstRemainingActionFlags(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('Move is not available.', $game);
        $this->assertStringContainsString('Main action is not available.', $game);
        $this->assertStringContainsString('HNS_RoundEngine::consumeMove', $game);
        $this->assertStringContainsString('Slimed heroes cannot move except with Dash.', $roundEngine = self::readFile(dirname(__DIR__) . '/modules/HNS_RoundEngine.php'));
        $this->assertStringContainsString('isPlayerHeldByAdjacentSlime', $roundEngine);
        $this->assertStringContainsString('clearExpiredSlimedStatuses', $roundEngine);
        $this->assertStringContainsString('isHeroHeldByAdjacentSlimeEntity', $roundEngine);
        $this->assertStringContainsString('clearExpiredSlimedStatus', $roundEngine);
        $this->assertStringContainsString('hasSlimeStatus', $roundEngine);
        $this->assertStringContainsString('(slimed|stuck|stick)', $roundEngine);
        $this->assertStringContainsString('hasSlimeStatus', $js);
        $this->assertStringContainsString('(slimed|stuck|stick)', $js);
        $this->assertStringContainsString('clearExpiredSlimeStatuses', $js);
        $this->assertStringContainsString('this.clearExpiredSlimeStatuses();', $js);
        $this->assertStringContainsString('this.scheduleFreeMoveHighlight();', $js);
        $this->assertStringContainsString('tiles/markers/slimed.webp', $js);
        $this->assertStringContainsString('tiles/markers/shield.webp', $js);
        $this->assertStringContainsString('hns_entity_effects', $js);
        $this->assertStringContainsString('hns_entity_shield_icon', $js);
        $this->assertStringContainsString('hns_entity_shielded', $js);
        $this->assertStringContainsString('hns_entity_thorns', $js);
        $this->assertStringContainsString('hns_entity_thorns_icon', $js);
        $this->assertStringContainsString('cards/monsters/thorns.webp', $js);
        $this->assertStringContainsString('hasThorns', $js);
        $this->assertStringContainsString("indexOf('thorns') !== -1", $js);
        $this->assertStringContainsString('parseInt(entity.has_shield || 0, 10) === 1', $js);
        $this->assertStringContainsString('parseInt(entity.shield_broken || 0, 10) !== 1', $js);
        $this->assertStringContainsString('updateEntityShieldFromEvent', $js);
        $this->assertStringContainsString("dojo.query('.hns_entity_shield_icon', node)", $js);
        $this->assertStringContainsString('dojo.destroy(shieldIcon)', $js);
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');
        $this->assertStringContainsString('.hns_entity_shield_icon', $css);
        $this->assertStringContainsString('.hns_entity_shielded', $css);
        $this->assertStringContainsString('.hns_entity_thorns', $css);
        $this->assertStringContainsString('.hns_entity .hns_entity_thorns_icon', $css);
        $this->assertStringContainsString('rgba(128, 76, 36', $css);
        $this->assertStringContainsString('isActivePlayerMoveAvailable', $game);
        $this->assertStringContainsString('parseInt(player.action_points || 0, 10) > 0', $js);
    }

    public function testEngineResolutionErrorsAreUserFacing(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('catch (InvalidArgumentException $e)', $game);
        $this->assertStringContainsString('throw new BgaUserException(clienttranslate($e->getMessage()))', $game);
    }
}
