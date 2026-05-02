<?php

trait HNS_Setup
{
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
        $tiles = [
            [0, 0, 'entry'],
            [1, 0, 'floor'],
            [2, 0, 'floor'],
            [3, 0, 'exit'],
        ];

        foreach ($tiles as [$x, $y, $type]) {
            $this->DbQuery("INSERT INTO tile (tile_x, tile_y, tile_type, tile_state, tile_level) VALUES ($x, $y, '$type', 'revealed', $level)");
        }
    }

    protected function initializePlayers(array $playerIds): void
    {
        $entryTileId = (int) $this->getUniqueValueFromDB("SELECT tile_id FROM tile WHERE tile_type = 'entry' ORDER BY tile_id LIMIT 1");

        foreach ($playerIds as $playerId) {
            $playerId = (int) $playerId;
            $this->DbQuery('UPDATE player SET player_health = ' . HNS_DEFAULT_HEALTH . ', player_action_points = ' . HNS_DEFAULT_ACTION_POINTS . " WHERE player_id = $playerId");
            $this->DbQuery("INSERT INTO entity (entity_type, entity_owner, entity_tile_id, entity_health) VALUES ('hero', $playerId, $entryTileId, " . HNS_DEFAULT_HEALTH . ")");
        }
    }
}
