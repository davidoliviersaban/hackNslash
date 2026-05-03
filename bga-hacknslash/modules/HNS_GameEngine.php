<?php

final class HNS_GameEngine
{
    /**
     * @param array<int, array<string, mixed>> $monsterMaterial
     * @param array<int, int> $monsterDeck
     * @param array<int, string> $enchantmentDeck
     * @return array<string, mixed>
     */
    public static function createLevel(int $levelNumber, int $seed, array $monsterMaterial, array $monsterDeck, array $enchantmentDeck = []): array
    {
        if ($levelNumber === HNS_BOSS_LEVEL) {
            return [
                'level' => $levelNumber,
                'is_boss_level' => true,
                'level_monster_abilities' => [],
                'tiles' => [],
                'entities' => [],
            'monster_slots' => [],
            'reward_offer' => [],
            'reward_upgrades' => [],
        ];
        }

        $layout = HNS_LevelGenerator::generate($levelNumber <= 3 ? 5 : 7, $seed);
        $rng = new HNS_SeededRandom($seed + $levelNumber);
        $levelMonsterIds = self::selectMonstersForRoomSlots($rng->shuffle($monsterDeck), $monsterMaterial, $levelNumber);
        $slotPayloads = array_map(static function (int $monsterId) use ($monsterMaterial): array {
            return ['monster_id' => $monsterId, 'size' => ($monsterMaterial[$monsterId]['size'] ?? 'small') === 'big' ? 'large' : 'small'];
        }, $levelMonsterIds);
        $monsterSlots = HNS_RoomSlotPattern::assignLevelMonsterSlots($levelNumber, $slotPayloads);
        $entities = self::spawnMonsters($layout['monster_starts'], $monsterSlots, $monsterMaterial);

        $abilities = [];
        $enchantment = $enchantmentDeck[0] ?? null;
        if ($enchantment === 'shield') {
            $abilities[] = 'shield';
            foreach ($entities as &$entity) {
                $entity['has_shield'] = true;
                $entity['shield_broken'] = false;
            }
        }
        if ($enchantment === 'thorns') {
            $abilities[] = 'thorns';
        }

        return [
            'level' => $levelNumber,
            'is_boss_level' => false,
            'layout' => $layout,
            'tiles' => self::tilesFromLayout($layout),
            'entities' => $entities,
            'monster_slots' => $monsterSlots,
            'level_monster_abilities' => $abilities,
            'reward_offer' => [],
            'reward_upgrades' => [],
        ];
    }

    /**
     * @param array<int, int> $monsterDeck
     * @param array<int, array<string, mixed>> $monsterMaterial
     * @return array<int, int>
     */
    private static function selectMonstersForRoomSlots(array $monsterDeck, array $monsterMaterial, int $levelNumber): array
    {
        $selected = [];
        $smallCount = 0;
        $largeCount = 0;
        $smallCapacity = 4;
        $largeCapacity = 3;

        foreach ($monsterDeck as $monsterId) {
            $isLarge = ($monsterMaterial[$monsterId]['size'] ?? 'small') === 'big';
            if ($isLarge) {
                if ($largeCount >= $largeCapacity) {
                    continue;
                }
                $largeCount++;
            } else {
                if ($smallCount >= $smallCapacity) {
                    continue;
                }
                $smallCount++;
            }

            $selected[] = $monsterId;
            if (count($selected) === $levelNumber) {
                return $selected;
            }
        }

        throw new InvalidArgumentException('Not enough monsters to fill valid room slots.');
    }

    /**
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $monsterMaterial
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    public static function activateMonsters(array $state, array $monsterMaterial): array
    {
        $events = [];
        $activatedEntities = [];
        foreach (self::monsterActivationOrder($state['entities']) as $entityId) {
            if (($state['entities'][$entityId]['state'] ?? 'active') !== 'active') {
                continue;
            }

            if (isset($activatedEntities[$entityId])) {
                continue;
            }

            $monsterId = (int) ($state['entities'][$entityId]['type_arg'] ?? 0);
            if (!isset($monsterMaterial[$monsterId])) {
                continue;
            }

            $beforeTileId = (int) ($state['entities'][$entityId]['tile_id'] ?? 0);
            $stackEntityIds = self::stackEntityIds($state['entities'], $state['entities'][$entityId]);
            $result = HNS_MonsterAi::activate($entityId, $state, $monsterMaterial[$monsterId]);
            $state = $result['state'];
            $resultEvents = self::applyStackMovement($entityId, $beforeTileId, $stackEntityIds, $state, $result['events']);
            $state = $resultEvents['state'];
            array_push($events, ...$resultEvents['events']);
            foreach ($stackEntityIds as $stackEntityId) {
                $activatedEntities[$stackEntityId] = true;
            }
        }

        return ['state' => $state, 'events' => $events];
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     * @param array<string, mixed> $anchorEntity
     * @return array<int, int>
     */
    private static function stackEntityIds(array $entities, array $anchorEntity): array
    {
        $ids = [];
        foreach ($entities as $entityId => $entity) {
            if (($entity['state'] ?? 'active') !== 'active' || ($entity['type'] ?? null) !== 'monster') {
                continue;
            }
            if ((int) ($entity['tile_id'] ?? 0) === (int) ($anchorEntity['tile_id'] ?? 0)
                && (int) ($entity['type_arg'] ?? 0) === (int) ($anchorEntity['type_arg'] ?? 0)
                && ($entity['monster_size'] ?? 'small') === ($anchorEntity['monster_size'] ?? 'small')
            ) {
                $ids[] = (int) $entityId;
            }
        }
        sort($ids);
        return $ids;
    }

