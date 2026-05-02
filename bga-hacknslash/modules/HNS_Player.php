<?php

trait HNS_Player
{
    protected function getPlayersWithState(): array
    {
        return $this->getCollectionFromDb('SELECT player_id id, player_name name, player_color color, player_score score, player_health health, player_action_points action_points, player_position_x x, player_position_y y, player_level level, player_free_move_available free_move_available, player_main_action_available main_action_available FROM player');
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

    protected function resetRoundFlags(): void
    {
        $round = (int) $this->getGameStateValue('round_number') + 1;
        $this->setGameStateValue('round_number', $round);
        $this->DbQuery('UPDATE player SET player_free_move_available = 1, player_main_action_available = 1, player_action_points = ' . HNS_DEFAULT_ACTION_POINTS);
    }

    protected function areAllHeroActionsSpent(): bool
    {
        return (int) $this->getUniqueValueFromDB('SELECT COUNT(*) FROM player WHERE player_free_move_available = 1 OR player_main_action_available = 1') === 0;
    }

    protected function getPlayerPowers(): array
    {
        return $this->getCollectionFromDb('SELECT player_power_id id, player_id, power_slot slot, power_key, power_cooldown cooldown FROM player_power');
    }
}
