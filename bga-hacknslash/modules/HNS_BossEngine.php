<?php

final class HNS_BossEngine
{
    /**
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $bossMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    public static function resolveBossDefeat(int $bossEntityId, array $state, array $bossMaterial, array &$events): array
    {
        $boss = $state['entities'][$bossEntityId];
        $bossKey = (string) ($boss['boss_key'] ?? '');
        $phase = (int) ($boss['phase'] ?? 1);

        if (!isset($bossMaterial[$bossKey])) {
            throw new InvalidArgumentException("Unknown boss $bossKey.");
        }

        $events[] = ['type' => 'bossPhaseDefeated', 'source_entity_id' => $bossEntityId, 'boss_key' => $bossKey, 'phase' => $phase];

        if ($phase >= 3) {
            $state['entities'][$bossEntityId]['state'] = 'dead';
            $state['game_over'] = true;
            $state['game_won'] = true;
            $events[] = ['type' => 'gameWon'];

            return $state;
        }

        $nextPhase = $phase + 1;
        $phaseMaterial = $bossMaterial[$bossKey]['phases'][$nextPhase] ?? null;
        if ($phaseMaterial === null || !isset($phaseMaterial['health'])) {
            throw new InvalidArgumentException("Boss $bossKey has no phase $nextPhase health.");
        }

        $state['entities'][$bossEntityId]['phase'] = $nextPhase;
        $state['entities'][$bossEntityId]['health'] = (int) $phaseMaterial['health'];
        $state['entities'][$bossEntityId]['state'] = 'active';
        $events[] = [
            'type' => 'bossPhaseStarted',
            'source_entity_id' => $bossEntityId,
            'entity_id' => $bossEntityId,
            'boss_key' => $bossKey,
            'phase' => $nextPhase,
            'health' => (int) $phaseMaterial['health'],
        ];

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $bossMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    public static function activateBossTurn(int $bossEntityId, array $state, array $bossMaterial, array &$events, bool $canMove = true): array
    {
        $boss = $state['entities'][$bossEntityId];
        $bossKey = (string) ($boss['boss_key'] ?? '');
        $phase = (int) ($boss['phase'] ?? 1);
        $phaseMaterial = $bossMaterial[$bossKey]['phases'][$phase] ?? null;
        if ($phaseMaterial === null) {
            $events[] = ['type' => 'bossTurnSkipped', 'source_entity_id' => $bossEntityId, 'boss_key' => $bossKey, 'phase' => $phase];

            return $state;
        }
        if (!$canMove) {
            $phaseMaterial['can_move'] = false;
        }

        $state = self::resolvePreActions($bossEntityId, $state, $phaseMaterial, $events);
        if (($state['entities'][$bossEntityId]['state'] ?? 'active') !== 'active') {
            return $state;
        }

        $state = self::activateSpawnedMinions($state, $events);
        if (($phaseMaterial['effect'] ?? null) === 'move_area_attack') {
            return self::resolveMoveAreaAttack($bossEntityId, $state, $phaseMaterial, $events);
        }

        $result = HNS_MonsterAi::activate($bossEntityId, $state, $phaseMaterial);
        array_push($events, ...$result['events']);

        return $result['state'];
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $phaseMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function resolvePreActions(int $bossEntityId, array $state, array $phaseMaterial, array &$events): array
    {
        foreach ($phaseMaterial['pre_actions'] ?? [] as $action) {
            if (($action['type'] ?? null) === 'grant_shield') {
                $state = self::grantShieldToCreatures($state, $events);
                continue;
            }

            if (($action['type'] ?? null) === 'spawn_minions') {
                $state = self::spawnMinions($bossEntityId, $state, $action, $events);
                continue;
            }

            if (($action['type'] ?? null) === 'charge') {
                $result = HNS_MonsterAi::activate($bossEntityId, $state, [
                    'effect' => 'charge',
                    'damage' => (int) ($action['damage'] ?? 0),
                    'range' => (int) ($action['range'] ?? 1),
                    'range_metric' => $action['range_metric'] ?? 'orthogonal',
                    'can_attack' => true,
                    'can_move' => false,
                    'can_attack_and_move' => false,
                ]);
                $state = $result['state'];
                array_push($events, ...$result['events']);
            }
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $phaseMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function resolveMoveAreaAttack(int $bossEntityId, array $state, array $phaseMaterial, array &$events): array
    {
        $targetHeroId = self::closestHeroId($bossEntityId, $state);
        if ($targetHeroId === null) {
            return $state;
        }

        if (($phaseMaterial['can_move'] ?? true) === true) {
            $state = self::moveToward($bossEntityId, $targetHeroId, $state, $phaseMaterial, $events);
        }

        $bossTile = HNS_BoardRules::entityTile($state, $bossEntityId);
        foreach (self::heroEntityIdsInArea($bossTile, $state, $phaseMaterial['area'] ?? [0, 0], $phaseMaterial['area_metric'] ?? 'chebyshev') as $heroEntityId) {
            $damage = (int) ($phaseMaterial['damage'] ?? 0);
            $state['entities'][$heroEntityId]['health'] = max(0, (int) $state['entities'][$heroEntityId]['health'] - $damage);
            if ((int) $state['entities'][$heroEntityId]['health'] === 0) {
                $state['entities'][$heroEntityId]['state'] = 'dead';
            }
            $events[] = [
                'type' => 'monsterAttack',
                'source_entity_id' => $bossEntityId,
                'target_entity_id' => $heroEntityId,
                'damage' => $damage,
                'target_health' => (int) $state['entities'][$heroEntityId]['health'],
            ];
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    private static function closestHeroId(int $bossEntityId, array $state): ?int
    {
        $bossTile = HNS_BoardRules::entityTile($state, $bossEntityId);
        $closestHeroId = null;
        $closestDistance = PHP_INT_MAX;

        foreach ($state['entities'] as $entityId => $entity) {
            if (($entity['type'] ?? null) !== 'hero' || ($entity['state'] ?? 'active') !== 'active') {
                continue;
            }

            $distance = HNS_BoardRules::distance($bossTile, HNS_BoardRules::entityTile($state, (int) $entityId));
            if ($distance < $closestDistance || ($distance === $closestDistance && (int) $entityId < (int) ($closestHeroId ?? PHP_INT_MAX))) {
                $closestDistance = $distance;
                $closestHeroId = (int) $entityId;
            }
        }

        return $closestHeroId;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $phaseMaterial
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function moveToward(int $bossEntityId, int $heroEntityId, array $state, array $phaseMaterial, array &$events): array
    {
        $steps = (int) ($phaseMaterial['move'] ?? 0);
        if ($steps <= 0) {
            return $state;
        }

        $from = HNS_BoardRules::entityTile($state, $bossEntityId);
        $to = HNS_BoardRules::entityTile($state, $heroEntityId);
        $tile = self::bestReachableTileToward($from, $to, $state, $bossEntityId, $phaseMaterial['move_metric'] ?? 'orthogonal', $steps);
        if ($tile !== null && (int) $tile['id'] !== (int) $from['id']) {
            $state['entities'][$bossEntityId]['tile_id'] = (int) $tile['id'];
            $events[] = ['type' => 'monsterMove', 'source_entity_id' => $bossEntityId, 'target_tile_id' => (int) $tile['id']];
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     * @param array<string, mixed> $state
     * @return array<string, mixed>|null
     */
    private static function bestReachableTileToward(array $from, array $to, array $state, int $movingEntityId, string $moveMetric, int $maxSteps): ?array
    {
        $distanceFn = $moveMetric === 'chebyshev'
            ? static fn (array $left, array $right): int => HNS_BoardRules::diagonalDistance($left, $right)
            : static fn (array $left, array $right): int => HNS_BoardRules::distance($left, $right);
        $bestTile = null;
        $bestDistance = $distanceFn($from, $to);
        $bestSteps = PHP_INT_MAX;

        foreach (self::reachableTilesBySteps($from, $state, $movingEntityId, $moveMetric, $maxSteps) as $tileId => $steps) {
            $tile = $state['tiles'][$tileId];
            $distance = $distanceFn($tile, $to);
            if ($distance < $bestDistance || ($distance === $bestDistance && ($steps < $bestSteps || ($steps === $bestSteps && (int) $tile['id'] < (int) ($bestTile['id'] ?? PHP_INT_MAX))))) {
                $bestDistance = $distance;
                $bestSteps = $steps;
                $bestTile = $tile;
            }
        }

        return $bestTile;
    }

