<?php

final class HNS_MonsterAi
{
    /**
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $monsterMaterial
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    public static function activate(int $monsterEntityId, array $state, array $monsterMaterial): array
    {
        self::assertEntityExists($state, $monsterEntityId);

        $events = [];
        $monster = $state['entities'][$monsterEntityId];
        if (($monster['state'] ?? 'active') !== 'active') {
            return ['state' => $state, 'events' => $events];
        }

        $targetHeroId = self::closestHeroId($monsterEntityId, $state);
        if ($targetHeroId === null) {
            return ['state' => $state, 'events' => $events];
        }

        if (($monsterMaterial['can_attack_and_move'] ?? false) === true) {
            if (($monsterMaterial['effect'] ?? null) === 'summon_then_flee') {
                return ['state' => self::summonThenFlee($monsterEntityId, $targetHeroId, $state, $monsterMaterial, $events), 'events' => $events];
            }

            if (($monsterMaterial['can_move'] ?? true) === true) {
                $state = self::moveToward($monsterEntityId, $targetHeroId, $state, $monsterMaterial, $events);
            }

            if (($monsterMaterial['can_attack'] ?? true) === true && self::canAttack($monsterEntityId, $targetHeroId, $state, $monsterMaterial)) {
                $state = self::attack($monsterEntityId, $targetHeroId, $state, $monsterMaterial, $events);
            }

            return ['state' => $state, 'events' => $events];
        }

        $canAttackBeforeMove = ($monsterMaterial['can_attack'] ?? true) === true && self::canAttack($monsterEntityId, $targetHeroId, $state, $monsterMaterial);
        if ($canAttackBeforeMove) {
            return ['state' => self::attack($monsterEntityId, $targetHeroId, $state, $monsterMaterial, $events), 'events' => $events];
        }

        if (($monsterMaterial['can_move'] ?? true) === true) {
            $state = self::moveToAttackRange($monsterEntityId, $targetHeroId, $state, $monsterMaterial, $events);
        }

        if (($monsterMaterial['effect'] ?? null) === 'stick' && self::canAttack($monsterEntityId, $targetHeroId, $state, $monsterMaterial)) {
            $state = self::attack($monsterEntityId, $targetHeroId, $state, $monsterMaterial, $events);
        }

        return ['state' => $state, 'events' => $events];
    }

    /** @param array<string, mixed> $state */
    private static function closestHeroId(int $monsterEntityId, array $state): ?int
    {
        $monsterTile = self::entityTile($monsterEntityId, $state);
        $closestHeroId = null;
        $closestDistance = PHP_INT_MAX;

        foreach ($state['entities'] as $entityId => $entity) {
            if (($entity['type'] ?? null) !== 'hero' || ($entity['state'] ?? 'active') !== 'active') {
                continue;
            }

            $distance = HNS_BoardRules::distance($monsterTile, self::entityTile((int) $entityId, $state));
            if ($distance < $closestDistance || ($distance === $closestDistance && (int) $entityId < (int) $closestHeroId)) {
                $closestDistance = $distance;
                $closestHeroId = (int) $entityId;
            }
        }

        return $closestHeroId;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $monsterMaterial
     */
    private static function canAttack(int $monsterEntityId, int $heroEntityId, array $state, array $monsterMaterial): bool
    {
        $monsterTile = self::entityTile($monsterEntityId, $state);
        $heroTile = self::entityTile($heroEntityId, $state);
        $maxRange = (int) ($monsterMaterial['range'] ?? 0);
        $range = [(int) ($monsterMaterial['min_range'] ?? ($maxRange > 0 ? 1 : 0)), $maxRange];

        if (($monsterMaterial['range_metric'] ?? 'orthogonal') === 'front_arc') {
            return self::isTileInFrontArc($monsterTile, $heroTile, self::frontDirection($monsterTile, $heroTile));
        }

        if (($monsterMaterial['range_metric'] ?? 'orthogonal') === 'chebyshev') {
            return HNS_BoardRules::isInDiagonalRange($monsterTile, $heroTile, $range);
        }

        return HNS_BoardRules::isInOrthogonalRange($monsterTile, $heroTile, $range);
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $monsterMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function attack(int $monsterEntityId, int $heroEntityId, array $state, array $monsterMaterial, array &$events): array
    {
        $damage = (int) ($monsterMaterial['damage'] ?? 0);
        if (($monsterMaterial['effect'] ?? 'damage') === 'stick') {
            $state['entities'][$heroEntityId]['status'] = 'slimed';
            $events[] = ['type' => 'monsterStick', 'source_entity_id' => $monsterEntityId, 'target_entity_id' => $heroEntityId];

            return $state;
        }

        if (($monsterMaterial['effect'] ?? 'damage') === 'explode') {
            return self::explode($monsterEntityId, $state, $monsterMaterial, $events);
        }

        if (($monsterMaterial['effect'] ?? 'damage') === 'front_arc_damage') {
            return self::frontArcAttack($monsterEntityId, $heroEntityId, $state, $monsterMaterial, $events);
        }

        if (($monsterMaterial['effect'] ?? 'damage') === 'charge') {
            return self::chargeAttack($monsterEntityId, $heroEntityId, $state, $monsterMaterial, $events);
        }

        if ($damage > 0) {
            $state['entities'][$heroEntityId]['health'] = max(0, (int) $state['entities'][$heroEntityId]['health'] - $damage);
            if ((int) $state['entities'][$heroEntityId]['health'] === 0) {
                $state['entities'][$heroEntityId]['state'] = 'dead';
            }
        }

        $events[] = ['type' => 'monsterAttack', 'source_entity_id' => $monsterEntityId, 'target_entity_id' => $heroEntityId, 'damage' => $damage, 'target_health' => (int) $state['entities'][$heroEntityId]['health']];

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $monsterMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function summonThenFlee(int $monsterEntityId, int $heroEntityId, array $state, array $monsterMaterial, array &$events): array
    {
        $summonTile = self::bestAdjacentSummonTile($monsterEntityId, $state);
        if ($summonTile !== null) {
            $summonedEntityId = self::nextEntityId($state['entities']);
            $summonedEntity = [
                'id' => $summonedEntityId,
                'type' => 'monster',
                'type_arg' => (int) ($monsterMaterial['summon_monster_id'] ?? 0),
                'monster_size' => 'small',
                'tile_id' => (int) $summonTile['id'],
                'health' => 1,
                'state' => 'active',
            ];
            $state['entities'][$summonedEntityId] = $summonedEntity;
            $events[] = ['type' => 'monsterSummon', 'source_entity_id' => $monsterEntityId, 'summoned_entity_id' => $summonedEntityId, 'monster_id' => (int) ($monsterMaterial['summon_monster_id'] ?? 0), 'target_tile_id' => (int) $summonTile['id'], 'summoned_entity' => $summonedEntity];
        }

        $fleeTile = self::bestAdjacentTileAwayFrom($monsterEntityId, $heroEntityId, $state);
        if ($fleeTile !== null) {
            $state['entities'][$monsterEntityId]['tile_id'] = (int) $fleeTile['id'];
            $events[] = ['type' => 'monsterMove', 'source_entity_id' => $monsterEntityId, 'target_tile_id' => (int) $fleeTile['id']];
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    private static function bestAdjacentSummonTile(int $monsterEntityId, array $state): ?array
    {
        $monsterTile = self::entityTile($monsterEntityId, $state);
        foreach ($state['tiles'] as $tile) {
            if (HNS_BoardRules::distance($monsterTile, $tile) !== 1 || !HNS_BoardRules::isTileWalkable($tile)) {
                continue;
            }

            $summon = ['id' => 0, 'type' => 'monster', 'monster_size' => 'small', 'state' => 'active'];
            if (HNS_BoardRules::canEnterTile((int) $tile['id'], $state['entities'], $summon, 0)) {
                return $tile;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $state */
    private static function bestAdjacentTileAwayFrom(int $monsterEntityId, int $heroEntityId, array $state): ?array
    {
        $monsterTile = self::entityTile($monsterEntityId, $state);
        $heroTile = self::entityTile($heroEntityId, $state);
        $currentDistance = HNS_BoardRules::distance($monsterTile, $heroTile);
        $bestTile = null;
        $bestDistance = $currentDistance;

        foreach ($state['tiles'] as $tile) {
            if (HNS_BoardRules::distance($monsterTile, $tile) !== 1 || !HNS_BoardRules::isTileWalkable($tile)) {
                continue;
            }

            if (!HNS_BoardRules::canEnterTile((int) $tile['id'], $state['entities'], $state['entities'][$monsterEntityId], $monsterEntityId)) {
                continue;
            }

            $distance = HNS_BoardRules::distance($tile, $heroTile);
            if ($distance > $bestDistance || ($distance === $bestDistance && $bestTile !== null && (int) $tile['id'] < (int) $bestTile['id'])) {
                $bestDistance = $distance;
                $bestTile = $tile;
            }
        }

        return $bestTile;
    }

    /** @param array<int, array<string, mixed>> $entities */
    private static function nextEntityId(array $entities): int
    {
        return max(array_map('intval', array_keys($entities))) + 1;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $monsterMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function chargeAttack(int $monsterEntityId, int $heroEntityId, array $state, array $monsterMaterial, array &$events): array
    {
        $monsterTile = self::entityTile($monsterEntityId, $state);
        $heroTile = self::entityTile($heroEntityId, $state);
        $dx = (int) $heroTile['x'] - (int) $monsterTile['x'];
        $dy = (int) $heroTile['y'] - (int) $monsterTile['y'];
        $pushX = (int) $heroTile['x'] + $dx;
        $pushY = (int) $heroTile['y'] + $dy;
        $pushTile = self::tileAt($pushX, $pushY, $state['tiles']);

        $state['entities'][$heroEntityId]['health'] = max(0, (int) $state['entities'][$heroEntityId]['health'] - (int) ($monsterMaterial['damage'] ?? 0));
        if ($pushTile !== null && HNS_BoardRules::isTileWalkable($pushTile) && HNS_BoardRules::canEnterTile((int) $pushTile['id'], $state['entities'], $state['entities'][$heroEntityId], $heroEntityId)) {
            $state['entities'][$heroEntityId]['tile_id'] = (int) $pushTile['id'];
            $state['entities'][$monsterEntityId]['tile_id'] = (int) $heroTile['id'];
        } else {
            $state['entities'][$heroEntityId]['health'] = max(0, (int) $state['entities'][$heroEntityId]['health'] - 1);
            $state['entities'][$monsterEntityId]['health'] = max(0, (int) $state['entities'][$monsterEntityId]['health'] - 1);
        }

        if ((int) $state['entities'][$heroEntityId]['health'] === 0) {
            $state['entities'][$heroEntityId]['state'] = 'dead';
        }
        if ((int) $state['entities'][$monsterEntityId]['health'] === 0) {
            $state['entities'][$monsterEntityId]['state'] = 'dead';
        }

        $events[] = ['type' => 'monsterCharge', 'source_entity_id' => $monsterEntityId, 'target_entity_id' => $heroEntityId, 'damage' => (int) ($monsterMaterial['damage'] ?? 0), 'push_tile_id' => $pushTile['id'] ?? null];

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $monsterMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function frontArcAttack(int $monsterEntityId, int $heroEntityId, array $state, array $monsterMaterial, array &$events): array
    {
        $monsterTile = self::entityTile($monsterEntityId, $state);
        $heroTile = self::entityTile($heroEntityId, $state);
        $direction = self::frontDirection($monsterTile, $heroTile);
        $damage = (int) ($monsterMaterial['damage'] ?? 0);
        $targetHeroIds = [];

        foreach ($state['entities'] as $entityId => $entity) {
            if (($entity['type'] ?? null) !== 'hero' || ($entity['state'] ?? 'active') !== 'active') {
                continue;
            }

            if (!self::isTileInFrontArc($monsterTile, self::entityTile((int) $entityId, $state), $direction)) {
                continue;
            }

            $state['entities'][$entityId]['health'] = max(0, (int) $entity['health'] - $damage);
            if ((int) $state['entities'][$entityId]['health'] === 0) {
                $state['entities'][$entityId]['state'] = 'dead';
            }
            $targetHeroIds[] = (int) $entityId;
        }

        $events[] = ['type' => 'monsterFrontArcAttack', 'source_entity_id' => $monsterEntityId, 'target_entity_ids' => $targetHeroIds, 'damage' => $damage];

        return $state;
    }

    /**
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     * @return array{x:int, y:int}
     */
    private static function frontDirection(array $from, array $to): array
    {
        $dx = (int) $to['x'] - (int) $from['x'];
        $dy = (int) $to['y'] - (int) $from['y'];

        if (abs($dx) >= abs($dy)) {
            return ['x' => $dx <=> 0, 'y' => 0];
        }

        return ['x' => 0, 'y' => $dy <=> 0];
    }

    /**
     * @param array<string, mixed> $origin
     * @param array<string, mixed> $tile
     * @param array{x:int, y:int} $direction
     */
    private static function isTileInFrontArc(array $origin, array $tile, array $direction): bool
    {
        $dx = (int) $tile['x'] - (int) $origin['x'];
        $dy = (int) $tile['y'] - (int) $origin['y'];

        if ($direction['x'] !== 0) {
            return $dx === $direction['x'] && abs($dy) <= 1;
        }

        return $dy === $direction['y'] && abs($dx) <= 1;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $monsterMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    public static function explode(int $monsterEntityId, array $state, array $monsterMaterial, array &$events): array
    {
        $monsterTile = self::entityTile($monsterEntityId, $state);
        $damage = (int) ($monsterMaterial['damage'] ?? 0);
        $targetHeroIds = [];
        $targetHealthByEntityId = [];

        foreach ($state['entities'] as $entityId => $entity) {
            if (($entity['type'] ?? null) !== 'hero' || ($entity['state'] ?? 'active') !== 'active') {
                continue;
            }

            if (!HNS_BoardRules::isInOrthogonalRange($monsterTile, self::entityTile((int) $entityId, $state), [1, 1])) {
                continue;
            }

            $state['entities'][$entityId]['health'] = max(0, (int) $entity['health'] - $damage);
            if ((int) $state['entities'][$entityId]['health'] === 0) {
                $state['entities'][$entityId]['state'] = 'dead';
            }
            $targetHeroIds[] = (int) $entityId;
            $targetHealthByEntityId[(int) $entityId] = (int) $state['entities'][$entityId]['health'];
        }

        $state['entities'][$monsterEntityId]['health'] = 0;
        $state['entities'][$monsterEntityId]['state'] = 'dead';
        $events[] = ['type' => 'monsterExplode', 'source_entity_id' => $monsterEntityId, 'target_entity_ids' => $targetHeroIds, 'target_health_by_entity_id' => $targetHealthByEntityId, 'damage' => $damage];

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $monsterMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function moveToward(int $monsterEntityId, int $heroEntityId, array $state, array $monsterMaterial, array &$events): array
    {
        $steps = (int) ($monsterMaterial['move'] ?? 0);
        for ($step = 0; $step < $steps; $step++) {
            $currentTile = self::entityTile($monsterEntityId, $state);
            $targetTile = self::entityTile($heroEntityId, $state);
            $nextTile = self::bestAdjacentTileToward($currentTile, $targetTile, $state['tiles'], $state['entities'], $monsterEntityId, $monsterMaterial['move_metric'] ?? 'orthogonal');
            if ($nextTile === null) {
                break;
            }

            $state['entities'][$monsterEntityId]['tile_id'] = (int) $nextTile['id'];
            $events[] = ['type' => 'monsterMove', 'source_entity_id' => $monsterEntityId, 'target_tile_id' => (int) $nextTile['id']];
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $monsterMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function moveToAttackRange(int $monsterEntityId, int $heroEntityId, array $state, array $monsterMaterial, array &$events): array
    {
        if (isset($monsterMaterial['min_range'])) {
            return self::moveTowardPreferredRange($monsterEntityId, $heroEntityId, $state, $monsterMaterial, $events);
        }

        return self::moveToward($monsterEntityId, $heroEntityId, $state, $monsterMaterial, $events);
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $monsterMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function moveTowardPreferredRange(int $monsterEntityId, int $heroEntityId, array $state, array $monsterMaterial, array &$events): array
    {
        $steps = (int) ($monsterMaterial['move'] ?? 0);
        for ($step = 0; $step < $steps; $step++) {
            $currentTile = self::entityTile($monsterEntityId, $state);
            $targetTile = self::entityTile($heroEntityId, $state);
            $nextTile = self::bestAdjacentTileTowardPreferredRange($currentTile, $targetTile, $state['tiles'], $state['entities'], $monsterEntityId, $monsterMaterial);
            if ($nextTile === null) {
                break;
            }

            $state['entities'][$monsterEntityId]['tile_id'] = (int) $nextTile['id'];
            $events[] = ['type' => 'monsterMove', 'source_entity_id' => $monsterEntityId, 'target_tile_id' => (int) $nextTile['id']];
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
    private static function bestAdjacentTileToward(array $from, array $to, array $tiles, array $entities, int $movingEntityId, string $moveMetric): ?array
    {
        $distanceFn = $moveMetric === 'chebyshev'
            ? static fn (array $left, array $right): int => HNS_BoardRules::diagonalDistance($left, $right)
            : static fn (array $left, array $right): int => HNS_BoardRules::distance($left, $right);
        $currentDistance = $distanceFn($from, $to);
        $bestTile = null;
        $bestDistance = $currentDistance;

        foreach ($tiles as $tile) {
            if ($distanceFn($from, $tile) !== 1 || !HNS_BoardRules::isTileWalkable($tile)) {
                continue;
            }

            if (!HNS_BoardRules::canEnterTile((int) $tile['id'], $entities, $entities[$movingEntityId], $movingEntityId)) {
                continue;
            }

            $distance = $distanceFn($tile, $to);
            if ($distance < $bestDistance || ($distance === $bestDistance && (int) $tile['id'] < (int) ($bestTile['id'] ?? PHP_INT_MAX))) {
                $bestDistance = $distance;
                $bestTile = $tile;
            }
        }

        return $bestTile;
    }

    /**
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     * @param array<int, array<string, mixed>> $tiles
     * @param array<int, array<string, mixed>> $entities
     * @param array<string, mixed> $monsterMaterial
     * @return array<string, mixed>|null
     */
    private static function bestAdjacentTileTowardPreferredRange(array $from, array $to, array $tiles, array $entities, int $movingEntityId, array $monsterMaterial): ?array
    {
        $moveDistanceFn = ($monsterMaterial['move_metric'] ?? 'orthogonal') === 'chebyshev'
            ? static fn (array $left, array $right): int => HNS_BoardRules::diagonalDistance($left, $right)
            : static fn (array $left, array $right): int => HNS_BoardRules::distance($left, $right);
        $rangeDistanceFn = ($monsterMaterial['range_metric'] ?? 'orthogonal') === 'chebyshev'
            ? static fn (array $left, array $right): int => HNS_BoardRules::diagonalDistance($left, $right)
            : static fn (array $left, array $right): int => HNS_BoardRules::distance($left, $right);
        $minRange = (int) ($monsterMaterial['min_range'] ?? 1);
        $maxRange = (int) ($monsterMaterial['range'] ?? 0);
        $currentScore = self::rangeBandScore($rangeDistanceFn($from, $to), $minRange, $maxRange);
        $bestTile = null;
        $bestScore = $currentScore;

        foreach ($tiles as $tile) {
            if ($moveDistanceFn($from, $tile) !== 1 || !HNS_BoardRules::isTileWalkable($tile)) {
                continue;
            }
            if (!HNS_BoardRules::canEnterTile((int) $tile['id'], $entities, $entities[$movingEntityId], $movingEntityId)) {
                continue;
            }
            if (($monsterMaterial['range_metric'] ?? 'orthogonal') === 'orthogonal' && (int) $tile['x'] !== (int) $to['x'] && (int) $tile['y'] !== (int) $to['y']) {
                continue;
            }

            $score = self::rangeBandScore($rangeDistanceFn($tile, $to), $minRange, $maxRange);
            if ($score < $bestScore || ($score === $bestScore && (int) $tile['id'] < (int) ($bestTile['id'] ?? PHP_INT_MAX))) {
                $bestScore = $score;
                $bestTile = $tile;
            }
        }

        return $bestTile;
    }

    private static function rangeBandScore(int $distance, int $minRange, int $maxRange): int
    {
        if ($distance < $minRange) {
            return $minRange - $distance;
        }
        if ($distance > $maxRange) {
            return $distance - $maxRange;
        }

        return 0;
    }

    /**
     * @param array<int, array<string, mixed>> $tiles
     * @return array<string, mixed>|null
     */
    private static function tileAt(int $x, int $y, array $tiles): ?array
    {
        foreach ($tiles as $tile) {
            if ((int) $tile['x'] === $x && (int) $tile['y'] === $y) {
                return $tile;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $state */
    private static function entityTile(int $entityId, array $state): array
    {
        self::assertEntityExists($state, $entityId);
        $tileId = (int) $state['entities'][$entityId]['tile_id'];

        return $state['tiles'][$tileId];
    }

    /** @param array<string, mixed> $state */
    private static function assertEntityExists(array $state, int $entityId): void
    {
        if (!isset($state['entities'][$entityId])) {
            throw new InvalidArgumentException("Unknown entity $entityId.");
        }
    }
}
