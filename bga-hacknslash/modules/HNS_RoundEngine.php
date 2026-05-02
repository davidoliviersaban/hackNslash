<?php

final class HNS_RoundEngine
{
    /** @param array<string, mixed> $state */
    public static function startRound(array $state): array
    {
        foreach ($state['players'] as &$player) {
            $player['free_move_available'] = true;
            $player['main_action_available'] = true;
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    public static function consumeFreeMove(array $state, int $playerId): array
    {
        self::assertPlayerCan($state, $playerId, 'free_move_available', 'Free move is not available.');
        $state['players'][$playerId]['free_move_available'] = false;

        return $state;
    }

    /** @param array<string, mixed> $state */
    public static function consumeMainAction(array $state, int $playerId): array
    {
        self::assertPlayerCan($state, $playerId, 'main_action_available', 'Main action is not available.');
        $state['players'][$playerId]['main_action_available'] = false;

        return $state;
    }

    /** @param array<string, mixed> $state */
    public static function isHeroPhaseComplete(array $state): bool
    {
        foreach ($state['players'] as $player) {
            if (($player['free_move_available'] ?? false) || ($player['main_action_available'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $state */
    public static function isGameLost(array $state): bool
    {
        foreach ($state['entities'] as $entity) {
            if (($entity['type'] ?? null) === 'hero' && (($entity['state'] ?? 'active') === 'dead' || (int) ($entity['health'] ?? 1) <= 0)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $state */
    public static function cooldownStep(array $state): array
    {
        if (!isset($state['player_powers'])) {
            return $state;
        }

        foreach ($state['player_powers'] as &$power) {
            $power['cooldown'] = max(0, (int) ($power['cooldown'] ?? 0) - 1);
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    public static function activateTraps(array $state): array
    {
        $events = [];
        foreach ($state['entities'] as $entityId => &$entity) {
            if (($entity['type'] ?? null) !== 'hero' || ($entity['state'] ?? 'active') !== 'active') {
                continue;
            }

            $tile = $state['tiles'][(int) $entity['tile_id']] ?? null;
            if (($tile['type'] ?? null) !== 'spikes') {
                continue;
            }

            $entity['health'] = max(0, (int) $entity['health'] - 1);
            if ((int) $entity['health'] === 0) {
                $entity['state'] = 'dead';
            }
            $events[] = ['type' => 'trapDamage', 'target_entity_id' => (int) $entityId, 'damage' => 1];
        }

        return ['state' => $state, 'events' => $events];
    }

    /**
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $monsterMaterial
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    public static function completeEnemyPhase(array $state, array $monsterMaterial): array
    {
        $trapResult = self::activateTraps($state);
        if (self::isGameLost($trapResult['state'])) {
            return ['state' => $trapResult['state'], 'events' => [...$trapResult['events'], ['type' => 'gameLost']]];
        }

        $monsterResult = HNS_GameEngine::activateMonsters($trapResult['state'], $monsterMaterial);
        if (self::isGameLost($monsterResult['state'])) {
            return ['state' => $monsterResult['state'], 'events' => [...$trapResult['events'], ...$monsterResult['events'], ['type' => 'gameLost']]];
        }

        return [
            'state' => $monsterResult['state'],
            'events' => [...$trapResult['events'], ...$monsterResult['events']],
        ];
    }

    /**
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $monsterMaterial
     * @param array<int, int> $monsterDeck
     * @param array<string, array<string, mixed>> $powers
     * @param array<int, string> $powerDeck
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    public static function resolveLevelEnd(array $state, array $monsterMaterial, array $monsterDeck, array $powers, array $powerDeck, int $seed): array
    {
        if (!HNS_GameEngine::isLevelCleared($state)) {
            return ['state' => $state, 'events' => []];
        }

        $state = HNS_GameEngine::prepareLevelReward($state, $powers, $powerDeck);
        $nextLevel = (int) ($state['level'] ?? 1) + 1;
        $events = [['type' => 'levelCleared', 'level' => (int) ($state['level'] ?? 1), 'reward_offer' => $state['reward_offer'] ?? []]];

        if ($nextLevel > HNS_BOSS_LEVEL) {
            $state['game_over'] = true;
            $events[] = ['type' => 'gameWon'];

            return ['state' => $state, 'events' => $events];
        }

        $nextState = HNS_GameEngine::createLevel($nextLevel, $seed, $monsterMaterial, $monsterDeck);
        $nextState['players'] = $state['players'] ?? [];
        $nextState['player_powers'] = $state['player_powers'] ?? [];
        $events[] = ['type' => 'levelStarted', 'level' => $nextLevel, 'is_boss_level' => $nextState['is_boss_level'] ?? false];

        return ['state' => self::startRound($nextState), 'events' => $events];
    }

    /** @param array<string, mixed> $state */
    private static function assertPlayerCan(array $state, int $playerId, string $field, string $message): void
    {
        if (!isset($state['players'][$playerId])) {
            throw new InvalidArgumentException("Unknown player $playerId.");
        }

        if (($state['players'][$playerId][$field] ?? false) !== true) {
            throw new InvalidArgumentException($message);
        }
    }
}
