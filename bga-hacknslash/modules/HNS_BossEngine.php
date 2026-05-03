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
        $state = self::activateSpawnedMinions($state, $events);
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
            }
        }

        return $state;
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

            if (($entity['shield_broken'] ?? false) === false && ($entity['has_shield'] ?? false) === true) {
                continue;
            }

            $entity['has_shield'] = true;
            $entity['shield_broken'] = false;
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
            $tile = self::firstAvailableAdjacentTile($bossEntityId, $state);
            if ($tile === null) {
                break;
            }

            $monsterId = (int) $rng->pick($monsterIds);
            $entityId = self::nextEntityId($state['entities']);
            $material = $state['monster_material'][$monsterId] ?? [];
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
                $state['entities'][$entityId]['has_shield'] = true;
                $state['entities'][$entityId]['shield_broken'] = false;
            }
            $state['_boss_spawned_entity_ids'][] = $entityId;
            $events[] = ['type' => 'bossSpawnMinion', 'source_entity_id' => $bossEntityId, 'summoned_entity_id' => $entityId, 'monster_id' => $monsterId, 'target_tile_id' => (int) $tile['id']];
        }

        $state['boss_spawn_seed'] = $seed + $count;

        return $state;
    }

    /** @param array<string, mixed> $state */
    private static function firstAvailableAdjacentTile(int $bossEntityId, array $state): ?array
    {
        $bossTile = $state['tiles'][(int) $state['entities'][$bossEntityId]['tile_id']];
        foreach ($state['tiles'] as $tile) {
            if (HNS_BoardRules::distance($bossTile, $tile) !== 1 || !HNS_BoardRules::isTileWalkable($tile)) {
                continue;
            }

            $summon = ['id' => 0, 'type' => 'monster', 'monster_size' => 'small', 'state' => 'active'];
            if (HNS_BoardRules::canEnterTile((int) $tile['id'], $state['entities'], $summon, 0)) {
                return $tile;
            }
        }

        return null;
    }

    /** @param array<int, array<string, mixed>> $entities */
    private static function nextEntityId(array $entities): int
    {
        return max(array_map('intval', array_keys($entities))) + 1;
    }

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
