<?php

trait HNS_Setup
{
    private const HNS_STARTING_POWER_KEYS = ['strike', 'attack', 'attack'];
    private const HNS_BOSS_FIGHT_POWER_COUNT = 2;

    protected function setupStaticCards(): void
    {
        $cards = [];
        foreach (array_values(array_keys($this->bonus_cards)) as $index => $powerKey) {
            // BGA Deck stores `card_type_arg` as an int. We use the position in
            // the material table as the stable numeric reference and recover
            // the textual power_key via powerKeyForTypeArg().
            $cards[] = ['type' => 'bonus', 'type_arg' => $index + 1, 'nbr' => 1];
        }

        if (!empty($cards)) {
            $this->cards->createCards($cards, 'deck');
            $this->cards->shuffle('deck');
        }
    }

    /**
     * Recover the power_key string from the `card_type_arg` value persisted by
     * BGA's Deck component.
     */
    protected function powerKeyForTypeArg(int $typeArg): ?string
    {
        $keys = array_values(array_keys($this->bonus_cards));
        $index = $typeArg - 1;
        if ($index < 0 || $index >= count($keys)) {
            return null;
        }

        return (string) $keys[$index];
    }

    protected function setupInitialBoard(int $level): void
    {
        $seed = random_int(1, PHP_INT_MAX);
        $levelState = HNS_GameEngine::createLevel($level, $seed, $this->monsters, array_keys($this->monsters), $this->drawLevelEnchantments($level));

        $abilities = $this->hns_sql_escape((string) json_encode($levelState['level_monster_abilities']));
        $this->DbQuery("REPLACE INTO global_var (var_name, var_value) VALUES ('level_seed', '$seed')");
        $this->DbQuery("REPLACE INTO global_var (var_name, var_value) VALUES ('level_monster_abilities', '$abilities')");
        $this->ensureTileSpawnLabelColumn();
        $spawnLabels = $this->spawnLabelsByCoordinate($levelState['layout']['monster_starts'] ?? []);

        foreach ($levelState['layout']['terrain'] as $tile) {
            $x = (int) $tile['x'];
            $y = (int) $tile['y'];
            $type = $this->hns_sql_escape((string) $tile['terrain']);
            $spawnLabel = $this->hns_sql_nullable_string($spawnLabels["$x,$y"] ?? null);
            $this->DbQuery("INSERT INTO tile (tile_x, tile_y, tile_type, tile_state, tile_level, tile_spawn_label) VALUES ($x, $y, '$type', 'revealed', $level, $spawnLabel)");
        }

        // Pre-load the (x, y) -> tile_id map for this level instead of querying
        // it once per entity (avoids an N+1 pattern during board setup).
        $coordsToTileId = $this->loadCoordsToTileIdMap($level);

        foreach ($levelState['entities'] as $entity) {
            $type = (string) ($entity['type'] ?? 'monster');
            $monsterId = (int) ($entity['type_arg'] ?? 0);
            $health = (int) $entity['health'];
            $size = $this->hns_sql_escape((string) ($entity['monster_size'] ?? 'small'));
            $onDeath = $this->hns_sql_nullable_string($entity['on_death'] ?? null);
            $hasShield = !empty($entity['has_shield']) ? 1 : 0;
            $shieldBroken = !empty($entity['shield_broken']) ? 1 : 0;
            $slot = (int) ($entity['slot'] ?? 0);
            $tileX = (int) ($levelState['tiles'][$entity['tile_id']]['x'] ?? 0);
            $tileY = (int) ($levelState['tiles'][$entity['tile_id']]['y'] ?? 0);
            $tileId = $coordsToTileId["$tileX,$tileY"] ?? $this->tileIdForCoords($tileX, $tileY, $level);
            if ($type === 'boss') {
                $bossKey = $this->hns_sql_nullable_string($entity['boss_key'] ?? null);
                $phase = (int) ($entity['phase'] ?? 1);
                $this->DbQuery("INSERT INTO entity (entity_type, entity_type_arg, entity_tile_id, entity_health, entity_monster_size, entity_boss_key, entity_phase, entity_has_shield, entity_shield_broken) VALUES ('boss', $monsterId, $tileId, $health, '$size', $bossKey, $phase, $hasShield, $shieldBroken)");
                continue;
            }

            $this->DbQuery("INSERT INTO entity (entity_type, entity_type_arg, entity_tile_id, entity_health, entity_monster_size, entity_on_death, entity_has_shield, entity_shield_broken, entity_slot) VALUES ('monster', $monsterId, $tileId, $health, '$size', $onDeath, $hasShield, $shieldBroken, $slot)");
        }
    }

    /**
     * @return array<string, int> map keyed by "x,y" -> tile_id for the given level.
     */
    private function loadCoordsToTileIdMap(int $level): array
    {
        $rows = $this->getCollectionFromDb("SELECT tile_id, tile_x, tile_y FROM tile WHERE tile_level = $level");
        $map = [];
        foreach ($rows as $row) {
            $map[((int) $row['tile_x']) . ',' . ((int) $row['tile_y'])] = (int) $row['tile_id'];
        }

        return $map;
    }

