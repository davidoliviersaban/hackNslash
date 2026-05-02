<?php

trait HNS_Board
{
    protected function getTilesForLevel(int $level): array
    {
        return $this->getCollectionFromDb("SELECT tile_id id, tile_x x, tile_y y, tile_type type, tile_state state, tile_level level FROM tile WHERE tile_level = $level");
    }

    protected function getEntities(): array
    {
        return $this->getCollectionFromDb('SELECT entity_id id, entity_type type, entity_type_arg type_arg, entity_owner owner, entity_tile_id tile_id, entity_health health, entity_state state FROM entity');
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
}