    /**
     * @param array<string, mixed> $from
     * @param array<string, mixed> $state
     * @return array<int, int>
     */
    private static function reachableTilesBySteps(array $from, array $state, int $movingEntityId, string $moveMetric, int $maxSteps): array
    {
        $queue = [(int) $from['id']];
        $stepsByTileId = [(int) $from['id'] => 0];

        for ($index = 0; $index < count($queue); $index++) {
            $tileId = $queue[$index];
            $steps = $stepsByTileId[$tileId];
            if ($steps >= $maxSteps) {
                continue;
            }

            foreach (self::neighboursForMoveMetric($state['tiles'][$tileId], $state['tiles'], $moveMetric) as $tile) {
                $nextTileId = (int) $tile['id'];
                if (isset($stepsByTileId[$nextTileId]) || !HNS_BoardRules::isTileWalkable($tile)) {
                    continue;
                }
                if (!HNS_BoardRules::canEnterTile($nextTileId, $state['entities'], $state['entities'][$movingEntityId], $movingEntityId)) {
                    continue;
                }

                $stepsByTileId[$nextTileId] = $steps + 1;
                $queue[] = $nextTileId;
            }
        }

        unset($stepsByTileId[(int) $from['id']]);

        return $stepsByTileId;
    }

    /**
     * @param array<string, mixed> $tile
     * @param array<int, array<string, mixed>> $tiles
     * @return array<int, array<string, mixed>>
     */
    private static function neighboursForMoveMetric(array $tile, array $tiles, string $moveMetric): array
    {
        $neighbours = [];
        foreach ($tiles as $candidate) {
            $distance = $moveMetric === 'chebyshev'
                ? HNS_BoardRules::diagonalDistance($tile, $candidate)
                : HNS_BoardRules::distance($tile, $candidate);
            if ($distance === 1) {
                $neighbours[] = $candidate;
            }
        }

        return $neighbours;
    }