    /** @return array<int, string> */
    protected function drawLevelEnchantments(int $level): array
    {
        if ($level <= HNS_FIRST_LEVEL || $level >= HNS_BOSS_LEVEL) {
            return [];
        }

        $cycle = ['shield', 'thorns', null];
        $enchantment = $cycle[($level - HNS_FIRST_LEVEL - 1) % count($cycle)];

        return $enchantment === null ? [] : [$enchantment];
    }

    protected function tileIdForCoords(int $x, int $y, int $level): int
    {
        return (int) $this->getUniqueValueFromDB("SELECT tile_id FROM tile WHERE tile_x = $x AND tile_y = $y AND tile_level = $level");
    }

    protected function initializePlayers(array $playerIds, int $level = HNS_FIRST_LEVEL, int $health = HNS_DEFAULT_HEALTH, ?array $powerKeys = null): void
    {
        $startTileIds = $this->heroStartTileIdsForLevel($level, count($playerIds));
        $actionPoints = count($playerIds) <= 1 ? HNS_SOLO_ACTION_POINTS : HNS_MULTIPLAYER_ACTION_POINTS;
        $powerKeys = $powerKeys ?? self::HNS_STARTING_POWER_KEYS;

        foreach (array_values($playerIds) as $index => $playerId) {
            $playerId = (int) $playerId;
            $tileId = $startTileIds[$index] ?? $startTileIds[0];
            $this->DbQuery("UPDATE player SET player_health = $health, player_action_points = $actionPoints, player_main_action_available = 1 WHERE player_id = $playerId");
            $this->DbQuery("INSERT INTO entity (entity_type, entity_owner, entity_tile_id, entity_health) VALUES ('hero', $playerId, $tileId, $health)");
            $this->syncPlayerPositionFromTile($playerId, $tileId);
            $this->initializePlayerPowers($playerId, $powerKeys);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $monsterStarts
     * @return array<string, string>
     */
    private function spawnLabelsByCoordinate(array $monsterStarts): array
    {
        $labels = [];
        foreach ($monsterStarts as $start) {
            if (!isset($start['x'], $start['y'], $start['label'])) {
                continue;
            }

            $labels[((int) $start['x']) . ',' . ((int) $start['y'])] = (string) $start['label'];
        }

        return $labels;
    }

    /** @return list<int> */
    protected function heroStartTileIdsForLevel(int $level, int $playerCount): array
    {
        $entry = $this->getObjectFromDB("SELECT tile_x x, tile_y y FROM tile WHERE tile_type = 'entry' AND tile_level = $level ORDER BY tile_id LIMIT 1");
        if (!$entry) {
            return [(int) $this->getUniqueValueFromDB("SELECT tile_id FROM tile WHERE tile_level = $level ORDER BY tile_id LIMIT 1")];
        }

        $maxY = (int) $this->getUniqueValueFromDB("SELECT MAX(tile_y) FROM tile WHERE tile_level = $level");
        $anchorX = (int) $entry['x'];
        $anchorY = (int) $entry['y'] === 0 ? 1 : $maxY - 1;

        $coords = $playerCount <= 1
            ? [[$anchorX, $anchorY]]
            : [[$anchorX - 1, $anchorY], [$anchorX + 1, $anchorY]];

        $tileIds = [];
        foreach ($coords as [$x, $y]) {
            $tileId = $this->walkableTileIdForCoords($x, $y, $level);
            if ($tileId !== null) {
                $tileIds[] = $tileId;
            }
        }

        if ($tileIds === []) {
            $tileIds[] = $this->walkableTileIdForCoords($anchorX, $anchorY, $level) ?? (int) $this->getUniqueValueFromDB("SELECT tile_id FROM tile WHERE tile_type = 'entry' AND tile_level = $level ORDER BY tile_id LIMIT 1");
        }

        return $tileIds;
    }

    private function walkableTileIdForCoords(int $x, int $y, int $level): ?int
    {
        $tileId = $this->getUniqueValueFromDB("SELECT tile_id FROM tile WHERE tile_x = $x AND tile_y = $y AND tile_level = $level AND tile_type IN ('floor', 'spikes') LIMIT 1");

        return $tileId === null ? null : (int) $tileId;
    }

    private function syncPlayerPositionFromTile(int $playerId, int $tileId): void
    {
        $tile = $this->getObjectFromDB("SELECT tile_x, tile_y FROM tile WHERE tile_id = $tileId");
        if (!$tile) {
            return;
        }

        $x = (int) $tile['tile_x'];
        $y = (int) $tile['tile_y'];
        $this->DbQuery("UPDATE player SET player_position_x = $x, player_position_y = $y WHERE player_id = $playerId");
    }

    protected function bossFightStartingPowers(): array
    {
        $powerKeys = array_values(array_filter(array_keys($this->bonus_cards), function (string $powerKey): bool {
            return (int) ($this->bonus_cards[$powerKey]['rank'] ?? 0) === 3;
        }));
        shuffle($powerKeys);

        return array_slice($powerKeys, 0, self::HNS_BOSS_FIGHT_POWER_COUNT);
    }

    protected function initializePlayerPowers(int $playerId, ?array $powerKeys = null): void
    {
        foreach (($powerKeys ?? self::HNS_STARTING_POWER_KEYS) as $slot => $powerKey) {
            $powerSlot = $slot + 1;
            $this->DbQuery("INSERT INTO player_power (player_id, power_slot, power_key, power_cooldown) VALUES ($playerId, $powerSlot, '$powerKey', 0)");
        }
    }
}
