<?php

trait HNS_Board
{
    protected function getTilesForLevel(int $level): array
    {
        return $this->getCollectionFromDb("SELECT tile_id id, tile_x x, tile_y y, tile_type type, tile_state state, tile_level level FROM tile WHERE tile_level = $level");
    }

    protected function getEntities(): array
    {
        return $this->getCollectionFromDb('SELECT entity_id id, entity_type type, entity_type_arg type_arg, entity_owner owner, entity_tile_id tile_id, entity_health health, entity_state state, entity_monster_size monster_size, entity_boss_key boss_key, entity_phase phase, entity_status status, entity_on_death on_death, entity_shield_broken shield_broken, entity_slot slot FROM entity');
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
            $shieldBroken = !empty($entity['shield_broken']) ? 1 : 0;

            if (isset($existingIds[$id])) {
                $this->DbQuery("UPDATE entity SET entity_tile_id = $tileId, entity_health = $health, entity_state = '$entityState', entity_status = $status, entity_shield_broken = $shieldBroken WHERE entity_id = $id");
                continue;
            }

            $type = $this->hns_sql_escape((string) $entity['type']);
            $typeArg = (int) ($entity['type_arg'] ?? 0);
            $owner = isset($entity['owner']) && $entity['owner'] !== null ? (int) $entity['owner'] : 'NULL';
            $size = $this->hns_sql_nullable_string($entity['monster_size'] ?? null);
            $onDeath = $this->hns_sql_nullable_string($entity['on_death'] ?? null);
            $slot = isset($entity['slot']) ? (int) $entity['slot'] : 'NULL';
            $bossKey = $this->hns_sql_nullable_string($entity['boss_key'] ?? null);
            $phase = (int) ($entity['phase'] ?? 0);
            $this->DbQuery("INSERT INTO entity (entity_id, entity_type, entity_type_arg, entity_owner, entity_tile_id, entity_health, entity_state, entity_monster_size, entity_boss_key, entity_phase, entity_status, entity_on_death, entity_shield_broken, entity_slot) VALUES ($id, '$type', $typeArg, $owner, $tileId, $health, '$entityState', $size, $bossKey, $phase, $status, $onDeath, $shieldBroken, $slot)");
        }
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

    protected function moveHeroesToCurrentLevelEntry(int $level): void
    {
        $entryTileId = (int) $this->getUniqueValueFromDB("SELECT tile_id FROM tile WHERE tile_type = 'entry' AND tile_level = $level ORDER BY tile_id LIMIT 1");
        $this->DbQuery("UPDATE entity SET entity_tile_id = $entryTileId WHERE entity_type = 'hero'");
    }
}
