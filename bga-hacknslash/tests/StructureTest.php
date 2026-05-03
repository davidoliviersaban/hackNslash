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
        $this->assertStringContainsString('this.moveEntityNode(entityIds[i], event.target_tile_id)', $js);
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

    public function testClientMarksKilledMonstersDeadOnBoardAndCards(): void
    {
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');
        $css = self::readFile(dirname(__DIR__) . '/hacknslash.css');

        $this->assertStringContainsString('markEntityDead', $js);
        $this->assertStringContainsString('updateEntityHealthBadge', $js);
        $this->assertStringContainsString("dojo.query('.hns_entity_health', node)", $js);
        $this->assertStringContainsString("event.type === 'afterKill'", $js);
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
        $this->assertStringContainsString('parseInt(player.free_move_available || 0, 10) === 1', $js);
        $this->assertStringContainsString('clearFreeMoveHighlights', $js);
        $this->assertStringContainsString('isFreeMoveTile', $js);
        $this->assertStringContainsString('hns_free_move_tile', $js);
        $this->assertStringContainsString('.hns_tile.hns_free_move_tile', $css);
        $this->assertStringContainsString('.hns_tile.hns_free_move_tile::after', $css);
    }

    public function testClientHighlightsPowerTargetsAndAutoTargetsVortex(): void
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
        $this->assertStringContainsString('maybePlayPullPower', $js);
        $this->assertStringContainsString('isTileValidPowerTarget', $js);
        $this->assertStringContainsString("(power.range_metric || 'orthogonal') === 'orthogonal'", $js);
        $this->assertStringContainsString('String(from.x) !== String(tile.x) && String(from.y) !== String(tile.y)', $js);
        $this->assertStringContainsString('monsterIdOnTile', $js);
        $this->assertStringContainsString('payload.target_entity_id = targetEntityId', $js);
        $this->assertStringContainsString('this.isWalkableTile(tile) && !this.isTileOccupied(tile.id)', $js);
        $this->assertStringContainsString('this.monsterIdsAdjacentToTile(tile.id).length > 0', $js);
        $this->assertStringContainsString("join(' ')", $js);
        $this->assertStringContainsString("this.selectedPowerTileId = String(entity.tile_id)", $js);
        $this->assertStringContainsString("preg_split('/\\s+/'", $actions);
        $this->assertStringContainsString('.hns_tile.hns_power_target_tile', $css);
        $this->assertStringContainsString('.hns_tile.hns_power_target_tile::after', $css);
        $this->assertStringContainsString('z-index: 3', $css);
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
        $this->assertStringContainsString('.hns_tile.hns_monster_attack_tile::after', $css);
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
        $this->assertStringContainsString('isHeroPhaseEndingAfterActiveTurn', $game);
        $this->assertStringContainsString('!$heroPhaseEnding', $game);
        $this->assertStringContainsString('if ($this->isActivePlayerTurnSpent())', $game);
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

        $this->assertStringContainsString('roundStarted', $player);
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
        $this->assertStringContainsString('applyLevelSnapshot', $js);
        $this->assertStringContainsString("event.type === 'levelStarted'", $js);
        $this->assertStringContainsString('this.renderBoard(this.gamedatas.tiles, this.gamedatas.entities)', $js);
    }

    public function testRewardChoiceCanReplaceOrUpgradeWithoutHidingHand(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $actions = self::readFile(dirname(__DIR__) . '/hacknslash.action.php');
        $states = self::readFile(dirname(__DIR__) . '/states.inc.php');
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('actChooseReward', $states);
        $this->assertStringContainsString('function actChooseReward()', $actions);
        $this->assertStringContainsString('public function actChooseReward', $game);
        $this->assertStringContainsString('HNS_LevelReward::takeOfferedPower', $game);
        $this->assertStringContainsString('HNS_LevelReward::upgradeExistingPower', $game);
        $this->assertStringContainsString('hns_reward_panel', $js);
        $this->assertStringContainsString('onRewardOfferClick', $js);
        $this->assertStringContainsString('onRewardUpgradeClick', $js);
        $this->assertStringContainsString('hns_reward_upgrade', $js);
        $this->assertStringContainsString('reward_upgrades', $game);
        $this->assertStringContainsString('onUpgradePowerClick', $js);
        $this->assertStringNotContainsString('this.renderRewardOffer();\n        return;\n      }\n\n      var activePlayerId', $js);
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
        $this->assertStringContainsString('Skip free move', $js);
        $this->assertStringContainsString('Skip action', $js);
    }

    public function testTurnActionsAreValidatedAgainstRemainingActionFlags(): void
    {
        $game = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');
        $js = self::readFile(dirname(__DIR__) . '/hacknslash.js');

        $this->assertStringContainsString('Move is not available.', $game);
        $this->assertStringContainsString('Main action is not available.', $game);
        $this->assertStringContainsString('HNS_RoundEngine::consumeMove', $game);
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
