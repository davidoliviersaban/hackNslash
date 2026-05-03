<?php

trait HNS_Board
{
    protected function getTilesForLevel(int $level): array
    {
        $this->ensureTileSpawnLabelColumn();
        return $this->getCollectionFromDb("SELECT tile_id id, tile_x x, tile_y y, tile_type type, tile_state state, tile_level level, tile_spawn_label spawn_label FROM tile WHERE tile_level = $level");
    }

    protected function getEntities(): array
    {
        return $this->getEntitiesForLevel((int) $this->getGameStateValue('current_level'));
    }

    protected function getEntitiesForLevel(int $level): array
    {
        $this->ensureEntityRuntimeColumns();
        return $this->getCollectionFromDb("SELECT e.entity_id id, e.entity_type type, e.entity_type_arg type_arg, e.entity_owner owner, e.entity_tile_id tile_id, e.entity_health health, e.entity_state state, e.entity_monster_size monster_size, e.entity_boss_key boss_key, e.entity_phase phase, e.entity_status status, e.entity_on_death on_death, e.entity_has_shield has_shield, e.entity_shield_broken shield_broken, e.entity_slot slot FROM entity e JOIN tile t ON t.tile_id = e.entity_tile_id WHERE t.tile_level = $level");
    }

    protected function moveHeroToTile(int $playerId, int $tileId): void
    {
        $this->DbQuery("UPDATE entity SET entity_tile_id = $tileId WHERE entity_type = 'hero' AND entity_owner = $playerId");

        $tile = $this->getObjectFromDB("SELECT tile_x, tile_y FROM tile WHERE tile_id = $tileId");
        if ($tile) {
            $x = (int) $tile['tile_x'];
            $y = (int) $tile['tile_y'];
            $this->DbQuery("UPDATE player SET player_position_x = $x, player_position_y = $y WHERE player_id = $playerId");
        }
    }

    protected function getLevelMonsterAbilities(): array
    {
        $json = $this->getUniqueValueFromDB("SELECT var_value FROM global_var WHERE var_name = 'level_monster_abilities'");
        if (!is_string($json) || $json === '') {
            return [];
        }

        $abilities = json_decode($json, true);

        return is_array($abilities) ? $abilities : [];
    }

    protected function loadEngineState(): array
    {
        return [
            'level' => (int) $this->getGameStateValue('current_level'),
            'players' => $this->getPlayersWithState(),
            'player_powers' => $this->getPlayerPowers(),
            'tiles' => $this->getTilesForLevel((int) $this->getGameStateValue('current_level')),
            'entities' => $this->getEntities(),
            'bosses' => $this->bosses ?? [],
            'monster_material' => $this->monsters ?? [],
            'level_monster_abilities' => $this->getLevelMonsterAbilities(),
        ];
    }

    protected function persistEngineState(array $state): void
    {
        $this->ensureEntityRuntimeColumns();
        $entities = $state['entities'] ?? [];
        if (empty($entities)) {
            return;
        }

        $existingIds = $this->fetchExistingEntityIds(array_map(static fn ($e) => (int) $e['id'], $entities));

        foreach ($entities as $entity) {
            $id = (int) $entity['id'];
            if ($id <= 0) {
                // Skip placeholder entities never assigned a stable id.
                continue;
            }

            $tileId = (int) ($entity['tile_id'] ?? 0);
            $health = (int) ($entity['health'] ?? 0);
            $entityState = $this->hns_sql_escape((string) ($entity['state'] ?? 'active'));
            $status = $this->hns_sql_nullable_string($entity['status'] ?? null);
            $hasShield = !empty($entity['has_shield']) ? 1 : 0;
            $shieldBroken = !empty($entity['shield_broken']) ? 1 : 0;
            $phase = (int) ($entity['phase'] ?? 0);

            if (isset($existingIds[$id])) {
                $this->DbQuery("UPDATE entity SET entity_tile_id = $tileId, entity_health = $health, entity_state = '$entityState', entity_phase = $phase, entity_status = $status, entity_has_shield = $hasShield, entity_shield_broken = $shieldBroken WHERE entity_id = $id");
                $this->syncHeroPlayerHealth($entity, $health);
                continue;
            }

            $type = $this->hns_sql_escape((string) $entity['type']);
            $typeArg = (int) ($entity['type_arg'] ?? 0);
            $owner = isset($entity['owner']) && $entity['owner'] !== null ? (int) $entity['owner'] : 'NULL';
            $size = $this->hns_sql_nullable_string($entity['monster_size'] ?? null);
            $onDeath = $this->hns_sql_nullable_string($entity['on_death'] ?? null);
            $slot = isset($entity['slot']) ? (int) $entity['slot'] : 'NULL';
            $bossKey = $this->hns_sql_nullable_string($entity['boss_key'] ?? null);
            $this->DbQuery("INSERT INTO entity (entity_id, entity_type, entity_type_arg, entity_owner, entity_tile_id, entity_health, entity_state, entity_monster_size, entity_boss_key, entity_phase, entity_status, entity_on_death, entity_has_shield, entity_shield_broken, entity_slot) VALUES ($id, '$type', $typeArg, $owner, $tileId, $health, '$entityState', $size, $bossKey, $phase, $status, $onDeath, $hasShield, $shieldBroken, $slot)");
            $this->syncHeroPlayerHealth($entity, $health);
        }
    }

