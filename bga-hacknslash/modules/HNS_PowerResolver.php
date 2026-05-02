<?php

final class HNS_PowerResolver
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, array<string, mixed>> $state
     * @param array<string, array<string, mixed>> $powers
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    public static function resolve(string $powerKey, int $sourceEntityId, array $payload, array $state, array $powers): array
    {
        if (!isset($powers[$powerKey])) {
            throw new InvalidArgumentException("Unknown power $powerKey.");
        }

        $power = $powers[$powerKey];
        $events = [self::cardPlayedEvent($sourceEntityId, $powerKey)];

        if (($power['effect'] ?? null) === 'attack') {
            return self::resolveAttack($powerKey, $sourceEntityId, $payload, $state, $power, $events);
        }

        if (($power['effect'] ?? null) === 'dash') {
            return self::resolveDash($sourceEntityId, $payload, $state, $power, $events);
        }

        if (($power['effect'] ?? null) === 'pull') {
            return self::resolvePull($sourceEntityId, $payload, $state, $power, $events);
        }

        throw new InvalidArgumentException("Unsupported power $powerKey.");
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $state
     * @param array<string, mixed> $power
     * @param array<int, array<string, mixed>> $events
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    private static function resolveAttack(string $powerKey, int $sourceEntityId, array $payload, array $state, array $power, array $events): array
    {
        $targetEntityId = (int) ($payload['target_entity_id'] ?? 0);
        self::assertEntityExists($state, $sourceEntityId);
        self::assertEntityExists($state, $targetEntityId);

        $sourceTile = self::entityTile($state, $sourceEntityId);
        $targetTile = self::entityTile($state, $targetEntityId);
        if (!HNS_BoardRules::isInRange($sourceTile, $targetTile, $power['range'])) {
            throw new InvalidArgumentException("Target is out of range for $powerKey.");
        }

        $newHealth = max(0, (int) $state['entities'][$targetEntityId]['health'] - (int) $power['damage']);
        $state['entities'][$targetEntityId]['health'] = $newHealth;
        if ($newHealth === 0) {
            $state['entities'][$targetEntityId]['state'] = 'dead';
            $events[] = [
                'type' => HNS_FreeActionEngine::EVENT_AFTER_KILL,
                'source_entity_id' => $sourceEntityId,
                'target_entity_id' => $targetEntityId,
            ];
        }

        return ['state' => $state, 'events' => $events];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $state
     * @param array<string, mixed> $power
     * @param array<int, array<string, mixed>> $events
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    private static function resolveDash(int $sourceEntityId, array $payload, array $state, array $power, array $events): array
    {
        $targetTileId = (int) ($payload['target_tile_id'] ?? 0);
        self::assertEntityExists($state, $sourceEntityId);
        self::assertTileExists($state, $targetTileId);

        $sourceTile = self::entityTile($state, $sourceEntityId);
        $targetTile = $state['tiles'][$targetTileId];
        if (!HNS_BoardRules::isInRange($sourceTile, $targetTile, $power['distance'])) {
            throw new InvalidArgumentException('Dash target is out of range.');
        }

        if (!HNS_BoardRules::isTileWalkable($targetTile) || !HNS_BoardRules::isTileAvailable($targetTileId, $state['entities'], $sourceEntityId)) {
            throw new InvalidArgumentException('Dash target is not available.');
        }

        $state['entities'][$sourceEntityId]['tile_id'] = $targetTileId;
        $events[] = [
            'type' => HNS_FreeActionEngine::EVENT_AFTER_DASH,
            'source_entity_id' => $sourceEntityId,
            'target_tile_id' => $targetTileId,
        ];

        return ['state' => $state, 'events' => $events];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $state
     * @param array<string, mixed> $power
     * @param array<int, array<string, mixed>> $events
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    private static function resolvePull(int $sourceEntityId, array $payload, array $state, array $power, array $events): array
    {
        $selectedTileId = (int) ($payload['selected_tile_id'] ?? 0);
        $targetEntityIds = array_map('intval', $payload['target_entity_ids'] ?? []);
        self::assertEntityExists($state, $sourceEntityId);
        self::assertTileExists($state, $selectedTileId);

        if (count($targetEntityIds) > (int) $power['targets']) {
            throw new InvalidArgumentException('Too many targets for pull power.');
        }

        $sourceTile = self::entityTile($state, $sourceEntityId);
        $selectedTile = $state['tiles'][$selectedTileId];
        if (!HNS_BoardRules::isInRange($sourceTile, $selectedTile, $power['range'])) {
            throw new InvalidArgumentException('Selected tile is out of range for pull power.');
        }

        foreach ($targetEntityIds as $targetEntityId) {
            self::assertEntityExists($state, $targetEntityId);
            $state = self::pullEntityTowardTile($targetEntityId, $selectedTileId, (int) $power['pull_distance'], $state);
        }

        $events[] = [
            'type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL,
            'source_entity_id' => $sourceEntityId,
            'target_entity_ids' => $targetEntityIds,
        ];

        return ['state' => $state, 'events' => $events];
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private static function pullEntityTowardTile(int $entityId, int $destinationTileId, int $distance, array $state): array
    {
        for ($step = 0; $step < $distance; $step++) {
            $currentTile = self::entityTile($state, $entityId);
            $destinationTile = $state['tiles'][$destinationTileId];
            if ((int) $currentTile['id'] === $destinationTileId) {
                return $state;
            }

            $nextTile = self::bestAdjacentTileToward($currentTile, $destinationTile, $state['tiles'], $state['entities'], $entityId);
            if ($nextTile === null) {
                return $state;
            }

            $state['entities'][$entityId]['tile_id'] = (int) $nextTile['id'];
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     * @param array<int, array<string, mixed>> $tiles
     * @param array<int, array<string, mixed>> $entities
     * @return array<string, mixed>|null
     */
    private static function bestAdjacentTileToward(array $from, array $to, array $tiles, array $entities, int $movingEntityId): ?array
    {
        $currentDistance = HNS_BoardRules::distance($from, $to);
        $bestTile = null;
        $bestDistance = $currentDistance;

        foreach ($tiles as $tile) {
            if (HNS_BoardRules::distance($from, $tile) !== 1) {
                continue;
            }

            if (!HNS_BoardRules::isTileWalkable($tile) || !HNS_BoardRules::isTileAvailable((int) $tile['id'], $entities, $movingEntityId)) {
                continue;
            }

            $distance = HNS_BoardRules::distance($tile, $to);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestTile = $tile;
            }
        }

        return $bestTile;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private static function entityTile(array $state, int $entityId): array
    {
        self::assertEntityExists($state, $entityId);
        $tileId = (int) $state['entities'][$entityId]['tile_id'];
        self::assertTileExists($state, $tileId);

        return $state['tiles'][$tileId];
    }

    /** @param array<string, mixed> $state */
    private static function assertEntityExists(array $state, int $entityId): void
    {
        if (!isset($state['entities'][$entityId])) {
            throw new InvalidArgumentException("Unknown entity $entityId.");
        }
    }

    /** @param array<string, mixed> $state */
    private static function assertTileExists(array $state, int $tileId): void
    {
        if (!isset($state['tiles'][$tileId])) {
            throw new InvalidArgumentException("Unknown tile $tileId.");
        }
    }

    /** @return array<string, mixed> */
    private static function cardPlayedEvent(int $sourceEntityId, string $powerKey): array
    {
        return [
            'type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED,
            'source_entity_id' => $sourceEntityId,
            'power_key' => $powerKey,
        ];
    }
}
