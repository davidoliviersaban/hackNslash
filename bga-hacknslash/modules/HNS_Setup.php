<?php

trait HNS_Setup
{
    private const HNS_STARTING_POWER_KEYS = ['strike', 'attack', 'attack'];

    protected function setupStaticCards(): void
    {
        $cards = [];
        foreach ($this->bonus_cards as $id => $card) {
            $cards[] = ['type' => 'bonus', 'type_arg' => $id, 'nbr' => 1];
        }

        if (!empty($cards)) {
            $this->cards->createCards($cards, 'deck');
            $this->cards->shuffle('deck');
        }
    }

    protected function setupInitialBoard(int $level): void
    {
        $seed = random_int(1, PHP_INT_MAX);
        $levelState = HNS_GameEngine::createLevel($level, $seed, $this->monsters, array_keys($this->monsters), $this->drawLevelEnchantments());

        $this->DbQuery("REPLACE INTO global_var (var_name, var_value) VALUES ('level_seed', '$seed')");
        $this->DbQuery("REPLACE INTO global_var (var_name, var_value) VALUES ('level_monster_abilities', '" . addslashes(json_encode($levelState['level_monster_abilities'])) . "')");

        foreach ($levelState['layout']['terrain'] as $tile) {
            $x = (int) $tile['x'];
            $y = (int) $tile['y'];
            $type = addslashes($tile['terrain']);
            $this->DbQuery("INSERT INTO tile (tile_x, tile_y, tile_type, tile_state, tile_level) VALUES ($x, $y, '$type', 'revealed', $level)");
        }

        foreach ($levelState['entities'] as $entity) {
            $monsterId = (int) $entity['type_arg'];
            $health = (int) $entity['health'];
            $size = addslashes((string) ($entity['monster_size'] ?? 'small'));
            $onDeath = $entity['on_death'] === null ? 'NULL' : "'" . addslashes((string) $entity['on_death']) . "'";
            $slot = (int) ($entity['slot'] ?? 0);
            $tileId = $this->tileIdForCoords((int) ($levelState['tiles'][$entity['tile_id']]['x'] ?? 0), (int) ($levelState['tiles'][$entity['tile_id']]['y'] ?? 0), $level);
            $this->DbQuery("INSERT INTO entity (entity_type, entity_type_arg, entity_tile_id, entity_health, entity_monster_size, entity_on_death, entity_slot) VALUES ('monster', $monsterId, $tileId, $health, '$size', $onDeath, $slot)");
        }
    }

    /** @return array<int, string> */
    protected function drawLevelEnchantments(): array
    {
        $enchantments = ['shield', 'thorns', null];
        $draw = $enchantments[array_rand($enchantments)];

        return $draw === null ? [] : [$draw];
    }

    protected function tileIdForCoords(int $x, int $y, int $level): int
    {
        return (int) $this->getUniqueValueFromDB("SELECT tile_id FROM tile WHERE tile_x = $x AND tile_y = $y AND tile_level = $level");
    }

    protected function initializePlayers(array $playerIds): void
    {
        $entryTileId = (int) $this->getUniqueValueFromDB("SELECT tile_id FROM tile WHERE tile_type = 'entry' ORDER BY tile_id LIMIT 1");

        foreach ($playerIds as $playerId) {
            $playerId = (int) $playerId;
            $this->DbQuery('UPDATE player SET player_health = ' . HNS_DEFAULT_HEALTH . ', player_action_points = ' . HNS_DEFAULT_ACTION_POINTS . " WHERE player_id = $playerId");
            $this->DbQuery("INSERT INTO entity (entity_type, entity_owner, entity_tile_id, entity_health) VALUES ('hero', $playerId, $entryTileId, " . HNS_DEFAULT_HEALTH . ")");
            $this->initializePlayerPowers($playerId);
        }
    }

    protected function initializePlayerPowers(int $playerId): void
    {
        foreach (self::HNS_STARTING_POWER_KEYS as $slot => $powerKey) {
            $powerSlot = $slot + 1;
            $this->DbQuery("INSERT INTO player_power (player_id, power_slot, power_key, power_cooldown) VALUES ($playerId, $powerSlot, '$powerKey', 0)");
        }
    }
}
