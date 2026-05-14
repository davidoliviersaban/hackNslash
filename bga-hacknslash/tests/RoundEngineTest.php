<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/material/constants.inc.php';
require_once dirname(__DIR__) . '/modules/HNS_BoardRules.php';
require_once dirname(__DIR__) . '/modules/HNS_MonsterAi.php';
require_once dirname(__DIR__) . '/modules/HNS_SeededRandom.php';
require_once dirname(__DIR__) . '/modules/HNS_LevelGenerator.php';
require_once dirname(__DIR__) . '/modules/HNS_RoomSlotPattern.php';
require_once dirname(__DIR__) . '/modules/HNS_LevelReward.php';
require_once dirname(__DIR__) . '/modules/HNS_BossEngine.php';
require_once dirname(__DIR__) . '/modules/HNS_GameEngine.php';
require_once dirname(__DIR__) . '/modules/HNS_RoundEngine.php';

final class RoundEngineTest extends TestCase
{
    private array $monsters;
    private array $bosses;
    private array $powers;

    protected function setUp(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';
        $this->monsters = $monsters;
        $this->bosses = $bosses;
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

    public function testStartRoundRemovesDeadEnemiesButKeepsHeroes(): void
    {
        $state = [
            'players' => [1 => ['free_move_available' => false, 'main_action_available' => false, 'action_points' => 0]],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'health' => 0, 'state' => 'dead'],
                20 => ['id' => 20, 'type' => 'monster', 'health' => 0, 'state' => 'dead'],
                21 => ['id' => 21, 'type' => 'monster', 'health' => 1, 'state' => 'active'],
                30 => ['id' => 30, 'type' => 'boss', 'health' => 0, 'state' => 'dead'],
            ],
        ];

        $state = HNS_RoundEngine::startRound($state);

