<?php
/**
 * Main BGA server-side class for HackNSlash.
 */

require_once(APP_GAMEMODULE_PATH . 'module/table/table.game.php');

require_once(__DIR__ . '/modules/HNS_Setup.php');
require_once(__DIR__ . '/modules/HNS_Player.php');
require_once(__DIR__ . '/modules/HNS_Board.php');
require_once(__DIR__ . '/modules/HNS_LevelGenerator.php');
require_once(__DIR__ . '/modules/HNS_RoomSlotPattern.php');
require_once(__DIR__ . '/modules/HNS_MonsterAi.php');
require_once(__DIR__ . '/modules/HNS_LevelReward.php');
require_once(__DIR__ . '/modules/HNS_BossEngine.php');
require_once(__DIR__ . '/modules/HNS_GameEngine.php');
require_once(__DIR__ . '/modules/HNS_RoundEngine.php');

class Hacknslash extends Table
{
    use HNS_Setup;
    use HNS_Player;
    use HNS_Board;

    public function __construct()
    {
        parent::__construct();

        include_once(__DIR__ . '/material.inc.php');

        $this->cards = $this->deckFactory->createDeck('card');
        $this->cards->init('card');

        $this->initGameStateLabels([
            'current_level' => 10,
            'selected_tile' => 11,
            'pending_action' => 12,
            'round_number' => 13,
        ]);
    }

    protected function setupNewGame($players, $options = [])
    {
        $gameinfos = $this->getGameinfos();
        $defaultColors = $gameinfos['player_colors'];

        $values = [];
        foreach ($players as $playerId => $player) {
            $color = array_shift($defaultColors);
            $values[] = "('" . $playerId . "','" . $color . "','" . $player['player_canal'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "', 0)";
        }

        $this->DbQuery('INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar, player_score) VALUES ' . implode(',', $values));
        $this->reloadPlayersBasicInfos();

        $this->setGameStateInitialValue('current_level', HNS_FIRST_LEVEL);
        $this->setGameStateInitialValue('selected_tile', 0);
        $this->setGameStateInitialValue('pending_action', 0);
        $this->setGameStateInitialValue('round_number', 1);

        $this->setupStaticCards();
        $this->setupInitialBoard(HNS_FIRST_LEVEL);
        $this->initializePlayers(array_keys($players));

        $this->activeNextPlayer();

        return 10;
    }

    protected function getAllDatas()
    {
        $currentPlayerId = $this->getCurrentPlayerId();

        return [
            'players' => $this->getPlayersWithState(),
            'tiles' => $this->getTilesForLevel((int) $this->getGameStateValue('current_level')),
            'entities' => $this->getEntities(),
            'hand' => $this->cards->getCardsInLocation('hand_' . $currentPlayerId),
            'tile_types' => $this->tile_types,
            'monsters' => $this->monsters,
            'bonus_cards' => $this->bonus_cards,
            'player_powers' => $this->getPlayerPowers(),
            'level_monster_abilities' => $this->getLevelMonsterAbilities(),
        ];
    }

    public function getGameProgression(): int
    {
        return 0;
    }

    public function argPlayerTurn(): array
    {
        return [
            'action_points' => $this->getActivePlayerActionPoints(),
            'selected_tile' => (int) $this->getGameStateValue('selected_tile'),
        ];
    }

    public function stResolveAction(): void
    {
        // Rule resolution will be specified later. For now, every accepted action
        // returns control to the active player.
        $this->gamestate->nextState('continueTurn');
    }

    public function stCooldown(): void
    {
        $this->DbQuery('UPDATE player_power SET power_cooldown = power_cooldown - 1 WHERE power_cooldown > 0');
        $this->gamestate->nextState('activateTraps');
    }

    public function stActivateTraps(): void
    {
        $state = $this->loadEngineState();
        $result = HNS_RoundEngine::activateTraps($state);
        $this->persistEngineState($result['state']);
        if (HNS_RoundEngine::isGameLost($result['state'])) {
            $this->gamestate->nextState('gameEnd');
            return;
        }

        $this->gamestate->nextState('activateMonsters');
    }

    public function stActivateMonsters(): void
    {
        $state = $this->loadEngineState();
        $result = HNS_GameEngine::activateMonsters($state, $this->monsters);
        $this->persistEngineState($result['state']);
        if (HNS_RoundEngine::isGameLost($result['state'])) {
            $this->gamestate->nextState('gameEnd');
            return;
        }

        $this->gamestate->nextState('levelEndCheck');
    }

    public function stLevelEndCheck(): void
    {
        $state = $this->loadEngineState();
        if (!HNS_GameEngine::isLevelCleared($state)) {
            $this->resetRoundFlags();
            $this->gamestate->nextState('nextRound');
            return;
        }

        $level = (int) $this->getGameStateValue('current_level');
        if ($level >= HNS_BOSS_LEVEL) {
            $this->gamestate->nextState('gameEnd');
            return;
        }

        $this->setGameStateValue('current_level', $level + 1);
        $this->setupInitialBoard($level + 1);
        $this->moveHeroesToCurrentLevelEntry($level + 1);
        $this->resetRoundFlags();
        $this->gamestate->nextState('nextLevel');
    }

    public function stNextPlayer(): void
    {
        if ($this->areAllHeroActionsSpent()) {
            $this->gamestate->nextState('roundEnd');
            return;
        }

        $this->activeNextPlayer();
        $this->gamestate->nextState('nextTurn');
    }

    public function actMove(int $tile_id): void
    {
        $this->checkAction('actMove');

        $playerId = (int) $this->getActivePlayerId();
        $tileId = (int) $tile_id;
        $this->moveHeroToTile($playerId, $tileId);
        $this->DbQuery("UPDATE player SET player_free_move_available = 0 WHERE player_id = $playerId");

        $this->notifyAllPlayers('heroMoved', clienttranslate('${player_name} moves'), [
            'player_id' => $playerId,
            'player_name' => $this->getActivePlayerName(),
            'tile_id' => $tileId,
        ]);

        $this->gamestate->nextState('resolveAction');
    }

    public function actPlayCard(int $card_id): void
    {
        $this->checkAction('actPlayCard');
        $this->setGameStateValue('pending_action', (int) $card_id);
        $playerId = (int) $this->getActivePlayerId();
        $this->DbQuery("UPDATE player SET player_main_action_available = 0 WHERE player_id = $playerId");
        $this->gamestate->nextState('resolveAction');
    }

    public function actAttack(int $target_id): void
    {
        $this->checkAction('actAttack');
        $this->setGameStateValue('pending_action', (int) $target_id);
        $playerId = (int) $this->getActivePlayerId();
        $this->DbQuery("UPDATE player SET player_main_action_available = 0 WHERE player_id = $playerId");
        $this->gamestate->nextState('resolveAction');
    }

    public function actEndTurn(): void
    {
        $this->checkAction('actEndTurn');
        $this->gamestate->nextState('endTurn');
    }

    public function zombieTurn($state, $active_player): void
    {
        $statename = $state['name'];
        if ($state['type'] === 'activeplayer') {
            $this->gamestate->nextState('endTurn');
        } else {
            throw new feException('Zombie mode not supported at this game state: ' . $statename);
        }
    }
}
