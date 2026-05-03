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
        $this->clearFreeActionChain();
        $this->deleteDeadEnemiesForCurrentLevel();
        $actionPoints = $this->mainActionPointsPerPlayer();
        $mainActionAvailable = $actionPoints > 0 ? 1 : 0;
        $this->DbQuery("UPDATE player SET player_free_move_available = 1, player_main_action_available = $mainActionAvailable, player_action_points = $actionPoints");
        $this->notifyAllPlayers('roundStarted', '', [
            'players' => $this->getPlayersWithState(),
            'entities' => $this->getEntities(),
            'free_action_events' => [],
        ]);
    }

    protected function areAllHeroActionsSpent(): bool
    {
        return (int) $this->getUniqueValueFromDB('SELECT COUNT(*) FROM player WHERE player_free_move_available = 1 OR player_main_action_available = 1') === 0;
    }

    protected function isActivePlayerTurnSpent(): bool
    {
        $playerId = (int) $this->getActivePlayerId();
        return (int) $this->getUniqueValueFromDB("SELECT COUNT(*) FROM player WHERE player_id = $playerId AND (player_free_move_available = 1 OR player_main_action_available = 1)") === 0;
    }

    protected function isActivePlayerFreeMoveAvailable(): bool
    {
        $playerId = (int) $this->getActivePlayerId();
        return (int) $this->getUniqueValueFromDB("SELECT player_free_move_available FROM player WHERE player_id = $playerId") === 1;
    }

    protected function isActivePlayerMainActionAvailable(): bool
    {
        $playerId = (int) $this->getActivePlayerId();
        return (int) $this->getUniqueValueFromDB("SELECT player_action_points FROM player WHERE player_id = $playerId") > 0;
    }

    protected function isActivePlayerMoveAvailable(): bool
    {
        $playerId = (int) $this->getActivePlayerId();
        return (int) $this->getUniqueValueFromDB("SELECT COUNT(*) FROM player WHERE player_id = $playerId AND (player_free_move_available = 1 OR player_action_points > 0)") === 1;
    }

    protected function mainActionPointsPerPlayer(): int
    {
        $playerCount = (int) $this->getUniqueValueFromDB('SELECT COUNT(*) FROM player');
        return $playerCount <= 1 ? HNS_SOLO_ACTION_POINTS : HNS_MULTIPLAYER_ACTION_POINTS;
    }

    protected function getPlayerPowers(): array
    {
        return $this->getCollectionFromDb('SELECT player_power_id id, player_id, power_slot slot, power_key, power_cooldown cooldown FROM player_power');
    }
}