        $this->assertArrayHasKey(10, $state['entities']);
        $this->assertArrayNotHasKey(20, $state['entities']);
        $this->assertArrayHasKey(21, $state['entities']);
        $this->assertArrayNotHasKey(30, $state['entities']);
    }

    public function testStartRoundClearsSlimedStatusWhenNoActiveSlimeIsAdjacent(): void
    {
        $state = [
            'players' => [1 => ['free_move_available' => false, 'main_action_available' => false, 'action_points' => 0]],
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'owner' => 1, 'tile_id' => 1, 'status' => 'slimed'],
                20 => ['id' => 20, 'type' => 'monster', 'type_arg' => 2, 'tile_id' => 2, 'state' => 'dead'],
                21 => ['id' => 21, 'type' => 'monster', 'type_arg' => 2, 'tile_id' => 3, 'state' => 'active'],
            ],
        ];

        $state = HNS_RoundEngine::startRound($state);

        $this->assertNull($state['entities'][10]['status']);
    }

    public function testStartRoundKeepsSlimedStatusWhenActiveSlimeIsAdjacent(): void
    {
        $state = [
            'players' => [1 => ['free_move_available' => false, 'main_action_available' => false, 'action_points' => 0]],
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'owner' => 1, 'tile_id' => 1, 'status' => 'slimed'],
                20 => ['id' => 20, 'type' => 'monster', 'type_arg' => 2, 'tile_id' => 2, 'state' => 'active'],
            ],
        ];

        $state = HNS_RoundEngine::startRound($state);

        $this->assertSame('slimed', $state['entities'][10]['status']);
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

    public function testSlimedHeroCannotUseNormalMove(): void
    {
        $state = [
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
            ],
            'players' => [1 => ['free_move_available' => true, 'main_action_available' => true, 'action_points' => 2]],
            'entities' => [
                10 => ['type' => 'hero', 'owner' => 1, 'tile_id' => 1, 'status' => 'slimed'],
                20 => ['type' => 'monster', 'type_arg' => 2, 'tile_id' => 2, 'state' => 'active'],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slimed heroes cannot move except with Dash.');

        HNS_RoundEngine::consumeMove($state, 1);
    }

    public function testSlimedHeroCanMoveWhenNoSlimeIsAdjacent(): void
    {
        $state = [
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
            ],
            'players' => [1 => ['free_move_available' => true, 'main_action_available' => true, 'action_points' => 2]],
            'entities' => [
                10 => ['type' => 'hero', 'owner' => 1, 'tile_id' => 1, 'status' => 'slimed'],
                20 => ['type' => 'monster', 'type_arg' => 2, 'tile_id' => 3, 'state' => 'active'],
            ],
        ];

        $state = HNS_RoundEngine::consumeMove($state, 1);

        $this->assertNull($state['entities'][10]['status']);
        $this->assertFalse($state['players'][1]['free_move_available']);
    }

    public function testLegacyStuckHeroCannotMoveWhileSlimeIsAdjacent(): void
    {
        $state = [
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
            ],
            'players' => [1 => ['free_move_available' => true, 'main_action_available' => true, 'action_points' => 2]],
            'entities' => [
                10 => ['type' => 'hero', 'owner' => 1, 'tile_id' => 1, 'status' => 'slimed'],
                20 => ['type' => 'monster', 'type_arg' => 2, 'tile_id' => 2, 'state' => 'active'],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slimed heroes cannot move except with Dash.');

        HNS_RoundEngine::consumeMove($state, 1);
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
        $this->assertSame([['type' => 'trapDamage', 'target_entity_id' => 10, 'damage' => 1, 'target_health' => 9]], $result['events']);
    }

    public function testTrapStepDamagesMonstersOnSpikes(): void
    {
        $state = [
            'tiles' => [1 => ['id' => 1, 'type' => 'spikes']],
            'entities' => [20 => ['id' => 20, 'type' => 'monster', 'tile_id' => 1, 'health' => 2, 'state' => 'active']],
        ];

        $result = HNS_RoundEngine::activateTraps($state);

        $this->assertSame(1, $result['state']['entities'][20]['health']);
        $this->assertSame([['type' => 'trapDamage', 'target_entity_id' => 20, 'damage' => 1, 'target_health' => 1]], $result['events']);
    }

    public function testTrapStepBreaksMonsterShieldBeforeDamaging(): void
    {
        $state = [
            'tiles' => [1 => ['id' => 1, 'type' => 'spikes']],
            'entities' => [20 => ['id' => 20, 'type' => 'monster', 'tile_id' => 1, 'health' => 2, 'state' => 'active', 'has_shield' => true, 'shield_broken' => false]],
        ];

        $result = HNS_RoundEngine::activateTraps($state);

        $this->assertSame(2, $result['state']['entities'][20]['health']);
        $this->assertTrue($result['state']['entities'][20]['shield_broken']);
        $this->assertSame([['type' => 'shieldBroken', 'source_entity_id' => 20, 'damage_absorbed' => 1]], $result['events']);
    }

    public function testTrapStepCanKillMonstersOnSpikes(): void
    {
        $state = [
            'tiles' => [1 => ['id' => 1, 'type' => 'spikes']],
            'entities' => [20 => ['id' => 20, 'type' => 'monster', 'tile_id' => 1, 'health' => 1, 'state' => 'active']],
        ];

        $result = HNS_RoundEngine::activateTraps($state);

        $this->assertSame(0, $result['state']['entities'][20]['health']);
        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertSame([['type' => 'trapDamage', 'target_entity_id' => 20, 'damage' => 1, 'target_health' => 0]], $result['events']);
    }

    public function testTrapStepAdvancesBossPhaseInsteadOfLeavingBossDead(): void
    {
        $state = [
            'bosses' => $this->bosses,
            'tiles' => [1 => ['id' => 1, 'type' => 'spikes']],
            'entities' => [900 => ['id' => 900, 'type' => 'boss', 'boss_key' => 'slasher', 'phase' => 1, 'tile_id' => 1, 'health' => 1, 'state' => 'active']],
        ];

        $result = HNS_RoundEngine::activateTraps($state);

        $this->assertSame('active', $result['state']['entities'][900]['state']);
        $this->assertSame(2, $result['state']['entities'][900]['phase']);
        $this->assertSame(8, $result['state']['entities'][900]['health']);
        $this->assertSame(['trapDamage', 'bossPhaseDefeated', 'bossPhaseStarted'], array_column($result['events'], 'type'));
    }

    public function testTrapStepWinsGameWhenItKillsFinalBossPhase(): void
    {
        $state = [
            'bosses' => $this->bosses,
            'tiles' => [1 => ['id' => 1, 'type' => 'spikes']],
            'entities' => [900 => ['id' => 900, 'type' => 'boss', 'boss_key' => 'slasher', 'phase' => 3, 'tile_id' => 1, 'health' => 1, 'state' => 'active']],
        ];

        $result = HNS_RoundEngine::activateTraps($state);

        $this->assertSame('dead', $result['state']['entities'][900]['state']);
        $this->assertTrue($result['state']['game_won']);
        $this->assertSame(['trapDamage', 'bossPhaseDefeated', 'gameWon'], array_column($result['events'], 'type'));
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

        $result = HNS_RoundEngine::resolveLevelEnd($state, $this->monsters, array_keys($this->monsters), $this->powers, ['dash_1', 'vortex_1'], 456);

        $this->assertSame(2, $result['state']['level']);
        $this->assertSame('levelCleared', $result['events'][0]['type']);
        $this->assertArrayHasKey('reward_upgrades', $result['events'][0]);
        $this->assertSame('levelStarted', $result['events'][1]['type']);
        $this->assertTrue($result['state']['players'][1]['free_move_available']);
    }
}
