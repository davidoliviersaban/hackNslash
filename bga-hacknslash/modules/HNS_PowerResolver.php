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
        self::assertEntityExists($state, $sourceEntityId);
        $targetEntityIds = self::attackTargetEntityIds($payload, $state, (int) ($power['targets'] ?? 1));
        if ($targetEntityIds === []) {
            throw new InvalidArgumentException('No attack target selected.');
        }

        if (count($targetEntityIds) > (int) ($power['targets'] ?? 1)) {
            throw new InvalidArgumentException("Too many targets for $powerKey.");
        }

        $sourceTile = self::entityTile($state, $sourceEntityId);
        foreach ($targetEntityIds as $targetEntityId) {
            self::assertEntityExists($state, $targetEntityId);
            $sourceType = (string) ($state['entities'][$sourceEntityId]['type'] ?? '');
            $targetType = (string) ($state['entities'][$targetEntityId]['type'] ?? '');
            if ($sourceType === 'hero' && $targetType === 'hero') {
                throw new InvalidArgumentException("Cannot target an allied hero with $powerKey.");
            }

            $targetTile = self::entityTile($state, $targetEntityId);
            $isInRange = ($power['range_metric'] ?? 'manhattan') === 'chebyshev'
                ? HNS_BoardRules::isInDiagonalRange($sourceTile, $targetTile, $power['range'])
                : HNS_BoardRules::isInOrthogonalRange($sourceTile, $targetTile, $power['range']);
            if (!$isInRange) {
                throw new InvalidArgumentException("Target is out of range for $powerKey.");
            }

            $state = self::damageEntity($targetEntityId, (int) $power['damage'], $sourceEntityId, $state, $events);

            if (in_array('thorns', $state['level_monster_abilities'] ?? [], true) && HNS_BoardRules::isExactStep($sourceTile, $targetTile, 1)) {
                $state = self::damageEntity($sourceEntityId, 1, $targetEntityId, $state, $events);
                $events[] = ['type' => 'thornsDamage', 'source_entity_id' => $targetEntityId, 'target_entity_id' => $sourceEntityId, 'damage' => 1];
            }
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
        $rangeMetric = $power['range_metric'] ?? 'manhattan';
        $isInRange = $rangeMetric === 'orthogonal'
            ? HNS_BoardRules::isInOrthogonalRange($sourceTile, $targetTile, $power['distance'])
            : HNS_BoardRules::isInRange($sourceTile, $targetTile, $power['distance']);
        if ($rangeMetric === 'chebyshev') {
            $isInRange = HNS_BoardRules::isInDiagonalRange($sourceTile, $targetTile, $power['distance']);
        }
        if (!$isInRange) {
            throw new InvalidArgumentException('Dash target is out of range.');
        }

        if (!HNS_BoardRules::isTileWalkable($targetTile)) {
            throw new InvalidArgumentException('Dash target is not available.');
        }

        if (!HNS_BoardRules::canEnterTile($targetTileId, $state['entities'], $state['entities'][$sourceEntityId], $sourceEntityId)) {
            $state = self::resolveBlockedMovement($sourceEntityId, $targetTileId, $sourceEntityId, $state, $events);
        }

        if (HNS_BoardRules::canEnterTile($targetTileId, $state['entities'], $state['entities'][$sourceEntityId], $sourceEntityId)) {
            $state['entities'][$sourceEntityId]['tile_id'] = $targetTileId;
            $state['entities'][$sourceEntityId]['status'] = self::removeStatusToken((string) ($state['entities'][$sourceEntityId]['status'] ?? ''), 'slimed');
            $events[] = [
                'type' => HNS_FreeActionEngine::EVENT_AFTER_DASH,
                'source_entity_id' => $sourceEntityId,
                'target_tile_id' => $targetTileId,
            ];
        }

        return ['state' => $state, 'events' => $events];
    }

    private static function removeStatusToken(string $status, string $token): ?string
    {
        $tokens = array_values(array_filter(preg_split('/\s+/', trim($status)) ?: [], static fn (string $value): bool => $value !== '' && $value !== $token));

        return $tokens === [] ? null : implode(' ', $tokens);
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

        if (count($targetEntityIds) !== count(array_unique($targetEntityIds))) {
            throw new InvalidArgumentException('Pull power cannot target the same entity more than once.');
        }

        $sourceTile = self::entityTile($state, $sourceEntityId);
        $selectedTile = $state['tiles'][$selectedTileId];
        $isInRange = ($power['range_metric'] ?? 'manhattan') === 'chebyshev'
            ? HNS_BoardRules::isInDiagonalRange($sourceTile, $selectedTile, $power['range'])
            : HNS_BoardRules::isInRange($sourceTile, $selectedTile, $power['range']);
        if (!$isInRange) {
            throw new InvalidArgumentException('Selected tile is out of range for pull power.');
        }

        $targetEntityIds = self::sortMonstersByMovementOrder($targetEntityIds, $state['entities']);

        foreach ($targetEntityIds as $targetEntityId) {
            self::assertEntityExists($state, $targetEntityId);
            $targetTile = self::entityTile($state, $targetEntityId);
            if (!HNS_BoardRules::isInDiagonalRange($selectedTile, $targetTile, $power['target_range_from_selected_tile'])) {
                throw new InvalidArgumentException('Pull target is out of range from selected tile.');
            }

            $state = self::pullEntityTowardTile($targetEntityId, $selectedTileId, (int) $power['pull_distance'], $state, $events, $sourceEntityId);
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
    private static function pullEntityTowardTile(int $entityId, int $destinationTileId, int $distance, array $state, array &$events, int $sourceEntityId): array
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

            $nextTileId = (int) $nextTile['id'];
            if (!HNS_BoardRules::isTileWalkable($nextTile) || self::monsterIdsOnTile($nextTileId, $state['entities'], $entityId) !== [] || !HNS_BoardRules::canEnterTile($nextTileId, $state['entities'], $state['entities'][$entityId], $entityId)) {
                $state = self::resolveBlockedMovement($entityId, $nextTileId, $sourceEntityId, $state, $events);
            }

            if (HNS_BoardRules::isTileWalkable($nextTile) && self::monsterIdsOnTile($nextTileId, $state['entities'], $entityId) === [] && HNS_BoardRules::canEnterTile($nextTileId, $state['entities'], $state['entities'][$entityId], $entityId)) {
                $state['entities'][$entityId]['tile_id'] = $nextTileId;
                $events[] = ['type' => 'monsterMove', 'source_entity_id' => $entityId, 'target_tile_id' => $nextTileId];
            }
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
        $currentDistance = HNS_BoardRules::diagonalDistance($from, $to);
        $bestTile = null;
        $bestDistance = $currentDistance;

        foreach ($tiles as $tile) {
            if (HNS_BoardRules::diagonalDistance($from, $tile) !== 1) {
                continue;
            }

            $tileId = (int) $tile['id'];
            if (!HNS_BoardRules::isTileWalkable($tile) && $tileId !== (int) $to['id']) {
                continue;
            }

            $distance = HNS_BoardRules::diagonalDistance($tile, $to);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestTile = $tile;
            }
        }

        return $bestTile;
    }

    /**
     * @param array<string, mixed> $from
     * @param array<string, mixed> $obstacle
     * @param array<int, array<string, mixed>> $tiles
     * @return array<string, mixed>|null
     */
    private static function closestTileBeforeObstacle(array $from, array $obstacle, array $tiles): ?array
    {
        $dx = (int) $obstacle['x'] <=> (int) $from['x'];
        $dy = (int) $obstacle['y'] <=> (int) $from['y'];
        $stopX = (int) $obstacle['x'] - $dx;
        $stopY = (int) $obstacle['y'] - $dy;

        foreach ($tiles as $tile) {
            if ((int) $tile['x'] === $stopX && (int) $tile['y'] === $stopY && HNS_BoardRules::isTileWalkable($tile)) {
                return $tile;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function resolveBlockedMovement(int $movingEntityId, int $targetTileId, int $damageSourceEntityId, array $state, array &$events): array
    {
        $blockingMonsterIds = self::monsterIdsOnTile($targetTileId, $state['entities'], $movingEntityId);
        if ($blockingMonsterIds !== []) {
            $state = self::damageEntity($movingEntityId, 1, $damageSourceEntityId, $state, $events);
            foreach ($blockingMonsterIds as $blockingMonsterId) {
                $state = self::damageEntity($blockingMonsterId, 1, $damageSourceEntityId, $state, $events);
            }
            return $state;
        }

        return self::damageEntity($movingEntityId, 1, $damageSourceEntityId, $state, $events);
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     */
    private static function weakestMonsterOnTile(int $tileId, array $entities, int $movingEntityId): ?int
    {
        $weakestMonsterId = null;
        $weakestHealth = PHP_INT_MAX;

        foreach ($entities as $entityId => $entity) {
            if ((int) $entityId === $movingEntityId || ($entity['state'] ?? 'active') !== 'active') {
                continue;
            }

            if (($entity['type'] ?? null) !== 'monster' || (int) ($entity['tile_id'] ?? 0) !== $tileId) {
                continue;
            }

            $health = (int) ($entity['health'] ?? 0);
            if ($health < $weakestHealth || ($health === $weakestHealth && (int) $entityId < (int) $weakestMonsterId)) {
                $weakestHealth = $health;
                $weakestMonsterId = (int) $entityId;
            }
        }

        return $weakestMonsterId;
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     * @return array<int, int>
     */
    private static function monsterIdsOnTile(int $tileId, array $entities, int $movingEntityId): array
    {
        $monsterIds = [];
        foreach ($entities as $entityId => $entity) {
            if ((int) $entityId === $movingEntityId || ($entity['state'] ?? 'active') !== 'active') {
                continue;
            }

            if (in_array($entity['type'] ?? null, ['monster', 'boss'], true) && (int) ($entity['tile_id'] ?? 0) === $tileId) {
                $monsterIds[] = (int) $entityId;
            }
        }
        sort($monsterIds);
        return $monsterIds;
    }

    /**
     * @param array<int, int> $entityIds
     * @param array<int, array<string, mixed>> $entities
     * @return array<int, int>
     */
    private static function sortMonstersByMovementOrder(array $entityIds, array $entities): array
    {
        usort($entityIds, static function (int $leftId, int $rightId) use ($entities): int {
            $leftOrder = self::monsterMovementOrder($entities[$leftId] ?? []);
            $rightOrder = self::monsterMovementOrder($entities[$rightId] ?? []);

            return $leftOrder <=> $rightOrder ?: $leftId <=> $rightId;
        });

        return $entityIds;
    }

    /** @param array<string, mixed> $entity */
    private static function monsterMovementOrder(array $entity): int
    {
        if (($entity['type'] ?? null) === 'boss' || ($entity['monster_size'] ?? null) === 'boss') {
            return 3;
        }

        if (($entity['monster_size'] ?? 'small') === 'big') {
            return 2;
        }

        return 1;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function damageEntity(int $targetEntityId, int $damage, int $sourceEntityId, array $state, array &$events): array
    {
        if (($state['entities'][$targetEntityId]['type'] ?? null) === 'monster'
            && ($state['entities'][$targetEntityId]['has_shield'] ?? false) === true
            && ($state['entities'][$targetEntityId]['shield_broken'] ?? false) !== true
            && $damage > 0
        ) {
            $state['entities'][$targetEntityId]['shield_broken'] = true;
            $events[] = ['type' => 'shieldBroken', 'source_entity_id' => $targetEntityId, 'damage_absorbed' => $damage];

            return $state;
        }

        $newHealth = max(0, (int) $state['entities'][$targetEntityId]['health'] - $damage);
        $state['entities'][$targetEntityId]['health'] = $newHealth;

        if ($damage > 0 && $newHealth > 0) {
            $events[] = [
                'type' => 'entityDamaged',
                'source_entity_id' => $sourceEntityId,
                'target_entity_id' => $targetEntityId,
                'damage' => $damage,
                'target_health' => $newHealth,
            ];
        }

        if ($newHealth === 0 && ($state['entities'][$targetEntityId]['state'] ?? 'active') !== 'dead') {
            $state['entities'][$targetEntityId]['state'] = 'dead';
            if (($state['entities'][$targetEntityId]['type'] ?? null) === 'monster') {
                $killEvent = [
                    'type' => HNS_FreeActionEngine::EVENT_AFTER_KILL,
                    'source_entity_id' => $sourceEntityId,
                    'target_entity_id' => $targetEntityId,
                ];
                if (($state['entities'][$targetEntityId]['on_death'] ?? null) !== null) {
                    $killEvent['death_effect'] = $state['entities'][$targetEntityId]['on_death'];
                }
                $events[] = $killEvent;

                if (($state['entities'][$targetEntityId]['on_death'] ?? null) === 'explode') {
                    $state = HNS_MonsterAi::explode($targetEntityId, $state, self::monsterMaterialForEntity($state['entities'][$targetEntityId]), $events);
                }
            }

            if (($state['entities'][$targetEntityId]['type'] ?? null) === 'boss') {
                $state = HNS_BossEngine::resolveBossDefeat($targetEntityId, $state, $state['bosses'] ?? [], $events);
            }
        }

        return $state;
    }

    /** @param array<string, mixed> $entity */
    private static function monsterMaterialForEntity(array $entity): array
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $monsterId = (int) ($entity['type_arg'] ?? 0);

        return array_merge($monsters[$monsterId] ?? [], $entity);
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
    private static function attackTargetEntityIds(array $payload, array $state, int $maxTargets): array
    {
        $targetEntityIds = array_map('intval', $payload['target_entity_ids'] ?? []);
        if ($targetEntityIds === [] && !empty($payload['target_entity_id'])) {
            $targetEntityIds[] = (int) $payload['target_entity_id'];
        }
        if ($targetEntityIds === [] && !empty($payload['target_tile_id'])) {
            $targetEntityIds[] = self::attackTargetEntityIdForTile((int) $payload['target_tile_id'], $state);
        }

        return array_values(array_slice($targetEntityIds, 0, $maxTargets + 1));
    }

    /** @param array<string, mixed> $state */
    private static function attackTargetEntityIdForTile(int $tileId, array $state): int
    {
        self::assertTileExists($state, $tileId);
        foreach ($state['entities'] as $entityId => $entity) {
            if (($entity['state'] ?? 'active') === 'dead') {
                continue;
            }
            if (!in_array($entity['type'] ?? null, ['monster', 'boss'], true)) {
                continue;
            }
            if ((int) ($entity['tile_id'] ?? 0) === $tileId) {
                return (int) $entityId;
            }
        }

        throw new InvalidArgumentException('No attack target on selected tile.');
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
