<?php
/**
 * Main BGA server-side class for HackNSlash.
 */

require_once(__DIR__ . '/modules/HNS_DbHelpers.php');
require_once(__DIR__ . '/modules/HNS_EventDispatcher.php');
require_once(__DIR__ . '/modules/HNS_Setup.php');
require_once(__DIR__ . '/modules/HNS_Player.php');
require_once(__DIR__ . '/modules/HNS_Board.php');
require_once(__DIR__ . '/modules/HNS_SeededRandom.php');
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
        $this->gamestate->setAllPlayersMultiactive();

        return 10;
    }

    protected function getAllDatas()
    {
        $currentPlayerId = $this->currentPlayerIdOrFirstPlayerId();

        return [
            'players' => $this->getPlayersWithState(),
            'tiles' => $this->getTilesForLevel((int) $this->getGameStateValue('current_level')),
            'entities' => $this->getEntities(),
            'hand' => $this->cards->getCardsInLocation('hand_' . $currentPlayerId),
            'tile_types' => $this->tile_types,
            'monsters' => $this->monsters,
            'bonus_cards' => $this->bonus_cards,
            'bosses' => $this->bosses ?? [],
            'player_powers' => $this->getPlayerPowers(),
            'free_action_events' => $this->getFreeActionEventTypes(),
            'level_monster_abilities' => $this->getLevelMonsterAbilities(),
            'reward_offer' => $this->getCurrentRewardOfferForPlayer((int) $currentPlayerId),
            'reward_upgrades' => $this->getCurrentRewardUpgradesForPlayer((int) $currentPlayerId),
            'reward_offers' => $this->getCurrentRewardOffersByPlayer(),
            'reward_upgrades_by_player' => $this->getCurrentRewardUpgradesByPlayer(),
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
        $playerId = $this->currentPlayerIdOrFirstPlayerId();

        return [
            'name' => clienttranslate('upgrade your character and pick a card'),
            'reward_pending' => $this->isRewardPendingForPlayer($playerId) ? 1 : 0,
            'reward_offer' => $this->getCurrentRewardOfferForPlayer($playerId),
            'reward_upgrades' => $this->getCurrentRewardUpgradesForPlayer($playerId),
            'reward_offers' => $this->getCurrentRewardOffersByPlayer(),
            'reward_upgrades_by_player' => $this->getCurrentRewardUpgradesByPlayer(),
        ];
    }

    public function stGameSetup(): void
    {
        $this->gamestate->setAllPlayersMultiactive();
    }

    public function stResolveAction(): void
    {
        $this->gamestate->nextState('continueTurn');
    }

    public function stEnterPlayerTurn(): void
    {
        $this->gamestate->setPlayersMultiactive(array_keys($this->playersWithAvailableActions()), 'roundEnd', true);
    }

    public function stEnterUpgradeReward(): void
    {
        $this->gamestate->setPlayersMultiactive(array_keys($this->playersWithPendingRewards()), 'nextLevel', true);
    }

    public function stStartNextLevel(): void
    {
        $this->startNextLevelAfterReward();
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
        $slimeCleanup = HNS_RoundEngine::clearExpiredSlimedStatusesWithEvents($result['state']);
        $result['state'] = $slimeCleanup['state'];
        $result['events'] = array_merge($result['events'] ?? [], $slimeCleanup['events']);
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
        $slimeCleanup = HNS_RoundEngine::clearExpiredSlimedStatusesWithEvents($result['state']);
        $result['state'] = $slimeCleanup['state'];
        $result['events'] = array_merge($result['events'] ?? [], $slimeCleanup['events']);
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
            $this->gamestate->setAllPlayersMultiactive();
            $this->gamestate->nextState('nextRound');
            return;
        }

        $level = (int) $this->getGameStateValue('current_level');
        $remainingPowerDeck = $this->remainingPowerDeckKeys();
        $rewardOffers = $this->buildRewardOffersByPlayer($remainingPowerDeck);
        $rewardUpgrades = $this->buildRewardUpgradesByPlayer($remainingPowerDeck);
        $this->saveCurrentRewardOffersByPlayer($rewardOffers);
        $this->saveCurrentRewardUpgradesByPlayer($rewardUpgrades);
        $this->notifyEngineEvents([[
            'type' => 'levelCleared',
            'level' => $level,
            'reward_offer' => [],
            'reward_upgrades' => [],
            'reward_offers' => $rewardOffers,
            'reward_upgrades_by_player' => $rewardUpgrades,
        ]]);

        if ($level >= HNS_BOSS_LEVEL) {
            $this->notifyEngineEvents([['type' => 'gameWon']]);
            $this->scoreCooperativeVictory();
            $this->gamestate->nextState('gameEnd');
            return;
        }

        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->nextState('upgradeReward');
    }

    public function stNextPlayer(): void
    {
        $state = $this->loadEngineState();
        $nextPlayerId = HNS_RoundEngine::nextPlayerWithActions($state, $this->currentPlayerIdOrFirstPlayerId());
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

        $playerId = (int) $this->getCurrentPlayerId();
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
            'player_name' => $this->getPlayerNameById($playerId),
            'tile_id' => $tileId,
            'action_points' => (int) $state['players'][$playerId]['action_points'],
            'free_move_available' => !empty($state['players'][$playerId]['free_move_available']) ? 1 : 0,
            'main_action_available' => !empty($state['players'][$playerId]['main_action_available']) ? 1 : 0,
            'free_action_available' => $this->isPlayerFreeActionAvailable($playerId) ? 1 : 0,
            'free_action_events' => $this->getFreeActionEventTypes(),
        ]);

        $this->nextStateAfterHeroAction($playerId);
    }

    public function actPlayCard(int $card_id, array $payload = []): void
    {
        $this->checkAction('actPlayCard');

        $playerId = (int) $this->getCurrentPlayerId();
        $powerId = (int) $card_id;
        $power = $this->getObjectFromDB("SELECT power_key, power_cooldown, power_plays_remaining FROM player_power WHERE player_power_id = $powerId AND player_id = $playerId");
        if (!$power) {
            throw new BgaUserException(clienttranslate('Card is not in your hand.'));
        }

        $powerKey = (string) $power['power_key'];
        if (!isset($this->bonus_cards[$powerKey])) {
            throw new BgaUserException(clienttranslate('Unknown card.'));
        }

        $currentCooldown = (int) $power['power_cooldown'];
        $playsRemaining = (int) ($power['power_plays_remaining'] ?? 0);
        // The cooldown is posted on the very first play of a multi-plays power.
        // We still let the player chain the remaining plays as long as
        // power_plays_remaining is positive; the chain itself is gated by
        // the matching free_triggers event in the active event chain.
        if ($currentCooldown > 0 && $playsRemaining <= 0) {
            throw new BgaUserException(clienttranslate('Card is on cooldown.'));
        }

        $isFree = $this->isFreePowerAvailable($powerKey, $currentCooldown, $playerId, $playsRemaining);
        if (!$isFree && !$this->isActivePlayerMainActionAvailable()) {
            throw new BgaUserException(clienttranslate('Main action is not available.'));
        }

        $events = $this->resolvePowerForActivePlayer($powerKey, $payload);

        $isChainedPlay = $playsRemaining > 0;
        $cooldown = $isChainedPlay ? $currentCooldown : $this->cooldownAfterPowerUse($powerKey, $isFree);
        $newPlaysRemaining = $this->playsRemainingAfterUse($powerKey, $playsRemaining);
        $this->DbQuery("UPDATE player_power SET power_cooldown = $cooldown, power_plays_remaining = $newPlaysRemaining WHERE player_power_id = $powerId");
        if ($isFree) {
            $this->consumeFreePower($powerKey, $events, $cooldown, $playsRemaining);
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
            $this->notifyPlayerActionState($playerId, $powerId, $cooldown, $powerKey, $newPlaysRemaining);
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

        $this->nextStateAfterHeroAction($playerId);
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
        return (int) ($power['cooldown'] ?? 0);
    }

    /**
     * Compute the new power_plays_remaining after a single use.
     *
     * - First play of a multi-plays power: initialise to plays - 1.
     * - Chained play (already counting down): decrement.
     * - Single-play power: stays at 0.
     */
    private function playsRemainingAfterUse(string $powerKey, int $currentPlaysRemaining): int
    {
        $power = $this->bonus_cards[$powerKey] ?? [];
        $totalPlays = max(1, (int) ($power['plays'] ?? 1));

        if ($currentPlaysRemaining > 0) {
            return max(0, $currentPlaysRemaining - 1);
        }

        if ($totalPlays > 1) {
            return $totalPlays - 1;
        }

        return 0;
    }

    private function isFreePowerAvailable(string $powerKey, int $cooldown, int $playerId, int $playsRemaining = 0): bool
    {
        $freeTriggers = $this->bonus_cards[$powerKey]['free_triggers'] ?? [];
        if (!is_array($freeTriggers) || $freeTriggers === []) {
            return false;
        }

        $chain = $this->loadFreeActionChain();
        $engine = new HNS_FreeActionEngine($chain['active_event_chain'], $chain['used_action_keys']);
        return $engine->canUseFreeAction($powerKey, $freeTriggers, $cooldown, $playsRemaining);
    }

    /** @param array<int, array<string, mixed>> $events */
    private function consumeFreePower(string $powerKey, array $events, int $cooldown, int $playsRemaining = 0): void
    {
        $chain = $this->loadFreeActionChain();
        $engine = new HNS_FreeActionEngine($chain['active_event_chain'], $chain['used_action_keys']);
        // currentCooldown is passed as 0 because canUseFreeAction was already
        // validated upstream with the real cooldown + playsRemaining bypass.
        $result = $engine->useFreeAction($powerKey, $this->bonus_cards[$powerKey]['free_triggers'] ?? [], 0, $cooldown, $events, $playsRemaining);
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
        return $this->isPlayerTurnSpent($playerId) && !$this->isPlayerFreeActionAvailable($playerId);
    }

    private function isActivePlayerFreeActionAvailable(): bool
    {
        return $this->isPlayerFreeActionAvailable($this->currentPlayerIdOrFirstPlayerId());
    }

    private function nextStateAfterHeroAction(int $playerId): void
    {
        if ($this->shouldEndActivePlayerTurn($playerId)) {
            $this->gamestate->setPlayerNonMultiactive($playerId, 'roundEnd');
            return;
        }

        $this->gamestate->nextState('continueTurn');
    }

    private function isPlayerTurnSpent(int $playerId): bool
    {
        return (int) $this->getUniqueValueFromDB("SELECT COUNT(*) FROM player WHERE player_id = $playerId AND (player_free_move_available = 1 OR player_main_action_available = 1)") === 0;
    }

    private function playersWithAvailableActions(): array
    {
        return $this->getCollectionFromDb('SELECT player_id id FROM player WHERE player_free_move_available = 1 OR player_main_action_available = 1');
    }

    private function playersWithPendingRewards(): array
    {
        $playerIds = [];
        foreach ($this->getCurrentRewardOffersByPlayer() + $this->getCurrentRewardUpgradesByPlayer() as $playerId => $unused) {
            if ($this->isRewardPendingForPlayer((int) $playerId)) {
                $playerIds[(int) $playerId] = ['id' => (int) $playerId];
            }
        }

        return $playerIds;
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

    private function currentPlayerIdOrFirstPlayerId(): int
    {
        try {
            $playerId = (int) $this->getCurrentPlayerId();
            if ($playerId > 0) {
                return $playerId;
            }
        } catch (Throwable) {
            // Some framework calls, including game creation, run without a logged player.
        }

        return (int) $this->getUniqueValueFromDB('SELECT player_id FROM player ORDER BY player_id LIMIT 1');
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

    private function notifyPlayerActionState(int $playerId, int $powerId, int $cooldown, string $powerKey = '', int $playsRemaining = 0): void
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
            'power_plays_remaining' => $playsRemaining,
            'free_action_events' => $this->getFreeActionEventTypes(),
        ]);
    }

    private function isHeroPhaseEndingAfterActiveTurn(int $playerId): bool
    {
        if (!$this->isPlayerTurnSpent($playerId)) {
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

        $playerId = (int) $this->getCurrentPlayerId();
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
        $playerId = (int) $this->getCurrentPlayerId();
        $this->clearFreeActionChain();
        $state = HNS_RoundEngine::endPlayerTurn($this->loadEngineState(), $playerId);
        $this->persistPlayerActionFlags($state['players'][$playerId]);
        $this->gamestate->setPlayerNonMultiactive($playerId, 'roundEnd');
    }

    public function actChooseReward(string $mode, int $slot, string $powerKey): void
    {
        $this->checkAction('actChooseReward');
        $playerId = (int) $this->getCurrentPlayerId();
        $playerPowers = array_values($this->getCollectionFromDb("SELECT player_power_id id, power_slot slot, power_key, power_cooldown cooldown FROM player_power WHERE player_id = $playerId ORDER BY power_slot"));
        $offer = $this->getCurrentRewardOfferForPlayer($playerId);
        $upgradeOffer = $this->getCurrentRewardUpgradesForPlayer($playerId);

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

        $this->clearRewardForPlayer($playerId);
        $this->notifyAllPlayers('rewardChosen', clienttranslate('${player_name} chooses a reward'), [
            'player_id' => $playerId,
            'player_name' => $this->getPlayerNameById($playerId),
            'mode' => $mode,
            'slot' => $slot,
            'power_key' => $chosenPowerKey,
            'reward_offers' => $this->getCurrentRewardOffersByPlayer(),
            'reward_upgrades_by_player' => $this->getCurrentRewardUpgradesByPlayer(),
        ]);
        $this->gamestate->setPlayerNonMultiactive($playerId, 'nextLevel');
    }

    public function actSkipReward(): void
    {
        $this->checkAction('actSkipReward');
        $playerId = (int) $this->getCurrentPlayerId();
        $this->clearRewardForPlayer($playerId);
        $this->notifyAllPlayers('rewardSkipped', clienttranslate('${player_name} skips a reward'), [
            'player_id' => $playerId,
            'player_name' => $this->getPlayerNameById($playerId),
            'reward_offers' => $this->getCurrentRewardOffersByPlayer(),
            'reward_upgrades_by_player' => $this->getCurrentRewardUpgradesByPlayer(),
        ]);
        $this->gamestate->setPlayerNonMultiactive($playerId, 'nextLevel');
    }

    public function actSkipFreeMove(): void
    {
        $this->checkAction('actSkipFreeMove');
        $playerId = (int) $this->getCurrentPlayerId();
        $this->DbQuery("UPDATE player SET player_free_move_available = 0 WHERE player_id = $playerId");
        if ($this->isPlayerTurnSpent($playerId)) {
            $this->clearFreeActionChain();
        }
        $this->nextStateAfterOptionalActionSkip($playerId);
    }

    public function actSkipMainAction(): void
    {
        $this->checkAction('actSkipMainAction');
        $playerId = (int) $this->getCurrentPlayerId();
        $this->DbQuery("UPDATE player SET player_main_action_available = 0, player_action_points = 0, player_free_move_available = 0 WHERE player_id = $playerId");
        $this->nextStateAfterOptionalActionSkip($playerId);
    }

    private function nextStateAfterOptionalActionSkip(int $playerId): void
    {
        $this->nextStateAfterHeroAction($playerId);
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

    /** @return array<int, list<string>> */
    private function buildRewardOffersByPlayer(array $remainingPowerDeck): array
    {
        $offers = [];
        foreach ($this->getCollectionFromDb('SELECT player_id id FROM player') as $player) {
            $playerId = (int) $player['id'];
            $playerPowers = array_values($this->getCollectionFromDb("SELECT power_slot slot, power_key FROM player_power WHERE player_id = $playerId ORDER BY power_slot"));
            $offers[$playerId] = HNS_LevelReward::drawOfferForPlayer($this->bonus_cards, $remainingPowerDeck, $playerPowers);
        }

        return $offers;
    }

    /** @return array<int, list<array{slot:int, from:string, to:string}>> */
    private function buildRewardUpgradesByPlayer(array $remainingPowerDeck): array
    {
        $upgrades = [];
        foreach ($this->getCollectionFromDb('SELECT player_id id FROM player') as $player) {
            $playerId = (int) $player['id'];
            $playerPowers = array_values($this->getCollectionFromDb("SELECT power_slot slot, power_key FROM player_power WHERE player_id = $playerId ORDER BY power_slot"));
            $upgrades[$playerId] = HNS_LevelReward::drawUpgradeOfferForPlayer($this->bonus_cards, $remainingPowerDeck, $playerPowers);
        }

        return $upgrades;
    }

    /** @return array<int, list<string>> */
    private function getCurrentRewardOffersByPlayer(): array
    {
        return $this->getRewardMap('reward_offers');
    }

    /** @return list<string> */
    private function getCurrentRewardOfferForPlayer(int $playerId): array
    {
        return $this->getCurrentRewardOffersByPlayer()[$playerId] ?? [];
    }

    /** @return array<int, list<array{slot:int, from:string, to:string}>> */
    private function getCurrentRewardUpgradesByPlayer(): array
    {
        return $this->getRewardMap('reward_upgrades_by_player');
    }

    /** @return list<array{slot:int, from:string, to:string}> */
    private function getCurrentRewardUpgradesForPlayer(int $playerId): array
    {
        return $this->getCurrentRewardUpgradesByPlayer()[$playerId] ?? [];
    }

    private function isRewardPendingForPlayer(int $playerId): bool
    {
        return $this->getCurrentRewardOfferForPlayer($playerId) !== [] || $this->getCurrentRewardUpgradesForPlayer($playerId) !== [];
    }

    private function saveCurrentRewardOffersByPlayer(array $offers): void
    {
        $this->saveRewardMap('reward_offers', $offers);
    }

    private function saveCurrentRewardUpgradesByPlayer(array $upgrades): void
    {
        $this->saveRewardMap('reward_upgrades_by_player', $upgrades);
    }

    private function clearRewardForPlayer(int $playerId): void
    {
        $offers = $this->getCurrentRewardOffersByPlayer();
        $upgrades = $this->getCurrentRewardUpgradesByPlayer();
        unset($offers[$playerId], $upgrades[$playerId]);
        $this->saveCurrentRewardOffersByPlayer($offers);
        $this->saveCurrentRewardUpgradesByPlayer($upgrades);
    }

    private function getRewardMap(string $name): array
    {
        $safeName = $this->hns_sql_escape($name);
        $json = $this->getUniqueValueFromDB("SELECT var_value FROM global_var WHERE var_name = '$safeName'");
        if (!is_string($json) || $json === '') {
            return [];
        }

        $map = json_decode($json, true);
        if (!is_array($map)) {
            return [];
        }

        $normalized = [];
        foreach ($map as $playerId => $value) {
            $normalized[(int) $playerId] = is_array($value) ? array_values($value) : [];
        }

        return $normalized;
    }

    private function saveRewardMap(string $name, array $map): void
    {
        $safeName = $this->hns_sql_escape($name);
        $json = $this->hns_sql_escape((string) json_encode($map));
        $this->DbQuery("REPLACE INTO global_var (var_name, var_value) VALUES ('$safeName', '$json')");
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
