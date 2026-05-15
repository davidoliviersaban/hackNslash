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
            'stats.jsonc',
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

    public function testUsesFrameworkPlayerNameHelper(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('$this->getPlayerNameById($playerId)', $game);
        $this->assertStringNotContainsString('private function getPlayerNameById', $game);
        $this->assertStringNotContainsString('fetchPlayerNameById', $game);
    }

    public function testPlayerStatsAreDeclaredAndUpdated(): void
    {
        $stats = self::readFile(dirname(__DIR__) . '/stats.jsonc');
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        foreach (['powers_taken', 'powers_played', 'damage_taken', 'damage_dealt', 'monsters_killed', 'victories', 'defeats', 'turns_played'] as $statName) {
            $this->assertStringContainsString('"' . $statName . '":', $stats);
            $this->assertStringContainsString("'$statName'", $game);
        }

        foreach (['dungeon_victories', 'boss_fight_defeats', 'easy_victories', 'hardcore_defeats', 'solo_victories', 'duo_defeats', 'current_win_streak', 'best_win_streak'] as $statName) {
            $this->assertStringContainsString('"' . $statName . '":', $stats);
            $this->assertStringContainsString("'$statName'", $game);
        }

        foreach (['power_taken_fireball_1', 'power_taken_vortex_3', 'power_played_dash_attack_1', 'power_played_point_blank_3'] as $statName) {
            $this->assertStringContainsString('"' . $statName . '":', $stats);
        }
        $this->assertStringNotContainsString('"power_taken_attack":', $stats);
        $this->assertStringNotContainsString('"power_taken_strike":', $stats);
        $this->assertStringContainsString('"power_played_attack":', $stats);
        $this->assertStringContainsString('"power_played_strike":', $stats);
        $this->assertStringContainsString("'power_taken_' . \$powerKey", $game);
        $this->assertStringContainsString("'power_played_' . \$powerKey", $game);
        $this->assertStringContainsString("'power_' . \$eventType . '_' . \$powerKey", $game);

        $this->assertStringContainsString('initPlayerStats', $game);
        $this->assertStringContainsString('playerStatNames', $game);
        $this->assertStringContainsString('powerStatKeys', $game);
        $this->assertStringContainsString('takablePowerStatKeys', $game);
        $this->assertStringContainsString('recordHeroPowerStats', $game);
        $this->assertStringContainsString('recordDamageTakenStats', $game);
        $this->assertStringContainsString('recordCooperativeDefeat', $game);
        $this->assertStringContainsString('safeInitPlayerStat', $game);
        $this->assertStringContainsString('safeIncPlayerStat', $game);
        $this->assertStringContainsString('safeSetPlayerStat', $game);
        $this->assertStringContainsString('recordContextualOutcomeStats', $game);
        $this->assertStringContainsString('Unknown statistic id', $game);
    }

    public function testFinalCombosAreRecordedForEndGameAggregations(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('recordFinalCombo', $game);
        $this->assertStringContainsString('currentFinalComboSnapshot', $game);
        $this->assertStringContainsString('ensureFinalComboTable', $game);
        $this->assertStringContainsString('final_combo', $game);
        $this->assertStringContainsString('finalComboAggregates', $game);
        $this->assertStringContainsString('currentScenarioKey', $game);
        $this->assertStringContainsString('currentDifficultyKey', $game);
        $this->assertStringContainsString('sort($comboPowers);', $game);
        $this->assertStringContainsString('combo_context_outcome', self::readFile(dirname(__DIR__) . '/dbmodel.sql'));
        $this->assertStringContainsString('combo_boss_outcome', self::readFile(dirname(__DIR__) . '/dbmodel.sql'));
    }

    public function testPowerHistoryRecordsWhichPowersArePlayedAndTaken(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $dbmodel = self::readFile(dirname(__DIR__) . '/dbmodel.sql');

        $this->assertStringContainsString('power_history', $dbmodel);
        $this->assertStringContainsString('recordPowerHistory', $game);
        $this->assertStringContainsString('recordPowerHistory($playerId, $powerKey, \'played\')', $game);
        $this->assertStringContainsString('recordPowerHistory($playerId, $chosenPowerKey, \'taken\')', $game);
        $this->assertStringContainsString('powerHistoryAggregates', $game);
    }

    public function testWinStreaksAreStoredByScenarioDifficultyAndPlayerCount(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $dbmodel = self::readFile(dirname(__DIR__) . '/dbmodel.sql');

        $this->assertStringContainsString('win_streak', $dbmodel);
        $this->assertStringContainsString('win_streak_context', $dbmodel);
        $this->assertStringContainsString('recordWinStreak', $game);
        $this->assertStringContainsString('winStreakAggregates', $game);
        $this->assertStringContainsString('$outcome === \'win\'', $game);
    }

    public function testGlobalStateArgsDoNotRequireLoggedPlayer(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $player = self::readFile(dirname(__DIR__) . '/modules/HNS_Player.php');
        $getAllDatas = substr($game, strpos($game, 'protected function getAllDatas()'), strpos($game, 'public function getGameProgression') - strpos($game, 'protected function getAllDatas()'));
        $argPlayerTurn = substr($game, strpos($game, 'public function argPlayerTurn()'), strpos($game, 'public function argUpgradeReward()') - strpos($game, 'public function argPlayerTurn()'));
        $argUpgradeReward = substr($game, strpos($game, 'public function argUpgradeReward()'), strpos($game, 'public function stGameSetup()') - strpos($game, 'public function argUpgradeReward()'));
        $levelEndCheck = substr($game, strpos($game, 'public function stLevelEndCheck()'), strpos($game, 'public function stNextPlayer()') - strpos($game, 'public function stLevelEndCheck()'));

        $this->assertStringNotContainsString('getCurrentPlayerId()', $getAllDatas);
        $this->assertStringNotContainsString('getCurrentPlayerId()', $argPlayerTurn);
        $this->assertStringNotContainsString('getCurrentPlayerId()', $argUpgradeReward);
        $this->assertStringNotContainsString('getCurrentPlayerId()', $levelEndCheck);
        $this->assertStringNotContainsString('getCurrentPlayerId()', $player);
        $this->assertStringContainsString('currentPlayerIdOrFirstPlayerId', $game);
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
        $this->assertStringContainsString('GameRules.isWalkableTile(tile) && !GameRules.isTileOccupied(tile.id, entities)', self::readFile(dirname(__DIR__) . '/hacknslash.js'));
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

    public function testHeroPhaseIsSimultaneousForAllPlayers(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $states = self::readFile(dirname(__DIR__) . '/states.inc.php');

        $this->assertStringContainsString("'type' => 'multipleactiveplayer'", $states);
        $this->assertStringContainsString("'action' => 'stEnterPlayerTurn'", $states);
        $this->assertStringContainsString('public function stEnterPlayerTurn(): void', $game);
        $this->assertStringContainsString('$this->gamestate->setAllPlayersMultiactive();', $game);
        $this->assertStringContainsString('setPlayersMultiactive(array_keys($this->playersWithAvailableActions())', $game);
        $this->assertStringContainsString('$this->getCurrentPlayerId()', $game);
        $this->assertStringContainsString('setPlayerNonMultiactive($playerId', $game);
        $this->assertStringContainsString('nextStateAfterHeroAction($playerId)', $game);
    }

    public function testMultiPlayPowerKeepsPlayerActiveWhilePlaysRemain(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $freeActionAvailable = substr($game, strpos($game, 'private function isPlayerFreeActionAvailable'), strpos($game, '/** @return list<string> */') - strpos($game, 'private function isPlayerFreeActionAvailable'));

        $this->assertStringContainsString('power_plays_remaining plays_remaining', $freeActionAvailable);
        $this->assertStringContainsString('(int) ($power[\'plays_remaining\'] ?? 0)', $freeActionAvailable);
        $this->assertStringContainsString('$this->isFreePowerAvailable($powerKey, (int) ($power[\'cooldown\'] ?? 0), $playerId, (int) ($power[\'plays_remaining\'] ?? 0))', $freeActionAvailable);
    }

    public function testPlayersWithOnlyFreeActionsStayActiveInSimultaneousTurn(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $playersWithAvailableActions = substr($game, strpos($game, 'private function playersWithAvailableActions'), strpos($game, 'private function playersWithPendingRewards') - strpos($game, 'private function playersWithAvailableActions'));

        $this->assertStringContainsString('player_free_move_available = 1 OR player_main_action_available = 1', $playersWithAvailableActions);
        $this->assertStringContainsString('$this->isPlayerFreeActionAvailable($playerId)', $playersWithAvailableActions);
        $this->assertStringContainsString('$players[$playerId] = [\'id\' => $playerId];', $playersWithAvailableActions);
    }

    public function testSetupActivatesAllPlayersForInitialSimultaneousTurn(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $setup = substr($game, strpos($game, 'protected function setupNewGame'), strpos($game, 'protected function getAllDatas') - strpos($game, 'protected function setupNewGame'));
        $stateSetup = substr($game, strpos($game, 'public function stGameSetup()'), strpos($game, 'public function stResolveAction()') - strpos($game, 'public function stGameSetup()'));

        $this->assertStringContainsString('$this->gamestate->setAllPlayersMultiactive();', $setup);
        $this->assertStringContainsString('$this->gamestate->setAllPlayersMultiactive();', $stateSetup);
    }

    public function testRewardPhaseActivatesAllPlayersWithPendingRewards(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $states = self::readFile(dirname(__DIR__) . '/states.inc.php');

        $this->assertStringContainsString("'action' => 'stEnterUpgradeReward'", $states);
        $this->assertStringContainsString('public function stEnterUpgradeReward(): void', $game);
        $this->assertStringContainsString('setPlayersMultiactive(array_keys($this->playersWithPendingRewards())', $game);
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
        $this->assertStringContainsString("'quick_shot_1': 'cards/powers/quick-shot-1.webp'", $js);
        $this->assertStringContainsString("'quick_strike_1': 'cards/powers/quick-strike-1.webp'", $js);
        $this->assertStringContainsString("'quick_strike_3': 'cards/powers/quick-strike-3.webp'", $js);
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
        $this->assertStringContainsString('isHeroHeldBySlime', $js);
        $this->assertStringContainsString('GameRules.isHeroHeldBySlime(hero, tiles, entities)', $js);
        $this->assertStringContainsString("parseInt(entity.type_arg || 0, 10) !== SLIME_TYPE_ARG", $js);
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
        $this->assertStringContainsString('entitiesAdjacentToTile', $js);
        $this->assertStringContainsString('entitiesInPowerArea', $js);
        $this->assertStringContainsString('tilesInPowerArea', $js);
        $this->assertStringContainsString('highlightPotentialPowerArea', $js);
        $this->assertStringContainsString('previewPowerArea', $js);
        $this->assertStringContainsString('isAreaPreviewPower', $js);
        $this->assertStringContainsString('clearPowerAreaPreview', $js);
        $this->assertStringContainsString('onTileMouseEnter', $js);
        $this->assertStringContainsString('onEntityMouseEnter', $js);
        $this->assertStringContainsString('onPowerAreaMouseLeave', $js);
        $this->assertStringContainsString('hns_power_area_candidate_tile', $js);
        $this->assertStringContainsString('hns_power_area_preview_tile', $js);
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
        $this->assertStringContainsString('entityOnTile', $js);
        $this->assertStringContainsString('payload.target_entity_id = targetEntityId', $js);
        $this->assertStringContainsString('GameRules.isWalkableTile(tile) && !GameRules.isTileOccupied(tile.id, entities)', $js);
        $this->assertStringContainsString("['floor', 'spikes'].indexOf(tile.type) !== -1", $js);
        $this->assertStringContainsString('GameRules.entitiesInPowerArea(', $js);
        $this->assertStringContainsString("join(' ')", $js);
        $this->assertStringContainsString("this.selectedPowerTileId = String(entity.tile_id)", $js);
        $this->assertStringContainsString("preg_split('/\\s+/'", $actions);
        $this->assertStringContainsString('.hns_tile.hns_power_target_tile', $css);
        $this->assertStringNotContainsString('.hns_tile.hns_power_target_tile::after', $css);
        $this->assertStringContainsString('.hns_tile.hns_power_area_candidate_tile', $css);
        $this->assertStringContainsString('.hns_tile.hns_power_area_preview_tile', $css);
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
        $this->assertStringContainsString("this.isSelectedPowerDashAttack()", $js);
        $this->assertStringContainsString('!$shouldEndTurn', $game);
        $this->assertStringContainsString('$this->nextStateAfterHeroAction($playerId);', $game);
        $this->assertStringContainsString("\$this->gamestate->setPlayerNonMultiactive(\$playerId, 'roundEnd');", $game);
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
        $this->assertStringContainsString("cooldown_overlay: isPlayableCooldown ? cooldown :", $js);
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
        $this->assertStringContainsString('this.rewardOffer = this.rewardOffersByPlayer[this.player_id] || source.reward_offer || []', $js);
        $this->assertStringContainsString('this.rewardUpgrades = this.rewardUpgradesByPlayer[this.player_id] || source.reward_upgrades || []', $js);
        $this->assertStringContainsString('renderRewardOffer', $js);
    }

    public function testLevelClearCreatesRewardOfferForEachPlayer(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('buildRewardOffersByPlayer', $game);
        $this->assertStringContainsString('getCurrentRewardOfferForPlayer', $game);
        $this->assertStringContainsString('getCurrentRewardUpgradesForPlayer', $game);
        $this->assertStringContainsString("'reward_offers' =>", $game);
        $this->assertStringContainsString("'reward_upgrades_by_player' =>", $game);
        $this->assertStringContainsString('this.rewardOffersByPlayer = gamedatas.reward_offers || {}', $js);
        $this->assertStringContainsString('this.syncRewardForCurrentPlayer(gamedatas)', $js);
        $this->assertStringContainsString("if (stateName === 'upgradeReward')", $js);
        $this->assertStringContainsString('this.syncRewardForCurrentPlayer(stateArgs)', $js);
        $this->assertStringContainsString('this.applyRewardMapsFromEvent(event)', $js);
        $this->assertStringContainsString('this.rewardOffer = this.rewardOffersByPlayer[this.player_id] || source.reward_offer || []', $js);
    }

    public function testSkippingRewardOnlyClearsThatPlayersClientReward(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString("notifyAllPlayers('rewardSkipped'", $game);
        $this->assertStringContainsString("'reward_offers' => \$this->getCurrentRewardOffersByPlayer()", $game);
        $this->assertStringContainsString("'reward_upgrades_by_player' => \$this->getCurrentRewardUpgradesByPlayer()", $game);
        $this->assertStringContainsString("dojo.subscribe('rewardSkipped', this, 'notif_rewardSkipped')", $js);
        $this->assertStringContainsString('notif_rewardSkipped: function (notif)', $js);
        $this->assertStringContainsString('this.applyRewardMapsFromEvent(notif.args || {})', $js);
        $this->assertStringContainsString('String((notif.args || {}).player_id) === String(this.player_id)', $js);
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
        $this->assertStringContainsString("'name' => 'startNextLevel'", $states);
        $this->assertStringContainsString("'action' => 'stStartNextLevel'", $states);
        $this->assertStringContainsString("'nextLevel' => 66", $states);
        $this->assertStringContainsString('function actChooseReward()', $actions);
        $this->assertStringContainsString("\$_REQUEST['power_key']", $actions);
        $this->assertStringContainsString("preg_match('/\\A[A-Za-z0-9_-]*\\z/'", $actions);
        $this->assertStringContainsString('function actSkipReward()', $actions);
        $this->assertStringContainsString('public function actChooseReward', $game);
        $this->assertStringContainsString('public function actSkipReward', $game);
        $this->assertStringContainsString('argUpgradeReward', $game);
        $this->assertStringContainsString('stStartNextLevel', $game);
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
        $this->assertStringNotContainsString('hns_power_card_name', $js);
        $this->assertStringNotContainsString('.hns_power_card_name', $css);
        $this->assertStringContainsString('hns_card_zoom', $js);
        $this->assertStringContainsString('updatePowerCardZoom', $js);
        $this->assertStringContainsString('hns_cards_zoom_3', $css);
        $this->assertStringContainsString('isRewardReplacementSuggested', $js);
        $this->assertStringContainsString('hns_reward_replace_candidate', $js);
        $this->assertStringContainsString('.hns_power_card.hns_reward_replace_candidate', $css);
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
        $this->assertStringContainsString("'reward_pending' => \$this->isRewardPendingForPlayer(\$playerId) ? 1 : 0", $game);
        $this->assertStringContainsString("stateName === 'upgradeReward'", $js);
        $this->assertStringContainsString("this.addActionButton('hns_skip_reward_button', _('Skip'), 'onSkipReward')", $js);
        $this->assertStringContainsString("createSkipHandler('actSkipReward')", $js);
        $this->assertStringContainsString('onSkipReward', $js);
    }

    public function testBoardResizesToGeneratedTileGrid(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');

        $this->assertStringContainsString('resizeBoardToTiles', $js);
        $this->assertStringContainsString('this.resizeBoardToTiles(tiles)', $js);
        $this->assertStringContainsString('var TILE_SIZE = 70', $js);
        $this->assertStringContainsString('var BORDER_TILE_SIZE = 36', $js);
        $this->assertStringContainsString('tileBox', $js);
        $this->assertStringContainsString('boardAxisOffset', $js);
        $this->assertStringContainsString('tileBounds', $js);
        $this->assertStringContainsString("if (x === 0 && y === 0) { return 'tiles/levels/wall-top-left.webp'; }", $js);
        $this->assertStringContainsString("if (x === bounds.maxX && y === 0) { return 'tiles/levels/wall-top-right.webp'; }", $js);
        $this->assertStringContainsString("if (x === 0 && y === bounds.maxY) { return 'tiles/levels/wall-bottom-left.webp'; }", $js);
        $this->assertStringContainsString("if (x === bounds.maxX && y === bounds.maxY) { return 'tiles/levels/wall-bottom-right.webp'; }", $js);
        $this->assertStringContainsString('width:${width}px; height:${height}px;', $js);
        $this->assertStringContainsString("width: boardAxisOffset(bounds.maxX + 1, bounds.maxX) + 'px'", $js);
        $this->assertStringContainsString("height: boardAxisOffset(bounds.maxY + 1, bounds.maxY) + 'px'", $js);
        $this->assertStringContainsString('overflow: visible;', $css);
        $this->assertStringContainsString('width: 70px;', $css);
        $this->assertStringContainsString('height: 70px;', $css);
        $this->assertStringContainsString('background-size: 100% 100%;', $css);
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
        $this->assertStringContainsString('monsterSlime', $js);
        $this->assertStringContainsString('monsterCharge', $js);
        $this->assertStringContainsString('monsterFrontArc', $js);
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
        $boardRules = self::readFile(dirname(__DIR__) . '/modules/HNS_BoardRules.php');
        $this->assertStringContainsString('slimed', $boardRules);
        $this->assertStringContainsString('hasSlimeStatus', $js);
        $this->assertStringContainsString('slimed', $js);
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
        $this->assertStringContainsString('tiles/markers/thorns.webp', $js);
        $this->assertStringContainsString('hasThorns', $js);
        $this->assertStringContainsString("indexOf('thorns') !== -1", $js);
        $this->assertStringContainsString('this.intFlag(entity.has_shield) === 1', $js);
        $this->assertStringContainsString('this.intFlag(entity.shield_broken) !== 1', $js);
        $this->assertStringContainsString('intFlag: function (value)', $js);
        $this->assertStringContainsString('this.gamedatas.entities[entityId].shield_broken = 1;', $js);
        $this->assertStringNotContainsString("status.indexOf('shield') !== -1", $js);
        $this->assertStringNotContainsString("levelAbilities[i] === 'shield'", $js);
        $this->assertStringContainsString('updateEntityShieldFromEvent', $js);
        $this->assertStringContainsString("dojo.query('.hns_entity_shield_icon', node)", $js);
        $this->assertStringContainsString('dojo.destroy(shieldIcon)', $js);
        $this->assertStringNotContainsString('shield_state_by_entity_id', $js);
        $this->assertStringContainsString('this.hasActiveShield(entity)', $js);
        $this->assertStringContainsString("this.markPanelDirty('monsters')", $js);
        $this->assertStringContainsString('this.flushDirtyPanels();', $js);
        $this->assertStringNotContainsString('this.flushPanels()', $js);
        $this->assertStringContainsString("dojo.toggleClass(node, 'hns_entity_shielded', this.hasActiveShield(entity));", $js);
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');
        $this->assertStringContainsString('.hns_entity_shield_icon', $css);
        $this->assertStringContainsString('.hns_entity_shielded', $css);
        $this->assertStringContainsString('.hns_entity_thorns', $css);
        $this->assertStringContainsString('.hns_entity .hns_entity_thorns_icon', $css);
        $this->assertStringContainsString('rgba(128, 76, 36', $css);
        $this->assertStringContainsString('isActivePlayerMoveAvailable', $game);
        $this->assertStringContainsString('parseInt(player.action_points || 0, 10) > 0', $js);
        $this->assertStringContainsString("var actionPoints = (player.action_points || 0) + '/' + maxActionPoints;", $js);
        $this->assertStringNotContainsString("compact ? '-'", $js);
    }

    public function testAssetSyncExcludesNonBgaImageFolders(): void
    {
        $script = self::readFile(dirname(__DIR__, 2) . '/scripts/prepare-bga-assets.sh');

        $this->assertStringContainsString("--exclude 'illustrations/***'", $script);
        $this->assertStringContainsString("--exclude 'cards/placeholders/***'", $script);
        $this->assertStringContainsString("--exclude 'tiles/actions/***'", $script);
        $this->assertStringContainsString("--exclude 'tiles/free/***'", $script);
    }

    public function testEngineResolutionErrorsAreUserFacing(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('catch (InvalidArgumentException $e)', $game);
        $this->assertStringContainsString('throw new BgaUserException(clienttranslate($e->getMessage()))', $game);
    }
}