    /** @param array<string, mixed> $entity */
    private function syncHeroPlayerHealth(array $entity, int $health): void
    {
        if (($entity['type'] ?? null) !== 'hero' || !isset($entity['owner'])) {
            return;
        }

        $playerId = (int) $entity['owner'];
        $this->DbQuery("UPDATE player SET player_health = $health WHERE player_id = $playerId");
    }

    private function ensureEntityRuntimeColumns(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $hasShieldColumn = $this->getObjectFromDB("SHOW COLUMNS FROM entity LIKE 'entity_has_shield'");
        if (!$hasShieldColumn) {
            $this->DbQuery("ALTER TABLE entity ADD entity_has_shield TINYINT UNSIGNED NOT NULL DEFAULT '0' AFTER entity_on_death");
        }

        $done = true;
    }

    /**
     * @param list<int> $ids
     * @return array<int, true>
     */
    private function fetchExistingEntityIds(array $ids): array
    {
        $ids = array_filter(array_unique(array_map('intval', $ids)), static fn (int $id) => $id > 0);
        if (empty($ids)) {
            return [];
        }

        $rows = $this->getCollectionFromDb('SELECT entity_id, entity_id FROM entity WHERE entity_id IN (' . implode(',', $ids) . ')');
        $existing = [];
        foreach (array_keys($rows) as $id) {
            $existing[(int) $id] = true;
        }

        return $existing;
    }

    protected function moveHeroesToLevelStarts(int $level): void
    {
        $heroes = $this->getCollectionFromDb("SELECT entity_id id, entity_owner owner FROM entity WHERE entity_type = 'hero' ORDER BY entity_id");
        $startTileIds = $this->heroStartTileIdsForLevel($level, count($heroes));

        foreach (array_values($heroes) as $index => $hero) {
            $entityId = (int) $hero['id'];
            $playerId = (int) $hero['owner'];
            $tileId = $startTileIds[$index] ?? $startTileIds[0];
            $this->DbQuery("UPDATE entity SET entity_tile_id = $tileId WHERE entity_id = $entityId");
            $this->syncPlayerPositionFromTile($playerId, $tileId);
        }
    }

    protected function deleteMonstersOutsideLevel(int $level): void
    {
        $this->DbQuery("DELETE e FROM entity e JOIN tile t ON t.tile_id = e.entity_tile_id WHERE e.entity_type IN ('monster', 'boss') AND t.tile_level <> $level");
    }

    protected function deleteDeadEnemiesForCurrentLevel(): void
    {
        $level = (int) $this->getGameStateValue('current_level');
        $this->DbQuery("DELETE e FROM entity e JOIN tile t ON t.tile_id = e.entity_tile_id WHERE e.entity_type IN ('monster', 'boss') AND t.tile_level = $level AND (e.entity_state = 'dead' OR e.entity_health <= 0)");
    }

    protected function ensureTileSpawnLabelColumn(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $hasColumn = $this->getObjectFromDB("SHOW COLUMNS FROM tile LIKE 'tile_spawn_label'");
        if (!$hasColumn) {
            $this->DbQuery("ALTER TABLE tile ADD tile_spawn_label VARCHAR(8) DEFAULT NULL AFTER tile_level");
        }

        $done = true;
    }
}
