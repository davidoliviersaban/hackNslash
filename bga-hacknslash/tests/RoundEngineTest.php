<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/material/constants.inc.php';
require_once dirname(__DIR__) . '/modules/HNS_BoardRules.php';
require_once dirname(__DIR__) . '/modules/HNS_MonsterAi.php';
require_once dirname(__DIR__) . '/modules/HNS_LevelGenerator.php';
require_once dirname(__DIR__) . '/modules/HNS_RoomSlotPattern.php';
require_once dirname(__DIR__) . '/modules/HNS_LevelReward.php';
require_once dirname(__DIR__) . '/modules/HNS_GameEngine.php';
require_once dirname(__DIR__) . '/modules/HNS_RoundEngine.php';

final class RoundEngineTest extends TestCase
{
    private array $monsters;
    private array $powers;

    protected function setUp(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';
        $this->monsters = $monsters;
        $this->powers = $bonus_cards;
    }

    public function testStartRoundResetsHeroActionFlags(): void
    {
        $state = ['players' => [1 => ['free_move_available' => false, 'main_action_available' => false]]];

        $state = HNS_RoundEngine::startRound($state);

        $this->assertTrue($state['players'][1]['free_move_available']);
        $this->assertTrue($state['players'][1]['main_action_available']);
    }

    public function testHeroPhaseCompletesWhenAllActionsAreSpent(): void
    {
        $state = ['players' => [1 => ['free_move_available' => false, 'main_action_available' => false], 2 => ['free_move_available' => false, 'main_action_available' => false]]];

        $this->assertTrue(HNS_RoundEngine::isHeroPhaseComplete($state));
    }

    public function testCooldownStepTicksPowersDown(): void
    {
        $state = ['player_powers' => [1 => ['cooldown' => 2], 2 => ['cooldown' => 0]]];

        $state = HNS_RoundEngine::cooldownStep($state);

        $this->assertSame(1, $state['player_powers'][1]['cooldown']);
        $this->assertSame(0, $state['player_powers'][2]['cooldown']);
    }

    public function testTrapStepDamagesHeroesOnSpikes(): void
    {
        $state = [
            'tiles' => [1 => ['id' => 1, 'type' => 'spikes']],
            'entities' => [10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 1, 'health' => 10, 'state' => 'active']],
        ];

        $result = HNS_RoundEngine::activateTraps($state);

        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame([['type' => 'trapDamage', 'target_entity_id' => 10, 'damage' => 1]], $result['events']);
    }

    public function testGameIsLostWhenAnyHeroHasZeroHealth(): void
    {
        $state = ['entities' => [10 => ['type' => 'hero', 'health' => 0, 'state' => 'dead']]];

        $this->assertTrue(HNS_RoundEngine::isGameLost($state));
    }

    public function testEnemyPhaseStopsWithGameLostAfterTrapKillsHero(): void
    {
        $state = [
            'tiles' => [1 => ['id' => 1, 'type' => 'spikes']],
            'entities' => [10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 1, 'health' => 1, 'state' => 'active']],
        ];

        $result = HNS_RoundEngine::completeEnemyPhase($state, $this->monsters);

        $this->assertSame('dead', $result['state']['entities'][10]['state']);
        $this->assertSame('gameLost', $result['events'][1]['type']);
    }

    public function testResolveLevelEndCreatesRewardAndStartsNextLevel(): void
    {
        $state = ['level' => 1, 'players' => [1 => []], 'player_powers' => [], 'entities' => [20 => ['type' => 'monster', 'state' => 'dead']]];

        $result = HNS_RoundEngine::resolveLevelEnd($state, $this->monsters, array_keys($this->monsters), $this->powers, ['dash_1', 'vortex'], 456);

        $this->assertSame(2, $result['state']['level']);
        $this->assertSame('levelCleared', $result['events'][0]['type']);
        $this->assertSame('levelStarted', $result['events'][1]['type']);
        $this->assertTrue($result['state']['players'][1]['free_move_available']);
    }
}
