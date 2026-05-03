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

    public function testSoloStartsWithTwoMainActionPoints(): void
    {
        $state = ['players' => [1 => ['free_move_available' => false, 'main_action_available' => false, 'action_points' => 0]]];

        $state = HNS_RoundEngine::startRound($state);

        $this->assertSame(2, $state['players'][1]['action_points']);
        $this->assertTrue($state['players'][1]['main_action_available']);
    }

    public function testMultiplayerStartsWithOneMainActionPointPerPlayer(): void
    {
        $state = [
            'players' => [
                1 => ['free_move_available' => false, 'main_action_available' => false, 'action_points' => 0],
                2 => ['free_move_available' => false, 'main_action_available' => false, 'action_points' => 0],
            ],
        ];

        $state = HNS_RoundEngine::startRound($state);

        $this->assertSame(1, $state['players'][1]['action_points']);
        $this->assertSame(1, $state['players'][2]['action_points']);
    }

    public function testSoloMainActionConsumesOnlyOneActionPoint(): void
    {
        $state = ['players' => [1 => ['free_move_available' => true, 'main_action_available' => true, 'action_points' => 2]]];

        $state = HNS_RoundEngine::consumeMainAction($state, 1);

        $this->assertSame(1, $state['players'][1]['action_points']);
        $this->assertFalse($state['players'][1]['free_move_available']);
        $this->assertTrue($state['players'][1]['main_action_available']);
        $this->assertFalse(HNS_RoundEngine::isHeroPhaseComplete($state));
    }

    public function testFreeMainActionDoesNotConsumeActionPoint(): void
    {
        $state = ['players' => [1 => ['free_move_available' => true, 'main_action_available' => true, 'action_points' => 1]]];

        $state = HNS_RoundEngine::consumeMainAction($state, 1, true);

        $this->assertSame(1, $state['players'][1]['action_points']);
        $this->assertFalse($state['players'][1]['free_move_available']);
        $this->assertTrue($state['players'][1]['main_action_available']);
    }

    public function testPaidMoveConsumesActionPointAfterFreeMoveIsClosed(): void
    {
        $state = ['players' => [1 => ['free_move_available' => false, 'main_action_available' => true, 'action_points' => 2]]];

        $state = HNS_RoundEngine::consumeMove($state, 1);

        $this->assertFalse($state['players'][1]['free_move_available']);
        $this->assertSame(1, $state['players'][1]['action_points']);
        $this->assertTrue($state['players'][1]['main_action_available']);
    }

    public function testFreeMoveDoesNotConsumeActionPointBeforeAnyAction(): void
    {
        $state = ['players' => [1 => ['free_move_available' => true, 'main_action_available' => true, 'action_points' => 2]]];

        $state = HNS_RoundEngine::consumeMove($state, 1);

        $this->assertFalse($state['players'][1]['free_move_available']);
        $this->assertSame(2, $state['players'][1]['action_points']);
        $this->assertTrue($state['players'][1]['main_action_available']);
    }

    public function testDbIntegerFreeMoveFlagIsConsumedAsFreeMove(): void
    {
        $state = ['players' => [1 => ['free_move_available' => '1', 'main_action_available' => '0', 'action_points' => 0]]];

        $state = HNS_RoundEngine::consumeMove($state, 1);

        $this->assertFalse($state['players'][1]['free_move_available']);
        $this->assertSame(0, $state['players'][1]['action_points']);
        $this->assertFalse($state['players'][1]['main_action_available']);
    }

    public function testHeroPhaseCompletesWhenAllActionsAreSpent(): void
    {
        $state = ['players' => [1 => ['free_move_available' => false, 'main_action_available' => false], 2 => ['free_move_available' => false, 'main_action_available' => false]]];

        $this->assertTrue(HNS_RoundEngine::isHeroPhaseComplete($state));
    }

    public function testSkippingFreeMoveLeavesMainActionAvailable(): void
    {
        $state = ['players' => [1 => ['free_move_available' => true, 'main_action_available' => true]]];

        $state = HNS_RoundEngine::consumeFreeMove($state, 1);

        $this->assertFalse($state['players'][1]['free_move_available']);
        $this->assertTrue($state['players'][1]['main_action_available']);
        $this->assertFalse(HNS_RoundEngine::isHeroPhaseComplete($state));
    }

    public function testSkippingMainActionClosesFreeMove(): void
    {
        $state = ['players' => [1 => ['free_move_available' => true, 'main_action_available' => true, 'action_points' => 1]]];

        $state = HNS_RoundEngine::consumeMainAction($state, 1);

        $this->assertFalse($state['players'][1]['free_move_available']);
        $this->assertFalse($state['players'][1]['main_action_available']);
        $this->assertTrue(HNS_RoundEngine::isHeroPhaseComplete($state));
    }

    public function testConsumingBothOptionalActionsCompletesHeroPhase(): void
    {
        $state = ['players' => [1 => ['free_move_available' => true, 'main_action_available' => true]]];

        $state = HNS_RoundEngine::consumeFreeMove($state, 1);
        $state = HNS_RoundEngine::consumeMainAction($state, 1);

        $this->assertTrue(HNS_RoundEngine::isHeroPhaseComplete($state));
    }

    public function testEndingSoloTurnConsumesAllHeroActionsAndCompletesHeroPhase(): void
    {
        $state = ['players' => [1 => ['free_move_available' => true, 'main_action_available' => true]]];

        $state = HNS_RoundEngine::endPlayerTurn($state, 1);

        $this->assertFalse($state['players'][1]['free_move_available']);
        $this->assertFalse($state['players'][1]['main_action_available']);
        $this->assertTrue(HNS_RoundEngine::isHeroPhaseComplete($state));
    }

    public function testSkippingInactivePartnerConsumesTheirActionsAndCompletesHeroPhase(): void
    {
        $state = [
            'players' => [
                1 => ['free_move_available' => false, 'main_action_available' => false],
                2 => ['free_move_available' => true, 'main_action_available' => true],
            ],
        ];

        $state = HNS_RoundEngine::endPlayerTurn($state, 2);

        $this->assertFalse($state['players'][2]['free_move_available']);
        $this->assertFalse($state['players'][2]['main_action_available']);
        $this->assertTrue(HNS_RoundEngine::isHeroPhaseComplete($state));
    }

    public function testFindsNextPlayerWithActionsRemaining(): void
    {
        $state = [
            'players' => [
                1 => ['free_move_available' => false, 'main_action_available' => false],
                2 => ['free_move_available' => true, 'main_action_available' => false],
                3 => ['free_move_available' => false, 'main_action_available' => true],
            ],
        ];

        $this->assertSame(2, HNS_RoundEngine::nextPlayerWithActions($state, 1));
        $this->assertSame(3, HNS_RoundEngine::nextPlayerWithActions($state, 2));
        $this->assertSame(2, HNS_RoundEngine::nextPlayerWithActions($state, 3));
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
        $this->assertArrayHasKey('reward_upgrades', $result['events'][0]);
        $this->assertSame('levelStarted', $result['events'][1]['type']);
        $this->assertTrue($result['state']['players'][1]['free_move_available']);
    }
}