    /**
     * @param array<int, int> $stackEntityIds
     * @param array<string, mixed> $state
     * @param array<int, array<string, mixed>> $events
     * @return array{state: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    private static function applyStackMovement(int $anchorEntityId, int $beforeTileId, array $stackEntityIds, array $state, array $events): array
    {
        $afterTileId = (int) ($state['entities'][$anchorEntityId]['tile_id'] ?? $beforeTileId);
        if ($afterTileId === $beforeTileId) {
            return ['state' => $state, 'events' => $events];
        }

        foreach ($stackEntityIds as $stackEntityId) {
            if ($stackEntityId !== $anchorEntityId && ($state['entities'][$stackEntityId]['state'] ?? 'active') === 'active') {
                $state['entities'][$stackEntityId]['tile_id'] = $afterTileId;
            }
        }

        foreach ($events as &$event) {
            if (($event['type'] ?? null) === 'monsterMove' && (int) ($event['source_entity_id'] ?? 0) === $anchorEntityId) {
                if (count($stackEntityIds) > 1) {
                    $event['moved_entity_ids'] = $stackEntityIds;
                }
            }
        }

        return ['state' => $state, 'events' => $events];
    }

    /** @param array<string, mixed> $state */
    public static function isLevelCleared(array $state): bool
    {
        foreach ($state['entities'] as $entity) {
            if (in_array($entity['type'] ?? null, ['monster', 'boss'], true) && ($entity['state'] ?? 'active') === 'active') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $powers
     * @param array<int, string> $powerDeck
     * @return array<string, mixed>
     */
    public static function prepareLevelReward(array $state, array $powers, array $powerDeck): array
    {
        if (!self::isLevelCleared($state)) {
            return $state;
        }

        $state['reward_offer'] = HNS_LevelReward::drawOfferForPlayer($powers, $powerDeck, array_values($state['player_powers'] ?? []));
        $state['reward_upgrades'] = HNS_LevelReward::drawUpgradeOfferForPlayer($powers, array_values($state['player_powers'] ?? []));

        return $state;
    }

    /** @param array<int, array<string, mixed>> $entities */
    private static function monsterActivationOrder(array $entities): array
    {
        $ids = [];
        foreach ($entities as $entityId => $entity) {
            if (!in_array($entity['type'] ?? null, ['monster', 'boss'], true)) {
                continue;
            }
            $ids[] = (int) $entityId;
        }

        usort($ids, static function (int $leftId, int $rightId) use ($entities): int {
            return self::entityOrder($entities[$leftId]) <=> self::entityOrder($entities[$rightId]) ?: $leftId <=> $rightId;
        });

        return $ids;
    }

    /** @param array<string, mixed> $entity */
    private static function entityOrder(array $entity): int
    {
        if (($entity['type'] ?? null) === 'boss' || ($entity['monster_size'] ?? null) === 'boss') {
            return 3;
        }

        return ($entity['monster_size'] ?? 'small') === 'big' ? 2 : 1;
    }

    /**
     * @param array<int, array<string, mixed>> $monsterStarts
     * @param array<int, array<string, mixed>> $monsterSlots
     * @param array<int, array<string, mixed>> $monsterMaterial
     * @return array<int, array<string, mixed>>
     */
    private static function spawnMonsters(array $monsterStarts, array $monsterSlots, array $monsterMaterial): array
    {
        $entities = [];
        $entityId = 100;
        foreach ($monsterSlots as $slot => $slotPayload) {
            $monsterId = (int) $slotPayload['monster_id'];
            $material = $monsterMaterial[$monsterId];
            $spawnCount = (int) ($material['spawn_count'] ?? 1);
            for ($copy = 0; $copy < $spawnCount; $copy++) {
                $start = $monsterStarts[$slot - 1 + $copy] ?? $monsterStarts[$slot - 1];
                $entities[$entityId] = [
                    'id' => $entityId,
                    'type' => 'monster',
                    'type_arg' => $monsterId,
                    'monster_size' => $material['size'] ?? 'small',
                    'tile_id' => self::tileIdFor($start['x'], $start['y']),
                    'health' => (int) $material['health'],
                    'state' => 'active',
                    'on_death' => $material['on_death'] ?? null,
                    'damage' => $material['damage'] ?? 0,
                    'slot' => $slot,
                ];
                $entityId++;
            }
        }

        return $entities;
    }

    /** @param array<string, mixed> $layout */
    private static function tilesFromLayout(array $layout): array
    {
        $tiles = [];
        foreach ($layout['terrain'] as $terrain) {
            $id = self::tileIdFor((int) $terrain['x'], (int) $terrain['y']);
            $tiles[$id] = ['id' => $id, 'x' => (int) $terrain['x'], 'y' => (int) $terrain['y'], 'type' => $terrain['terrain']];
        }

        return $tiles;
    }

    private static function tileIdFor(int $x, int $y): int
    {
        return ($y * 100) + $x + 1;
    }
}