    /**
     * @param array<string, mixed> $centerTile
     * @param array<string, mixed> $state
     * @param array{0:int, 1:int} $area
     * @return array<int, int>
     */
    private static function heroEntityIdsInArea(array $centerTile, array $state, array $area, string $areaMetric): array
    {
        $heroIds = [];
        foreach ($state['entities'] as $entityId => $entity) {
            if (($entity['type'] ?? null) !== 'hero' || ($entity['state'] ?? 'active') !== 'active') {
                continue;
            }

            $tile = HNS_BoardRules::entityTile($state, (int) $entityId);
            $inArea = $areaMetric === 'chebyshev'
                ? HNS_BoardRules::isInDiagonalRange($centerTile, $tile, $area)
                : HNS_BoardRules::isInOrthogonalRange($centerTile, $tile, $area);
            if ($inArea) {
                $heroIds[] = (int) $entityId;
            }
        }
        sort($heroIds);

        return $heroIds;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function activateSpawnedMinions(array $state, array &$events): array
    {
        $spawnedEntityIds = $state['_boss_spawned_entity_ids'] ?? [];
        unset($state['_boss_spawned_entity_ids']);
        foreach ($spawnedEntityIds as $entityId) {
            $entityId = (int) $entityId;
            if (($state['entities'][$entityId]['state'] ?? null) !== 'active') {
                continue;
            }

            $monsterId = (int) ($state['entities'][$entityId]['type_arg'] ?? 0);
            $material = $state['monster_material'][$monsterId] ?? null;
            if (!is_array($material)) {
                continue;
            }

            // Spawned minions join the boss turn by advancing, but the boss keeps
            // the actual attack beat for this phase.
            $material['can_attack'] = false;
            $result = HNS_MonsterAi::activate($entityId, $state, $material);
            $state = $result['state'];
            array_push($events, ...$result['events']);
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function grantShieldToCreatures(array $state, array &$events): array
    {
        $shielded = [];
        foreach ($state['entities'] as $entityId => &$entity) {
            if (!in_array($entity['type'] ?? null, ['monster', 'boss'], true) || ($entity['state'] ?? 'active') !== 'active') {
                continue;
            }

            $entity['has_shield'] = 1;
            $entity['shield_broken'] = 0;
            $shielded[] = (int) $entityId;
        }

        $state['level_monster_abilities'] = array_values(array_unique([...(array) ($state['level_monster_abilities'] ?? []), 'shield']));
        $events[] = ['type' => 'bossGrantShield', 'target_entity_ids' => $shielded];

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $action
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private static function spawnMinions(int $bossEntityId, array $state, array $action, array &$events): array
    {
        $monsterIds = $action['monster_ids'] ?? [1];
        $count = (int) ($action['count'] ?? 1);
        $seed = (int) ($state['boss_spawn_seed'] ?? 1);
        $rng = new HNS_SeededRandom($seed);

        for ($index = 0; $index < $count; $index++) {
            [$monsterId, $material, $tile] = self::pickSpawnableMinion($bossEntityId, $state, $monsterIds, $rng);
            if ($tile === null) {
                break;
            }

            $entityId = self::nextEntityId($state['entities']);
            $state['entities'][$entityId] = [
                'id' => $entityId,
                'type' => 'monster',
                'type_arg' => $monsterId,
                'monster_size' => $material['size'] ?? 'small',
                'tile_id' => (int) $tile['id'],
                'health' => (int) ($material['health'] ?? 1),
                'state' => 'active',
                'on_death' => $material['on_death'] ?? null,
                'damage' => $material['damage'] ?? 0,
            ];
            if (in_array('shield', $state['level_monster_abilities'] ?? [], true)) {
                $state['entities'][$entityId]['has_shield'] = 1;
                $state['entities'][$entityId]['shield_broken'] = 0;
            }
            $state['_boss_spawned_entity_ids'][] = $entityId;
            $events[] = ['type' => 'bossSpawnMinion', 'source_entity_id' => $bossEntityId, 'summoned_entity_id' => $entityId, 'monster_id' => $monsterId, 'target_tile_id' => (int) $tile['id']];
        }

        $state['boss_spawn_seed'] = $seed + $count;

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<int, int> $monsterIds
     * @return array{0:int, 1:array<string, mixed>, 2:array<string, mixed>|null}
     */
    private static function pickSpawnableMinion(int $bossEntityId, array $state, array $monsterIds, HNS_SeededRandom $rng): array
    {
        $preferredMonsterId = (int) $rng->pick($monsterIds);
        $candidateMonsterIds = array_values(array_unique([$preferredMonsterId, ...array_map('intval', $monsterIds)]));

        foreach ($candidateMonsterIds as $monsterId) {
            $material = $state['monster_material'][$monsterId] ?? [];
            $tile = self::firstAvailableAdjacentTile($bossEntityId, $state, $monsterId, $material['size'] ?? 'small');
            if ($tile !== null) {
                return [$monsterId, $material, $tile];
            }
        }

        return [$preferredMonsterId, $state['monster_material'][$preferredMonsterId] ?? [], null];
    }

    /** @param array<string, mixed> $state */
    private static function firstAvailableAdjacentTile(int $bossEntityId, array $state, int $monsterId, string $monsterSize): ?array
    {
        $bossTile = $state['tiles'][(int) $state['entities'][$bossEntityId]['tile_id']];
        foreach ($state['tiles'] as $tile) {
            if (HNS_BoardRules::distance($bossTile, $tile) !== 1 || !HNS_BoardRules::isTileWalkable($tile)) {
                continue;
            }

            $summon = ['id' => 0, 'type' => 'monster', 'type_arg' => $monsterId, 'monster_size' => $monsterSize, 'tile_id' => (int) ($state['entities'][$bossEntityId]['tile_id'] ?? 0), 'state' => 'active'];
            if (HNS_BoardRules::canEnterTile((int) $tile['id'], $state['entities'], $summon, 0)) {
                return $tile;
            }
        }

        return null;
    }

    /** @param array<int, array<string, mixed>> $entities */
    private static function nextEntityId(array $entities): int
    {
        return empty($entities) ? 1 : max(array_map('intval', array_keys($entities))) + 1;
    }

    /** @param array<int, array<string, mixed>> $entities */
    /** @param array<string, array<string, mixed>> $bossMaterial */
    public static function initialBossEntity(string $bossKey, int $entityId, int $tileId, array $bossMaterial): array
    {
        $phase = $bossMaterial[$bossKey]['phases'][1] ?? null;
        if ($phase === null) {
            throw new InvalidArgumentException("Boss $bossKey has no phase 1 material.");
        }

        return [
            'id' => $entityId,
            'type' => 'boss',
            'boss_key' => $bossKey,
            'phase' => 1,
            'monster_size' => 'boss',
            'tile_id' => $tileId,
            'health' => (int) $phase['health'],
            'state' => 'active',
        ];
    }
}
