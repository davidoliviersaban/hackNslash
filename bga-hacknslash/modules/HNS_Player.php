<?php

trait HNS_Player
{
    protected function getPlayersWithState(): array
    {
        return $this->getCollectionFromDb('SELECT player_id id, player_name name, player_color color, player_score score, player_health health, player_action_points action_points, player_position_x x, player_position_y y, player_level level FROM player');
    }

    protected function getActivePlayerActionPoints(): int
    {
        $playerId = (int) $this->getActivePlayerId();
        return (int) $this->getUniqueValueFromDB("SELECT player_action_points FROM player WHERE player_id = $playerId");
    }

    protected function resetActivePlayerTurn(): void
    {
        $playerId = (int) $this->getActivePlayerId();
        $this->DbQuery('UPDATE player SET player_action_points = ' . HNS_DEFAULT_ACTION_POINTS . " WHERE player_id = $playerId");
    }
}
