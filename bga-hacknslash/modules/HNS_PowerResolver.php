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

        if (($power['effect'] ?? null) === 'dash_attack') {
            return self::resolveDashAttack($powerKey, $sourceEntityId, $payload, $state, $power, $events);
        }

        if (($power['effect'] ?? null) === 'area_attack') {
            return self::resolveAreaAttack($powerKey, $sourceEntityId, $payload, $state, $power, $events);
        }

        if (($power['effect'] ?? null) === 'move_area_attack') {
            return self::resolveMoveAreaAttack($powerKey, $sourceEntityId, $payload, $state, $power, $events);
        }

        if (($power['effect'] ?? null) === 'heal') {
            return self::resolveHeal($powerKey, $sourceEntityId, $payload, $state, $power, $events);
        }

        if (($power['effect'] ?? null) === 'jump') {
            return self::resolveJump($powerKey, $sourceEntityId, $payload, $state, $power, $events);
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
        HNS_BoardRules::assertEntityExists($state, $sourceEntityId);
        $targetEntityIds = self::attackTargetEntityIds($payload, $state, (int) ($power['targets'] ?? 1));
        if ($targetEntityIds === []) {
            throw new InvalidArgumentException('No attack target selected.');
        }

        if (count($targetEntityIds) > (int) ($power['targets'] ?? 1)) {
            throw new InvalidArgumentException("Too many targets for $powerKey.");
        }

        $sourceTile = HNS_BoardRules::entityTile($state, $sourceEntityId);
        $shieldDamageByTargetId = [];
        foreach ($targetEntityIds as $targetEntityId) {
            $shieldDamageByTargetId[$targetEntityId] = ($shieldDamageByTargetId[$targetEntityId] ?? 0) + (int) $power['damage'];
        }
        $shieldAbsorbedTargetIds = [];
        foreach ($targetEntityIds as $targetEntityId) {
            HNS_BoardRules::assertEntityExists($state, $targetEntityId);
            $sourceType = (string) ($state['entities'][$sourceEntityId]['type'] ?? '');
            $targetType = (string) ($state['entities'][$targetEntityId]['type'] ?? '');
            if ($sourceType === 'hero' && $targetType === 'hero') {
                throw new InvalidArgumentException("Cannot target an allied hero with $powerKey.");
            }

            $targetTile = HNS_BoardRules::entityTile($state, $targetEntityId);
            $isInRange = ($power['range_metric'] ?? 'orthogonal') === 'chebyshev'
                ? HNS_BoardRules::isInDiagonalRange($sourceTile, $targetTile, $power['range'])
                : HNS_BoardRules::isInOrthogonalRange($sourceTile, $targetTile, $power['range']);
            if (!$isInRange) {
                throw new InvalidArgumentException("Target is out of range for $powerKey.");
            }
            if (!HNS_BoardRules::hasLineOfSight($sourceTile, $targetTile, $state['tiles'])) {
                throw new InvalidArgumentException("Target is not in line of sight for $powerKey.");
            }

            if (in_array($targetEntityId, $shieldAbsorbedTargetIds, true)) {
                continue;
            }

            $attackAbsorbedByShield = false;
            if ((int) $power['damage'] > 0 && empty($power['ignores_shield']) && self::hasActiveShield($state, $targetEntityId)) {
                $state = self::breakShield($targetEntityId, $shieldDamageByTargetId[$targetEntityId] ?? (int) $power['damage'], $state, $events);
                $shieldAbsorbedTargetIds[] = $targetEntityId;
                $attackAbsorbedByShield = true;
            } else {
                $state = self::damageEntity($targetEntityId, (int) $power['damage'], $sourceEntityId, $state, $events, !empty($power['ignores_shield']));
            }

            if ((int) ($power['push_distance'] ?? 0) > 0 && ($state['entities'][$targetEntityId]['state'] ?? 'active') === 'active') {
                $state = self::pushEntityAwayFromTile($targetEntityId, $sourceTile, (int) $power['push_distance'], $state, $events, $sourceEntityId);
            }

            if ($attackAbsorbedByShield) {
                continue;
            }

            $state = self::applyThornsDamageIfMelee($sourceEntityId, $targetEntityId, $sourceTile, $targetTile, $state, $events);

            if ((int) ($power['heal_on_damage'] ?? 0) > 0) {
                $state = self::healEntity($sourceEntityId, (int) $power['heal_on_damage'], $sourceEntityId, $state, $events);
            }
        }

        if ((int) ($power['push_distance'] ?? 0) > 0) {
            $events[] = [
                'type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL,
                'source_entity_id' => $sourceEntityId,
                'target_entity_ids' => $targetEntityIds,
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
    private static function resolveDashAttack(string $powerKey, int $sourceEntityId, array $payload, array $state, array $power, array $events): array
    {
        self::assertHeroCanMoveWithPower($state, $sourceEntityId, $powerKey);

        $targetEntityIds = self::dashAttackTargetEntityIds($payload);

        HNS_BoardRules::assertEntityExists($state, $sourceEntityId);
        $sourceTile = HNS_BoardRules::entityTile($state, $sourceEntityId);
        $selectedTileId = (int) ($payload['selected_tile_id'] ?? $payload['target_tile_id'] ?? 0);
        if ($selectedTileId <= 0) {
            throw new InvalidArgumentException("No dash destination for $powerKey.");
        }
        $destinationTile = self::dashAttackDestinationTile($sourceTile, $targetEntityIds, $state, $power, $sourceEntityId, $powerKey, $selectedTileId);
        $state['entities'][$sourceEntityId]['tile_id'] = (int) $destinationTile['id'];
        $events[] = [
            'type' => HNS_FreeActionEngine::EVENT_AFTER_DASH,
            'source_entity_id' => $sourceEntityId,
            'target_tile_id' => (int) $destinationTile['id'],
        ];

        foreach ($targetEntityIds as $targetEntityId) {
            HNS_BoardRules::assertEntityExists($state, $targetEntityId);
            $targetTile = HNS_BoardRules::entityTile($state, $targetEntityId);
            if (!HNS_BoardRules::isInOrthogonalRange($destinationTile, $targetTile, [1, 1])) {
                throw new InvalidArgumentException("Target is out of range for $powerKey after dash.");
            }
            if (!HNS_BoardRules::hasLineOfSight($destinationTile, $targetTile, $state['tiles'])) {
                throw new InvalidArgumentException("Target is not in line of sight for $powerKey after dash.");
            }
            $state = self::damageEntity($targetEntityId, (int) $power['damage'], $sourceEntityId, $state, $events);
            $state = self::applyThornsDamageIfMelee($sourceEntityId, $targetEntityId, $destinationTile, $targetTile, $state, $events);
        }

        if ((int) ($power['plays'] ?? 1) > 1) {
            $events[] = ['type' => HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK, 'source_entity_id' => $sourceEntityId, 'power_key' => $powerKey];
        }

        return ['state' => $state, 'events' => $events];
    }

    /** @return array<int, int> */
    private static function dashAttackTargetEntityIds(array $payload): array
    {
        if (!empty($payload['target_entity_id'])) {
            return [(int) $payload['target_entity_id']];
        }

        if (empty($payload['target_entity_ids'])) {
            return [];
        }

        $rawIds = is_array($payload['target_entity_ids']) ? $payload['target_entity_ids'] : preg_split('/\s+/', (string) $payload['target_entity_ids']);
        $ids = array_values(array_filter(array_map('intval', $rawIds ?: []), static fn (int $id): bool => $id > 0));

        return array_slice($ids, 0, 1);
    }

    /**
     * @param array<string, mixed> $sourceTile
     * @param array<int, int> $targetEntityIds
     * @param array<string, mixed> $state
     * @param array<string, mixed> $power
     * @return array<string, mixed>
     */
    private static function dashAttackDestinationTile(array $sourceTile, array $targetEntityIds, array $state, array $power, int $sourceEntityId, string $powerKey, int $selectedTileId): array
    {
        $targetTiles = [];
        foreach ($targetEntityIds as $targetEntityId) {
            HNS_BoardRules::assertEntityExists($state, $targetEntityId);
            $targetTiles[] = HNS_BoardRules::entityTile($state, $targetEntityId);
        }

        HNS_BoardRules::assertTileExists($state, $selectedTileId);
        $selectedTile = $state['tiles'][$selectedTileId];
        if (!self::isValidDashAttackDestination($sourceTile, $selectedTile, $targetTiles, $state, $power, $sourceEntityId)) {
            throw new InvalidArgumentException("Invalid dash destination for $powerKey.");
        }

        return $selectedTile;
    }

    /**
     * @param array<string, mixed> $sourceTile
     * @param array<string, mixed> $tile
     * @param array<int, array<string, mixed>> $targetTiles
     * @param array<string, mixed> $state
     * @param array<string, mixed> $power
     */
    private static function isValidDashAttackDestination(array $sourceTile, array $tile, array $targetTiles, array $state, array $power, int $sourceEntityId): bool
    {
        $isDashInRange = HNS_BoardRules::isInRange($sourceTile, $tile, $power['range']);
        if (!$isDashInRange || !HNS_BoardRules::isTileWalkable($tile) || !HNS_BoardRules::canEnterTile((int) $tile['id'], $state['entities'], $state['entities'][$sourceEntityId], $sourceEntityId)) {
            return false;
        }

        foreach ($targetTiles as $targetTile) {
            if (!HNS_BoardRules::isInOrthogonalRange($tile, $targetTile, [1, 1]) || !HNS_BoardRules::hasLineOfSight($tile, $targetTile, $state['tiles'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $state
     * @param array<string, mixed> $power
     * @param array<int, array<string, mixed>> $events
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    private static function resolveAreaAttack(string $powerKey, int $sourceEntityId, array $payload, array $state, array $power, array $events): array
    {
        $targetTileId = (int) ($payload['target_tile_id'] ?? $payload['selected_tile_id'] ?? 0);
        if ($targetTileId === 0 && (int) ($payload['target_entity_id'] ?? 0) > 0) {
            $targetEntityId = (int) $payload['target_entity_id'];
            HNS_BoardRules::assertEntityExists($state, $targetEntityId);
            $targetTileId = (int) ($state['entities'][$targetEntityId]['tile_id'] ?? 0);
        }
        HNS_BoardRules::assertEntityExists($state, $sourceEntityId);
        HNS_BoardRules::assertTileExists($state, $targetTileId);

        $sourceTile = HNS_BoardRules::entityTile($state, $sourceEntityId);
        $targetTile = $state['tiles'][$targetTileId];
        $isInRange = ($power['range_metric'] ?? 'orthogonal') === 'chebyshev'
            ? HNS_BoardRules::isInDiagonalRange($sourceTile, $targetTile, $power['range'])
            : HNS_BoardRules::isInOrthogonalRange($sourceTile, $targetTile, $power['range']);
        if (!$isInRange) {
            throw new InvalidArgumentException("Target tile is out of range for $powerKey.");
        }
        if (!HNS_BoardRules::hasLineOfSight($sourceTile, $targetTile, $state['tiles'])) {
            throw new InvalidArgumentException("Target tile is not in line of sight for $powerKey.");
        }

        $targetEntityIds = self::enemyEntityIdsInArea($targetTile, $state, $power['area'], $power['area_metric'] ?? 'orthogonal');
        if ($targetEntityIds === []) {
            throw new InvalidArgumentException("No target in area for $powerKey.");
        }

        foreach ($targetEntityIds as $targetEntityId) {
            $state = self::damageEntity($targetEntityId, (int) $power['damage'], $sourceEntityId, $state, $events, !empty($power['ignores_shield']));
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
    private static function resolveMoveAreaAttack(string $powerKey, int $sourceEntityId, array $payload, array $state, array $power, array $events): array
    {
        HNS_BoardRules::assertEntityExists($state, $sourceEntityId);
        $sourceTile = HNS_BoardRules::entityTile($state, $sourceEntityId);
        $targetTileId = (int) ($payload['target_tile_id'] ?? $payload['selected_tile_id'] ?? $sourceTile['id']);
        if ($targetTileId === 0) {
            $targetTileId = (int) $sourceTile['id'];
        }
        HNS_BoardRules::assertTileExists($state, $targetTileId);
        $targetTile = $state['tiles'][$targetTileId];

        $isInRange = ($power['range_metric'] ?? 'chebyshev') === 'orthogonal'
            ? HNS_BoardRules::isInOrthogonalRange($sourceTile, $targetTile, $power['distance'])
            : HNS_BoardRules::isInDiagonalRange($sourceTile, $targetTile, $power['distance']);
        if (!$isInRange) {
            throw new InvalidArgumentException("Move target is out of range for $powerKey.");
        }

        if (!HNS_BoardRules::isTileWalkable($targetTile)) {
            throw new InvalidArgumentException("Move target is not available for $powerKey.");
        }

        if (!HNS_BoardRules::canEnterTile($targetTileId, $state['entities'], $state['entities'][$sourceEntityId], $sourceEntityId)) {
            throw new InvalidArgumentException("Move target is occupied for $powerKey.");
        }

        if ($targetTileId !== (int) $sourceTile['id']) {
            self::assertHeroCanMoveWithPower($state, $sourceEntityId, $powerKey);
            $state['entities'][$sourceEntityId]['tile_id'] = $targetTileId;
            $events[] = [
                'type' => HNS_FreeActionEngine::EVENT_AFTER_DASH,
                'source_entity_id' => $sourceEntityId,
                'target_tile_id' => $targetTileId,
            ];
        }

        foreach (self::enemyEntityIdsInArea($targetTile, $state, $power['area'], $power['area_metric'] ?? 'chebyshev') as $targetEntityId) {
            $state = self::damageEntity($targetEntityId, (int) $power['damage'], $sourceEntityId, $state, $events, !empty($power['ignores_shield']));
        }

        return ['state' => $state, 'events' => $events];
    }

    /**
     * @param array<string, mixed> $centerTile
     * @param array<string, mixed> $state
     * @param array{0:int, 1:int} $area
     * @return array<int, int>
     */
    private static function enemyEntityIdsInArea(array $centerTile, array $state, array $area, string $areaMetric): array
    {
        $entityIds = [];
        foreach ($state['entities'] as $entityId => $entity) {
            if (!in_array($entity['type'] ?? null, ['monster', 'boss'], true) || ($entity['state'] ?? 'active') !== 'active') {
                continue;
            }
            $tile = $state['tiles'][(int) ($entity['tile_id'] ?? 0)] ?? null;
            if ($tile === null) {
                continue;
            }
            $inArea = $areaMetric === 'chebyshev'
                ? HNS_BoardRules::isInDiagonalRange($centerTile, $tile, $area)
                : HNS_BoardRules::isInRange($centerTile, $tile, $area);
            if ($inArea) {
                $entityIds[] = (int) $entityId;
            }
        }
        sort($entityIds);

        return $entityIds;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $state
     * @param array<string, mixed> $power
     * @param array<int, array<string, mixed>> $events
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    private static function resolveHeal(string $powerKey, int $sourceEntityId, array $payload, array $state, array $power, array $events): array
    {
        HNS_BoardRules::assertEntityExists($state, $sourceEntityId);
        $targetEntityId = (int) ($payload['target_entity_id'] ?? $sourceEntityId);
        HNS_BoardRules::assertEntityExists($state, $targetEntityId);

        if (($state['entities'][$targetEntityId]['type'] ?? null) !== 'hero') {
            throw new InvalidArgumentException("Heal target must be a hero for $powerKey.");
        }

        $sourceTile = HNS_BoardRules::entityTile($state, $sourceEntityId);
        $targetTile = HNS_BoardRules::entityTile($state, $targetEntityId);
        $isInRange = ($power['range_metric'] ?? 'orthogonal') === 'chebyshev'
            ? HNS_BoardRules::isInDiagonalRange($sourceTile, $targetTile, $power['range'])
            : HNS_BoardRules::isInOrthogonalRange($sourceTile, $targetTile, $power['range']);
        if (!$isInRange) {
            throw new InvalidArgumentException("Heal target is out of range for $powerKey.");
        }

        $state = self::healEntity($targetEntityId, (int) $power['heal'], $sourceEntityId, $state, $events);

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
        HNS_BoardRules::assertEntityExists($state, $sourceEntityId);
        HNS_BoardRules::assertTileExists($state, $targetTileId);

        $sourceTile = HNS_BoardRules::entityTile($state, $sourceEntityId);
        $targetTile = $state['tiles'][$targetTileId];
        $rangeMetric = $power['range_metric'] ?? 'orthogonal';
        $isInRange = match ($rangeMetric) {
            'chebyshev' => HNS_BoardRules::isInDiagonalRange($sourceTile, $targetTile, $power['distance']),
            'orthogonal' => HNS_BoardRules::isInOrthogonalRange($sourceTile, $targetTile, $power['distance']),
            default => HNS_BoardRules::isInRange($sourceTile, $targetTile, $power['distance']),
        };
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

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $state
     * @param array<string, mixed> $power
     * @param array<int, array<string, mixed>> $events
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    private static function resolveJump(string $powerKey, int $sourceEntityId, array $payload, array $state, array $power, array $events): array
    {
        HNS_BoardRules::assertEntityExists($state, $sourceEntityId);
        self::assertHeroCanMoveWithPower($state, $sourceEntityId, $powerKey);
        $targetTileId = (int) ($payload['target_tile_id'] ?? $payload['selected_tile_id'] ?? 0);
        HNS_BoardRules::assertTileExists($state, $targetTileId);

        $sourceTile = HNS_BoardRules::entityTile($state, $sourceEntityId);
        $targetTile = $state['tiles'][$targetTileId];
        if (!HNS_BoardRules::isInDiagonalRange($sourceTile, $targetTile, $power['distance'])) {
            throw new InvalidArgumentException("Jump target is out of range for $powerKey.");
        }

        if (!HNS_BoardRules::isTileWalkable($targetTile)) {
            throw new InvalidArgumentException("Jump target is not available for $powerKey.");
        }

        $blockingMonsterIds = self::monsterIdsOnTile($targetTileId, $state['entities'], $sourceEntityId);
        if ($blockingMonsterIds !== [] && (int) ($power['push_distance'] ?? 0) <= 0 && (int) ($power['damage'] ?? 0) <= 0) {
            throw new InvalidArgumentException("Jump target is occupied for $powerKey.");
        }

        if ($blockingMonsterIds === [] && !HNS_BoardRules::canEnterTile($targetTileId, $state['entities'], $state['entities'][$sourceEntityId], $sourceEntityId)) {
            throw new InvalidArgumentException("Jump target is occupied for $powerKey.");
        }

        $state['entities'][$sourceEntityId]['tile_id'] = $targetTileId;
        $events[] = [
            'type' => HNS_FreeActionEngine::EVENT_AFTER_DASH,
            'source_entity_id' => $sourceEntityId,
            'target_tile_id' => $targetTileId,
        ];

        $pushedEntityIds = [];
        foreach ($blockingMonsterIds as $blockingMonsterId) {
            if ((int) ($power['damage'] ?? 0) > 0) {
                $state = self::damageEntity($blockingMonsterId, (int) $power['damage'], $sourceEntityId, $state, $events);
            }

            if ((int) ($power['push_distance'] ?? 0) > 0 && ($state['entities'][$blockingMonsterId]['state'] ?? 'active') === 'active') {
                $state = self::displaceJumpedMonster($blockingMonsterId, $sourceTile, $targetTile, $state, $events, $sourceEntityId);
                $pushedEntityIds[] = $blockingMonsterId;
            }
        }

        if ($pushedEntityIds !== []) {
            $events[] = [
                'type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL,
                'source_entity_id' => $sourceEntityId,
                'target_entity_ids' => $pushedEntityIds,
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
     * @param array<string, mixed> $sourceTile
     * @param array<string, mixed> $targetTile
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function applyThornsDamageIfMelee(int $sourceEntityId, int $targetEntityId, array $sourceTile, array $targetTile, array $state, array &$events): array
    {
        if (!in_array('thorns', $state['level_monster_abilities'] ?? [], true) || !HNS_BoardRules::isExactStep($sourceTile, $targetTile, 1)) {
            return $state;
        }

        $state = self::damageEntity($sourceEntityId, 1, $targetEntityId, $state, $events);
        $events[] = ['type' => 'thornsDamage', 'source_entity_id' => $targetEntityId, 'target_entity_id' => $sourceEntityId, 'damage' => 1];

        return $state;
    }

    /** @param array<string, mixed> $state */
    private static function assertHeroCanMoveWithPower(array $state, int $sourceEntityId, string $powerKey): void
    {
        if (($state['entities'][$sourceEntityId]['type'] ?? null) !== 'hero') {
            return;
        }

        if (self::isHeroHeldByAdjacentSlime($state, $sourceEntityId)) {
            throw new InvalidArgumentException("Slimed heroes cannot move with $powerKey.");
        }
    }

    /** @param array<string, mixed> $state */
    private static function isHeroHeldByAdjacentSlime(array $state, int $sourceEntityId): bool
    {
        $heroTile = HNS_BoardRules::entityTile($state, $sourceEntityId);
        foreach ($state['entities'] ?? [] as $entity) {
            if (($entity['type'] ?? null) !== 'monster' || (int) ($entity['type_arg'] ?? 0) !== 2 || ($entity['state'] ?? 'active') !== 'active' || (int) ($entity['health'] ?? 1) <= 0) {
                continue;
            }

            $slimeTile = $state['tiles'][(int) ($entity['tile_id'] ?? 0)] ?? null;
            if ($slimeTile !== null && HNS_BoardRules::isExactStep($heroTile, $slimeTile, 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $sourceTile
     * @param array<string, mixed> $landingTile
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function displaceJumpedMonster(int $monsterEntityId, array $sourceTile, array $landingTile, array $state, array &$events, int $sourceEntityId): array
    {
        $targetTile = self::jumpPushTargetTile($landingTile, $sourceTile, $state, $monsterEntityId)
            ?? self::jumpFallbackTile($landingTile, $sourceTile, $state, $monsterEntityId);

        if ($targetTile === null) {
            return self::damageEntity($monsterEntityId, 1, $sourceEntityId, $state, $events);
        }

        $state['entities'][$monsterEntityId]['tile_id'] = (int) $targetTile['id'];
        $events[] = ['type' => 'monsterMove', 'source_entity_id' => $monsterEntityId, 'target_tile_id' => (int) $targetTile['id']];

        if ((int) $targetTile['id'] !== (int) self::jumpPushTargetCoordinatesTileId($landingTile, $sourceTile, $state)) {
            $state = self::damageEntity($monsterEntityId, 1, $sourceEntityId, $state, $events);
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    private static function jumpPushTargetTile(array $landingTile, array $sourceTile, array $state, int $monsterEntityId): ?array
    {
        $tile = self::jumpPushTargetCoordinatesTile($landingTile, $sourceTile, $state);
        if ($tile === null) {
            return null;
        }

        return self::canRelocateMonsterToTile($monsterEntityId, (int) $tile['id'], $state) ? $tile : null;
    }

    /** @param array<string, mixed> $state */
    private static function jumpPushTargetCoordinatesTile(array $landingTile, array $sourceTile, array $state): ?array
    {
        $dx = (int) $landingTile['x'] <=> (int) $sourceTile['x'];
        $dy = (int) $landingTile['y'] <=> (int) $sourceTile['y'];

        return self::tileAtCoordinates((int) $landingTile['x'] + $dx, (int) $landingTile['y'] + $dy, $state['tiles']);
    }

    /** @param array<string, mixed> $state */
    private static function jumpPushTargetCoordinatesTileId(array $landingTile, array $sourceTile, array $state): int
    {
        $tile = self::jumpPushTargetCoordinatesTile($landingTile, $sourceTile, $state);

        return $tile === null ? 0 : (int) $tile['id'];
    }

    /** @param array<string, mixed> $state */
    private static function jumpFallbackTile(array $landingTile, array $sourceTile, array $state, int $monsterEntityId): ?array
    {
        $candidates = [];
        foreach ($state['tiles'] as $tile) {
            if (HNS_BoardRules::diagonalDistance($landingTile, $tile) !== 1) {
                continue;
            }

            if ((int) $tile['id'] === (int) $sourceTile['id']) {
                continue;
            }

            if (self::canRelocateMonsterToTile($monsterEntityId, (int) $tile['id'], $state)) {
                $candidates[] = $tile;
            }
        }

        usort($candidates, static fn (array $left, array $right): int => (int) $left['id'] <=> (int) $right['id']);

        return $candidates[0] ?? null;
    }

    /** @param array<string, mixed> $state */
    private static function canRelocateMonsterToTile(int $monsterEntityId, int $tileId, array $state): bool
    {
        $tile = $state['tiles'][$tileId] ?? null;
        if ($tile === null || !HNS_BoardRules::isTileWalkable($tile)) {
            return false;
        }

        return HNS_BoardRules::canEnterTile($tileId, $state['entities'], $state['entities'][$monsterEntityId], $monsterEntityId);
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
        HNS_BoardRules::assertEntityExists($state, $sourceEntityId);
        HNS_BoardRules::assertTileExists($state, $selectedTileId);

        if (count($targetEntityIds) > (int) $power['targets']) {
            throw new InvalidArgumentException('Too many targets for pull power.');
        }

        if (count($targetEntityIds) !== count(array_unique($targetEntityIds))) {
            throw new InvalidArgumentException('Pull power cannot target the same entity more than once.');
        }

        $sourceTile = HNS_BoardRules::entityTile($state, $sourceEntityId);
        $selectedTile = $state['tiles'][$selectedTileId];
        $isInRange = ($power['range_metric'] ?? 'orthogonal') === 'chebyshev'
            ? HNS_BoardRules::isInDiagonalRange($sourceTile, $selectedTile, $power['range'])
            : HNS_BoardRules::isInRange($sourceTile, $selectedTile, $power['range']);
        if (!$isInRange) {
            throw new InvalidArgumentException('Selected tile is out of range for pull power.');
        }
        if (!HNS_BoardRules::hasLineOfSight($sourceTile, $selectedTile, $state['tiles'])) {
            throw new InvalidArgumentException('Selected tile is not in line of sight for pull power.');
        }

        $targetEntityIds = self::sortMonstersByMovementOrder($targetEntityIds, $state['entities']);

        foreach ($targetEntityIds as $targetEntityId) {
            HNS_BoardRules::assertEntityExists($state, $targetEntityId);
            $targetTile = HNS_BoardRules::entityTile($state, $targetEntityId);
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
            $currentTile = HNS_BoardRules::entityTile($state, $entityId);
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
     * @param array<string, mixed> $sourceTile
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function pushEntityAwayFromTile(int $entityId, array $sourceTile, int $distance, array $state, array &$events, int $sourceEntityId): array
    {
        for ($step = 0; $step < $distance; $step++) {
            $currentTile = HNS_BoardRules::entityTile($state, $entityId);
            $dx = (int) $currentTile['x'] <=> (int) $sourceTile['x'];
            $dy = (int) $currentTile['y'] <=> (int) $sourceTile['y'];
            if ($dx === 0 && $dy === 0) {
                return $state;
            }

            $nextTile = self::tileAtCoordinates((int) $currentTile['x'] + $dx, (int) $currentTile['y'] + $dy, $state['tiles']);
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
     * @param array<int, array<string, mixed>> $tiles
     * @return array<string, mixed>|null
     */
    private static function tileAtCoordinates(int $x, int $y, array $tiles): ?array
    {
        foreach ($tiles as $tile) {
            if ((int) $tile['x'] === $x && (int) $tile['y'] === $y) {
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
    private static function damageEntity(int $targetEntityId, int $damage, int $sourceEntityId, array $state, array &$events, bool $ignoreShield = false): array
    {
        if (!$ignoreShield && self::hasActiveShield($state, $targetEntityId) && $damage > 0) {
            return self::breakShield($targetEntityId, $damage, $state, $events);
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

    /**
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function healEntity(int $targetEntityId, int $heal, int $sourceEntityId, array $state, array &$events): array
    {
        $currentHealth = (int) ($state['entities'][$targetEntityId]['health'] ?? 0);
        $maxHealth = self::maxHealthForEntity($targetEntityId, $state);
        $newHealth = min($maxHealth, $currentHealth + max(0, $heal));
        $healed = $newHealth - $currentHealth;
        $state['entities'][$targetEntityId]['health'] = $newHealth;

        if ($healed > 0 && ($state['entities'][$targetEntityId]['state'] ?? 'active') === 'dead') {
            $state['entities'][$targetEntityId]['state'] = 'active';
        }

        if ($healed > 0) {
            $events[] = [
                'type' => 'entityHealed',
                'source_entity_id' => $sourceEntityId,
                'target_entity_id' => $targetEntityId,
                'heal' => $healed,
                'target_health' => $newHealth,
            ];
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    private static function maxHealthForEntity(int $entityId, array $state): int
    {
        $entity = $state['entities'][$entityId] ?? [];
        if (isset($entity['max_health'])) {
            return (int) $entity['max_health'];
        }

        if (($entity['type'] ?? null) === 'hero') {
            $owner = (int) ($entity['owner'] ?? 0);
            if ($owner > 0 && isset($state['players'][$owner]['max_health'])) {
                return (int) $state['players'][$owner]['max_health'];
            }
            return defined('HNS_DEFAULT_HEALTH') ? HNS_DEFAULT_HEALTH : 10;
        }

        return max((int) ($entity['health'] ?? 0), (int) ($entity['max_health'] ?? 0));
    }

    /** @param array<string, mixed> $state */
    private static function hasActiveShield(array $state, int $targetEntityId): bool
    {
        return ($state['entities'][$targetEntityId]['type'] ?? null) === 'monster'
            && (int) ($state['entities'][$targetEntityId]['has_shield'] ?? 0) === 1
            && (int) ($state['entities'][$targetEntityId]['shield_broken'] ?? 0) !== 1;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function breakShield(int $targetEntityId, int $damageAbsorbed, array $state, array &$events): array
    {
        $state['entities'][$targetEntityId]['shield_broken'] = true;
        $events[] = ['type' => 'shieldBroken', 'source_entity_id' => $targetEntityId, 'damage_absorbed' => $damageAbsorbed];

        return $state;
    }

    /** @param array<string, mixed> $entity */
    private static function monsterMaterialForEntity(array $entity): array
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $monsterId = (int) ($entity['type_arg'] ?? 0);

        return array_merge($monsters[$monsterId] ?? [], $entity);
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
        HNS_BoardRules::assertTileExists($state, $tileId);
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
