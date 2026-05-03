<?php
/**
 * Main BGA server-side class for HackNSlash.
 */

require_once(__DIR__ . '/modules/HNS_DbHelpers.php');
require_once(__DIR__ . '/modules/HNS_EventDispatcher.php');
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
require_once(__DIR__ . '/modules/HNS_PowerResolver.php');
require_once(__DIR__ . '/modules/HNS_FreeActionEngine.php');
require_once(__DIR__ . '/modules/HNS_BoardRules.php');

class Hacknslash extends \Bga\GameFramework\Table
{
    use HNS_DbHelpers;
    use HNS_EventDispatcher;
    use HNS_Setup;
    use HNS_Player;
    use HNS_Board;

    public function __construct()
    {
        parent::__construct();

        include_once(__DIR__ . '/material.inc.php');

        $this->cards = $this->bga->deckFactory->createDeck('card');

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
            $playerIdInt = (int) $playerId;
            $canal = $this->hns_sql_escape((string) $player['player_canal']);
            $name = $this->hns_sql_escape((string) $player['player_name']);
            $avatar = $this->hns_sql_escape((string) $player['player_avatar']);
            $values[] = "($playerIdInt,'$color','$canal','$name','$avatar')";
        }

        $this->DbQuery('INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ' . implode(',', $values));
        $this->reloadPlayersBasicInfos();

        // Initialize scores using the framework counter (player_score column).
        foreach ($players as $playerId => $player) {
            $this->bga->playerScore->set((int) $playerId, 0);
        }

        $difficulty = (int) ($options[101] ?? HNS_DIFFICULTY_NORMAL);
        $startLevel = $difficulty === HNS_DIFFICULTY_BOSS_FIGHT ? HNS_BOSS_LEVEL : HNS_FIRST_LEVEL;
        $startingHealth = $difficulty === HNS_DIFFICULTY_BOSS_FIGHT ? HNS_BOSS_FIGHT_HEALTH : HNS_DEFAULT_HEALTH;
        $startingPowers = $difficulty === HNS_DIFFICULTY_BOSS_FIGHT ? $this->bossFightStartingPowers() : null;

        $this->setGameStateInitialValue('current_level', $startLevel);
        $this->setGameStateInitialValue('selected_tile', 0);
        $this->setGameStateInitialValue('pending_action', 0);
        $this->setGameStateInitialValue('round_number', 1);

        $this->setupStaticCards();
        $this->setupInitialBoard($startLevel);
        $this->initializePlayers(array_keys($players), $startLevel, $startingHealth, $startingPowers);

        $this->gamestate->changeActivePlayer((int) array_key_first($players));

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
            'free_action_events' => $this->getFreeActionEventTypes(),
            'level_monster_abilities' => $this->getLevelMonsterAbilities(),
            'reward_offer' => $this->getCurrentRewardOffer(),
            'reward_upgrades' => $this->getCurrentRewardUpgrades(),
        ];
    }

    public function getGameProgression(): int
    {
        $level = (int) $this->getGameStateValue('current_level');
        if ($level <= HNS_FIRST_LEVEL) {
            return 0;
        }
        if ($level >= HNS_BOSS_LEVEL) {
            return 100;
        }

        $progression = (int) floor((($level - HNS_FIRST_LEVEL) / (HNS_BOSS_LEVEL - HNS_FIRST_LEVEL)) * 100);

        return max(0, min(100, $progression));
    }

    public function argPlayerTurn(): array
    {
        return [
            'name' => clienttranslate('must choose an action'),
            'action_points' => $this->getActivePlayerActionPoints(),
            'selected_tile' => (int) $this->getGameStateValue('selected_tile'),
            'free_move_available' => $this->isActivePlayerFreeMoveAvailable(),
            'main_action_available' => $this->isActivePlayerMainActionAvailable(),
            'free_action_available' => $this->isActivePlayerFreeActionAvailable() ? 1 : 0,
            'free_action_events' => $this->getFreeActionEventTypes(),
        ];
    }

    public function argUpgradeReward(): array
    {
        return [
            'name' => clienttranslate('upgrade your character and pick a card'),
            'reward_pending' => $this->isRewardPending() ? 1 : 0,
            'reward_offer' => $this->getCurrentRewardOffer(),
            'reward_upgrades' => $this->getCurrentRewardUpgrades(),
        ];
    }

    public function stGameSetup(): void
    {
        // The full setup happens in setupNewGame(). This handler is kept so the
        // state machine has an explicit entry point for the gameSetup manager state.
    }

    public function stResolveAction(): void
    {
        // Rule resolution will be specified later. For now, every accepted action
        // returns control to the active player.
        $this->gamestate->nextState('continueTurn');
    }

    public function stCooldown(): void
    {
        $this->clearFreeActionChain();
        $this->DbQuery('UPDATE player_power SET power_cooldown = power_cooldown - 1 WHERE power_cooldown > 0');
        $this->notifyAllPlayers('powerCooldownsUpdated', '', [
            'player_powers' => $this->getPlayerPowers(),
            'free_action_events' => [],
        ]);
        $this->gamestate->nextState('activateTraps');
    }

    public function stActivateTraps(): void
    {
        $state = $this->loadEngineState();
        $result = HNS_RoundEngine::activateTraps($state);
        $this->persistEngineState($result['state']);
        $this->notifyEngineEvents($result['events'] ?? []);
        if (!empty($result['state']['game_won'])) {
            $this->scoreCooperativeVictory();
            $this->gamestate->nextState('gameEnd');
            return;
        }

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
        $this->notifyEngineEvents($result['events'] ?? []);
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
        $playerId = (int) $this->getActivePlayerId();
        $playerPowers = array_values($this->getCollectionFromDb("SELECT power_slot slot, power_key FROM player_power WHERE player_id = $playerId ORDER BY power_slot"));
        $remainingPowerDeck = $this->remainingPowerDeckKeys();
        $rewardOffer = HNS_LevelReward::drawOfferForPlayer($this->bonus_cards, $remainingPowerDeck, $playerPowers);
        $rewardUpgrades = HNS_LevelReward::drawUpgradeOfferForPlayer($this->bonus_cards, $remainingPowerDeck, $playerPowers);
        $this->saveCurrentRewardOffer($rewardOffer);
        $this->saveCurrentRewardUpgrades($rewardUpgrades);
        $this->notifyEngineEvents([['type' => 'levelCleared', 'level' => $level, 'reward_offer' => $rewardOffer, 'reward_upgrades' => $rewardUpgrades]]);

        if ($level >= HNS_BOSS_LEVEL) {
            $this->notifyEngineEvents([['type' => 'gameWon']]);
            $this->scoreCooperativeVictory();
            $this->gamestate->nextState('gameEnd');
            return;
        }

        $this->gamestate->nextState('upgradeReward');
    }

    public function stNextPlayer(): void
    {
        $state = $this->loadEngineState();
        $nextPlayerId = HNS_RoundEngine::nextPlayerWithActions($state, (int) $this->getActivePlayerId());
        if ($nextPlayerId === null) {
            $this->gamestate->nextState('roundEnd');
            return;
        }

        $this->gamestate->changeActivePlayer($nextPlayerId);
        $this->gamestate->nextState('nextTurn');
    }

    public function actMove(int $tile_id): void
    {
        $this->checkAction('actMove');
        if (!$this->isActivePlayerMoveAvailable()) {
            throw new BgaUserException(clienttranslate('Move is not available.'));
        }

        $playerId = (int) $this->getActivePlayerId();
        $tileId = (int) $tile_id;
        $entityId = $this->getHeroEntityIdForPlayer($playerId);
        $this->assertFreeMoveTarget($entityId, $tileId);
        try {
            $state = HNS_RoundEngine::consumeMove($this->loadEngineState(), $playerId);
        } catch (InvalidArgumentException $e) {
            throw new BgaUserException(clienttranslate($e->getMessage()));
        }
        if (isset($state['entities'][$entityId])) {
            $state['entities'][$entityId]['tile_id'] = $tileId;
        }
        $this->persistPlayerActionFlags($state['players'][$playerId]);
        $this->persistEngineState($state);
        $this->syncPlayerPositionFromTile($playerId, $tileId);
        $this->startFreeActionChain([['type' => HNS_FreeActionEngine::EVENT_AFTER_MOVE, 'source_entity_id' => $entityId, 'target_tile_id' => $tileId]]);

        $this->notifyAllPlayers('heroMoved', clienttranslate('${player_name} moves'), [
            'entity_id' => $entityId,
            'player_id' => $playerId,
            'player_name' => $this->getActivePlayerName(),
            'tile_id' => $tileId,
            'action_points' => (int) $state['players'][$playerId]['action_points'],
            'free_move_available' => !empty($state['players'][$playerId]['free_move_available']) ? 1 : 0,
            'main_action_available' => !empty($state['players'][$playerId]['main_action_available']) ? 1 : 0,
            'free_action_available' => $this->isPlayerFreeActionAvailable($playerId) ? 1 : 0,
            'free_action_events' => $this->getFreeActionEventTypes(),
        ]);

        if ($this->shouldEndActivePlayerTurn($playerId)) {
            $this->gamestate->nextState('endTurn');
            return;
        }

        $this->gamestate->nextState('continueTurn');
    }

    public function actPlayCard(int $card_id, array $payload = []): void
    {
        $this->checkAction('actPlayCard');

        $playerId = (int) $this->getActivePlayerId();
        $powerId = (int) $card_id;
        $power = $this->getObjectFromDB("SELECT power_key, power_cooldown FROM player_power WHERE player_power_id = $powerId AND player_id = $playerId");
        if (!$power) {
            throw new BgaUserException(clienttranslate('Card is not in your hand.'));
        }

        $powerKey = (string) $power['power_key'];
        if (!isset($this->bonus_cards[$powerKey])) {
            throw new BgaUserException(clienttranslate('Unknown card.'));
        }

        if ((int) $power['power_cooldown'] > 0) {
            throw new BgaUserException(clienttranslate('Card is on cooldown.'));
        }

        $isFree = $this->isFreePowerAvailable($powerKey, (int) $power['power_cooldown'], $playerId);
        if (!$isFree && !$this->isActivePlayerMainActionAvailable()) {
            throw new BgaUserException(clienttranslate('Main action is not available.'));
        }

        $events = $this->resolvePowerForActivePlayer($powerKey, $payload);

        $cooldown = $this->cooldownAfterPowerUse($powerKey, $isFree);
        $this->DbQuery("UPDATE player_power SET power_cooldown = $cooldown WHERE player_power_id = $powerId");
        if ($isFree) {
            $this->consumeFreePower($powerKey, $events, $cooldown);
            $this->DbQuery("UPDATE player SET player_free_move_available = 0 WHERE player_id = $playerId");
        } else {
            $this->consumeActivePlayerMainActionPoint($playerId);
            $this->startFreeActionChain($events);
        }
        $shouldEndTurn = $this->shouldEndActivePlayerTurn($playerId);
        $bossPhaseStarted = $this->hasBossPhaseStarted($events);
        $gameWon = $this->hasGameWon($events);
        $levelCleared = $this->isCurrentLevelCleared();
        if (!$shouldEndTurn && !$bossPhaseStarted && !$gameWon) {
            $this->notifyPlayerActionState($playerId, $powerId, $cooldown, $powerKey);
        }

        if ($gameWon) {
            $this->scoreCooperativeVictory();
            $this->gamestate->nextState('gameEnd');
            return;
        }

        if ($bossPhaseStarted) {
            $this->gamestate->nextState('roundEnd');
            return;
        }

        if ($levelCleared) {
            $this->gamestate->nextState('levelEndCheck');
            return;
        }

        if ($shouldEndTurn) {
            $this->gamestate->nextState('endTurn');
            return;
        }

        $this->gamestate->nextState('resolveAction');
    }

    public function actAttack(int $target_id): void
    {
        $this->checkAction('actAttack');
        if (!$this->isActivePlayerMainActionAvailable()) {
            throw new BgaUserException(clienttranslate('Main action is not available.'));
        }

        $events = $this->resolvePowerForActivePlayer('attack', ['target_entity_id' => (int) $target_id]);

        $playerId = (int) $this->getActivePlayerId();
        $this->consumeActivePlayerMainActionPoint($playerId);
        $this->startFreeActionChain($events);
        $shouldEndTurn = $this->shouldEndActivePlayerTurn($playerId);
        $bossPhaseStarted = $this->hasBossPhaseStarted($events);
        $gameWon = $this->hasGameWon($events);
        $levelCleared = $this->isCurrentLevelCleared();
        if (!$shouldEndTurn && !$bossPhaseStarted && !$gameWon) {
            $this->notifyPlayerActionState($playerId, 0, 0);
        }

        if ($gameWon) {
            $this->scoreCooperativeVictory();
            $this->gamestate->nextState('gameEnd');
            return;
        }

        if ($bossPhaseStarted) {
            $this->gamestate->nextState('roundEnd');
            return;
        }

        if ($levelCleared) {
            $this->gamestate->nextState('levelEndCheck');
            return;
        }

        if ($shouldEndTurn) {
            $this->gamestate->nextState('endTurn');
            return;
        }

        $this->gamestate->nextState('resolveAction');
    }

    private function consumeActivePlayerMainActionPoint(int $playerId): void
    {
        $actionPoints = max(0, (int) $this->getUniqueValueFromDB("SELECT player_action_points FROM player WHERE player_id = $playerId") - 1);
        $mainActionAvailable = $actionPoints > 0 ? 1 : 0;
        $this->DbQuery("UPDATE player SET player_action_points = $actionPoints, player_main_action_available = $mainActionAvailable, player_free_move_available = 0 WHERE player_id = $playerId");
    }

    private function cooldownAfterPowerUse(string $powerKey, bool $isFree): int
    {
        $power = $this->bonus_cards[$powerKey] ?? [];
        if (($power['effect'] ?? null) === 'dash_attack' && (int) ($power['plays'] ?? 1) > 1 && !$isFree) {
            return 0;
        }

        return (int) ($power['cooldown'] ?? 0);
    }

    private function isFreePowerAvailable(string $powerKey, int $cooldown, int $playerId): bool
    {
        $freeTriggers = $this->bonus_cards[$powerKey]['free_triggers'] ?? [];
        if (!is_array($freeTriggers) || $freeTriggers === []) {
            return false;
        }

        $chain = $this->loadFreeActionChain();
        $engine = new HNS_FreeActionEngine($chain['active_event_chain'], $chain['used_action_keys']);
        return $engine->canUseFreeAction($powerKey, $freeTriggers, $cooldown);
    }

    /** @param array<int, array<string, mixed>> $events */
    private function consumeFreePower(string $powerKey, array $events, int $cooldown): void
    {
        $chain = $this->loadFreeActionChain();
        $engine = new HNS_FreeActionEngine($chain['active_event_chain'], $chain['used_action_keys']);
        $result = $engine->useFreeAction($powerKey, $this->bonus_cards[$powerKey]['free_triggers'] ?? [], 0, $cooldown, $events);
        $this->saveFreeActionChain($result['active_event_chain'], $result['used_action_keys']);
    }

    /** @param array<int, array<string, mixed>> $events */
    private function startFreeActionChain(array $events): void
    {
        $this->saveFreeActionChain($events, []);
    }

    /**
     * @return array{active_event_chain: array<int, array<string, mixed>>, used_action_keys: array<int, string>}
     */
    protected function loadFreeActionChain(): array
    {
        $row = $this->getObjectFromDB('SELECT active_event_chain, used_action_keys FROM free_chain ORDER BY chain_id DESC LIMIT 1');
        if (!$row) {
            return ['active_event_chain' => [], 'used_action_keys' => []];
        }

        $events = json_decode((string) ($row['active_event_chain'] ?? '[]'), true);
        $used = json_decode((string) ($row['used_action_keys'] ?? '[]'), true);

        return [
            'active_event_chain' => is_array($events) ? array_values($events) : [],
            'used_action_keys' => is_array($used) ? array_values(array_filter($used, 'is_string')) : [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @param array<int, string> $usedActionKeys
     */
    protected function saveFreeActionChain(array $events, array $usedActionKeys): void
    {
        $eventsJson = $this->hns_sql_escape((string) json_encode(array_values($events)));
        $usedJson = $this->hns_sql_escape((string) json_encode(array_values($usedActionKeys)));
        $this->DbQuery('DELETE FROM free_chain');
        $this->DbQuery("INSERT INTO free_chain (active_event_chain, used_action_keys, passed_player_ids) VALUES ('$eventsJson', '$usedJson', '[]')");
    }

    protected function clearFreeActionChain(): void
    {
        $this->DbQuery('DELETE FROM free_chain');
    }

    private function shouldEndActivePlayerTurn(int $playerId): bool
    {
        return $this->isActivePlayerTurnSpent() && !$this->isPlayerFreeActionAvailable($playerId);
    }

    private function isActivePlayerFreeActionAvailable(): bool
    {
        return $this->isPlayerFreeActionAvailable((int) $this->getActivePlayerId());
    }

    /** @param array<int, array<string, mixed>> $events */
    private function hasBossPhaseStarted(array $events): bool
    {
        foreach ($events as $event) {
            if (($event['type'] ?? null) === 'bossPhaseStarted') {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $events */
    private function hasGameWon(array $events): bool
    {
        foreach ($events as $event) {
            if (($event['type'] ?? null) === 'gameWon') {
                return true;
            }
        }

        return false;
    }

    private function scoreCooperativeVictory(): void
    {
        foreach ($this->getCollectionFromDb('SELECT player_id id FROM player') as $player) {
            $this->bga->playerScore->set((int) $player['id'], 1);
        }
    }

    private function isCurrentLevelCleared(): bool
    {
        return HNS_GameEngine::isLevelCleared($this->loadEngineState());
    }

    private function isPlayerFreeActionAvailable(int $playerId): bool
    {
        $powers = $this->getCollectionFromDb("SELECT power_key, power_cooldown cooldown FROM player_power WHERE player_id = $playerId");
        foreach ($powers as $power) {
            $powerKey = (string) ($power['power_key'] ?? '');
            if ($powerKey !== '' && isset($this->bonus_cards[$powerKey]) && $this->isFreePowerAvailable($powerKey, (int) ($power['cooldown'] ?? 0), $playerId)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function remainingPowerDeckKeys(): array
    {
        $this->ensureMissingRewardCardsInDeck();
        $cards = $this->cards->getCardsInLocation('deck');
        $powerKeys = [];
        foreach ($cards as $card) {
            $powerKey = $this->powerKeyForTypeArg((int) ($card['type_arg'] ?? 0));
            if ($powerKey !== null) {
                $powerKeys[] = $powerKey;
            }
        }

        shuffle($powerKeys);
        return $powerKeys;
    }

    private function ensureMissingRewardCardsInDeck(): void
    {
        $existingPowerKeys = [];
        foreach (['deck', 'discard'] as $location) {
            foreach ($this->cards->getCardsInLocation($location) as $card) {
                $powerKey = $this->powerKeyForTypeArg((int) ($card['type_arg'] ?? 0));
                if ($powerKey !== null) {
                    $existingPowerKeys[] = $powerKey;
                }
            }
        }

        foreach ($this->getPlayerPowers() as $playerPower) {
            $existingPowerKeys[] = (string) ($playerPower['power_key'] ?? '');
        }

        $existingPowerKeys = array_values(array_unique($existingPowerKeys));
        foreach (array_values(array_keys($this->bonus_cards)) as $index => $powerKey) {
            if ((int) ($this->bonus_cards[$powerKey]['rank'] ?? 0) < 1 || in_array($powerKey, $existingPowerKeys, true)) {
                continue;
            }

            $this->cards->createCards([['type' => 'bonus', 'type_arg' => $index + 1, 'nbr' => 1]], 'deck');
            $existingPowerKeys[] = $powerKey;
        }
    }

    private function removePowerFromDeck(string $powerKey): void
    {
        foreach ($this->cards->getCardsInLocation('deck') as $card) {
            if ($this->powerKeyForTypeArg((int) ($card['type_arg'] ?? 0)) !== $powerKey) {
                continue;
            }

            $cardId = (int) ($card['id'] ?? 0);
            if ($cardId > 0) {
                $this->cards->moveCard($cardId, 'discard');
            }
            return;
        }
    }

    /** @return list<string> */
    private function getFreeActionEventTypes(): array
    {
        $chain = $this->loadFreeActionChain();
        $types = [];
        foreach ($chain['active_event_chain'] as $event) {
            if (isset($event['type']) && is_string($event['type'])) {
                $types[] = $event['type'];
            }
        }
        return array_values(array_unique($types));
    }

    private function notifyPlayerActionState(int $playerId, int $powerId, int $cooldown, string $powerKey = ''): void
    {
        $player = $this->getObjectFromDB("SELECT player_action_points action_points, player_free_move_available free_move_available, player_main_action_available main_action_available FROM player WHERE player_id = $playerId LIMIT 1");
        $this->notifyAllPlayers('playerActionState', '', [
            'player_id' => $playerId,
            'action_points' => (int) ($player['action_points'] ?? 0),
            'free_move_available' => (int) ($player['free_move_available'] ?? 0),
            'main_action_available' => (int) ($player['main_action_available'] ?? 0),
            'free_action_available' => $this->isPlayerFreeActionAvailable($playerId) ? 1 : 0,
            'player_power_id' => $powerId,
            'power_key' => $powerKey,
            'power_cooldown' => $cooldown,
            'free_action_events' => $this->getFreeActionEventTypes(),
        ]);
    }

    private function isHeroPhaseEndingAfterActiveTurn(int $playerId): bool
    {
        if (!$this->isActivePlayerTurnSpent()) {
            return false;
        }

        return HNS_RoundEngine::nextPlayerWithActions($this->loadEngineState(), $playerId) === null;
    }

    /**
     * Resolve a power on behalf of the currently active player: locate their
     * hero entity, run the engine, persist the resulting state and forward
     * events as BGA notifications.
     *
     * @param array<string, mixed> $payload
     */
    private function resolvePowerForActivePlayer(string $powerKey, array $payload): array
    {
        if (!isset($this->bonus_cards[$powerKey])) {
            throw new BgaUserException(clienttranslate('Unknown power.'));
        }

        $playerId = (int) $this->getActivePlayerId();
        $state = $this->loadEngineState();
        $heroEntityId = $this->findHeroEntityIdForPlayer($state, $playerId);
        if ($heroEntityId === null) {
            throw new BgaUserException(clienttranslate('Active player has no hero on the board.'));
        }

        try {
            $result = HNS_PowerResolver::resolve($powerKey, $heroEntityId, $payload, $state, $this->bonus_cards);
        } catch (InvalidArgumentException $e) {
            throw new BgaUserException(clienttranslate($e->getMessage()));
        }

        $this->persistEngineState($result['state']);
        $this->notifyEngineEvents($result['events']);

        return $result['events'];
    }

    /**
     * Find the hero entity id owned by the given player in the engine state.
     */
    private function findHeroEntityIdForPlayer(array $state, int $playerId): ?int
    {
        foreach ($state['entities'] ?? [] as $entityId => $entity) {
            if (($entity['type'] ?? '') === 'hero' && (int) ($entity['owner'] ?? 0) === $playerId) {
                return (int) $entityId;
            }
        }

        return null;
    }

    public function actEndTurn(): void
    {
        $this->checkAction('actEndTurn');
        $playerId = (int) $this->getActivePlayerId();
        $this->clearFreeActionChain();
        $state = HNS_RoundEngine::endPlayerTurn($this->loadEngineState(), $playerId);
        $this->persistPlayerActionFlags($state['players'][$playerId]);
        $this->gamestate->nextState('endTurn');
    }

    public function actChooseReward(string $mode, int $slot, string $powerKey): void
    {
        $this->checkAction('actChooseReward');
        $playerId = (int) $this->getActivePlayerId();
        $playerPowers = array_values($this->getCollectionFromDb("SELECT player_power_id id, power_slot slot, power_key, power_cooldown cooldown FROM player_power WHERE player_id = $playerId ORDER BY power_slot"));
        $offer = $this->getCurrentRewardOffer();
        $upgradeOffer = $this->getCurrentRewardUpgrades();

        try {
            if ($mode === 'replace') {
                $updatedPowers = HNS_LevelReward::takeOfferedPower($playerPowers, $slot, $powerKey, $offer);
            } elseif ($mode === 'upgrade') {
                if (!in_array($slot, array_map(static fn (array $upgrade): int => (int) $upgrade['slot'], $upgradeOffer), true)) {
                    throw new InvalidArgumentException('Power upgrade is not in the level reward offer.');
                }
                $updatedPowers = HNS_LevelReward::upgradeExistingPower($playerPowers, $slot, $this->bonus_cards);
            } else {
                throw new InvalidArgumentException('Unknown reward choice.');
            }
        } catch (InvalidArgumentException $e) {
            throw new BgaUserException(clienttranslate($e->getMessage()));
        }

        $chosenPowerKey = $powerKey;
        foreach ($updatedPowers as $playerPower) {
            $powerSlot = (int) $playerPower['slot'];
            $updatedPowerKey = $this->hns_sql_escape((string) $playerPower['power_key']);
            $this->DbQuery("UPDATE player_power SET power_key = '$updatedPowerKey', power_cooldown = 0 WHERE player_id = $playerId AND power_slot = $powerSlot");
            if ($powerSlot === $slot) {
                $chosenPowerKey = (string) $playerPower['power_key'];
            }
        }

        $this->removePowerFromDeck($chosenPowerKey);

        $this->saveCurrentRewardOffer([]);
        $this->saveCurrentRewardUpgrades([]);
        $this->notifyAllPlayers('rewardChosen', clienttranslate('${player_name} chooses a reward'), [
            'player_id' => $playerId,
            'player_name' => $this->getActivePlayerName(),
            'mode' => $mode,
            'slot' => $slot,
            'power_key' => $chosenPowerKey,
        ]);
        $this->startNextLevelAfterReward();
    }

    public function actSkipReward(): void
    {
        $this->checkAction('actSkipReward');
        $this->saveCurrentRewardOffer([]);
        $this->saveCurrentRewardUpgrades([]);
        $this->startNextLevelAfterReward();
    }

    public function actSkipFreeMove(): void
    {
        $this->checkAction('actSkipFreeMove');
        $playerId = (int) $this->getActivePlayerId();
        $this->DbQuery("UPDATE player SET player_free_move_available = 0 WHERE player_id = $playerId");
        if ($this->isActivePlayerTurnSpent()) {
            $this->clearFreeActionChain();
        }
        $this->nextStateAfterOptionalActionSkip();
    }

    public function actSkipMainAction(): void
    {
        $this->checkAction('actSkipMainAction');
        $playerId = (int) $this->getActivePlayerId();
        $this->DbQuery("UPDATE player SET player_main_action_available = 0, player_action_points = 0, player_free_move_available = 0 WHERE player_id = $playerId");
        $this->nextStateAfterOptionalActionSkip();
    }

    private function nextStateAfterOptionalActionSkip(): void
    {
        if ($this->isActivePlayerTurnSpent()) {
            $this->gamestate->nextState('endTurn');
            return;
        }

        $this->gamestate->nextState('continueTurn');
    }

    private function startNextLevelAfterReward(): void
    {
        $level = (int) $this->getGameStateValue('current_level');
        if ($level >= HNS_BOSS_LEVEL) {
            $this->gamestate->nextState('gameEnd');
            return;
        }

        $nextLevel = $level + 1;
        $this->setGameStateValue('current_level', $nextLevel);
        $this->setupInitialBoard($nextLevel);
        $this->moveHeroesToLevelStarts($nextLevel);
        $this->deleteMonstersOutsideLevel($nextLevel);
        $this->DbQuery('UPDATE player_power SET power_cooldown = 0');
        $this->resetRoundFlags();
        $this->notifyEngineEvents([[
            'type' => 'levelStarted',
            'level' => $nextLevel,
            'is_boss_level' => $nextLevel >= HNS_BOSS_LEVEL,
            'tiles' => $this->getTilesForLevel($nextLevel),
            'entities' => $this->getEntities(),
            'players' => $this->getPlayersWithState(),
            'player_powers' => $this->getPlayerPowers(),
            'level_monster_abilities' => $this->getLevelMonsterAbilities(),
        ]]);
        $this->gamestate->nextState('nextLevel');
    }

    private function getHeroEntityIdForPlayer(int $playerId): int
    {
        return (int) $this->getUniqueValueFromDB("SELECT entity_id FROM entity WHERE entity_type = 'hero' AND entity_owner = $playerId ORDER BY entity_id LIMIT 1");
    }

    private function assertFreeMoveTarget(int $entityId, int $tileId): void
    {
        $from = $this->getObjectFromDB("SELECT t.tile_x x, t.tile_y y FROM entity e JOIN tile t ON t.tile_id = e.entity_tile_id WHERE e.entity_id = $entityId LIMIT 1");
        $to = $this->getObjectFromDB("SELECT tile_x x, tile_y y, tile_type type FROM tile WHERE tile_id = $tileId LIMIT 1");

        if (!$from || !$to || !HNS_BoardRules::isExactStep($from, $to, 1)) {
            throw new BgaUserException(clienttranslate('Free move is limited to one orthogonal step.'));
        }
        if (!HNS_BoardRules::isTileWalkable($to)) {
            throw new BgaUserException(clienttranslate('Move target is not available.'));
        }
    }

    /** @param array<string, mixed> $player */
    private function persistPlayerActionFlags(array $player): void
    {
        $playerId = (int) $player['id'];
        $freeMove = !empty($player['free_move_available']) ? 1 : 0;
        $actionPoints = (int) ($player['action_points'] ?? (!empty($player['main_action_available']) ? 1 : 0));
        $mainAction = $actionPoints > 0 ? 1 : 0;
        $this->DbQuery("UPDATE player SET player_free_move_available = $freeMove, player_main_action_available = $mainAction, player_action_points = $actionPoints WHERE player_id = $playerId");
    }

    /** @return list<string> */
    private function getCurrentRewardOffer(): array
    {
        $json = $this->getUniqueValueFromDB("SELECT var_value FROM global_var WHERE var_name = 'reward_offer'");
        if (!is_string($json) || $json === '') {
            return [];
        }

        $offer = json_decode($json, true);
        return is_array($offer) ? array_values(array_filter($offer, 'is_string')) : [];
    }

    /** @param list<string> $offer */
    private function saveCurrentRewardOffer(array $offer): void
    {
        $json = $this->hns_sql_escape((string) json_encode(array_values($offer)));
        $this->DbQuery("REPLACE INTO global_var (var_name, var_value) VALUES ('reward_offer', '$json')");
    }

    /** @return list<array{slot:int, from:string, to:string}> */
    private function getCurrentRewardUpgrades(): array
    {
        $json = $this->getUniqueValueFromDB("SELECT var_value FROM global_var WHERE var_name = 'reward_upgrades'");
        if (!is_string($json) || $json === '') {
            return [];
        }

        $upgrades = json_decode($json, true);
        return is_array($upgrades) ? array_values(array_filter($upgrades, static fn ($upgrade): bool => is_array($upgrade))) : [];
    }

    private function isRewardPending(): bool
    {
        return $this->getCurrentRewardOffer() !== [] || $this->getCurrentRewardUpgrades() !== [];
    }

    /** @param list<array{slot:int, from:string, to:string}> $upgrades */
    private function saveCurrentRewardUpgrades(array $upgrades): void
    {
        $json = $this->hns_sql_escape((string) json_encode(array_values($upgrades)));
        $this->DbQuery("REPLACE INTO global_var (var_name, var_value) VALUES ('reward_upgrades', '$json')");
    }

    public function zombieTurn($state, $active_player): void
    {
        $statename = $state['name'];
        if ($state['type'] === 'activeplayer') {
            $engineState = HNS_RoundEngine::endPlayerTurn($this->loadEngineState(), (int) $active_player);
            $this->persistPlayerActionFlags($engineState['players'][(int) $active_player]);
            $this->gamestate->nextState('endTurn');
        } else {
            throw new feException('Zombie mode not supported at this game state: ' . $statename);
        }
    }
}
