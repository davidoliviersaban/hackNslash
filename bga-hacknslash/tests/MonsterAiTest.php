<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_BoardRules.php';
require_once dirname(__DIR__) . '/modules/HNS_MonsterAi.php';

final class MonsterAiTest extends TestCase
{
    private array $state;
    private array $monsters;

    protected function setUp(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';
        $this->monsters = $monsters;
        $this->state = [
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
                4 => ['id' => 4, 'x' => 3, 'y' => 0, 'type' => 'floor'],
                5 => ['id' => 5, 'x' => 0, 'y' => 1, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 1, 'health' => 10, 'state' => 'active'],
                11 => ['id' => 11, 'type' => 'hero', 'tile_id' => 5, 'health' => 10, 'state' => 'active'],
                20 => ['id' => 20, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 3, 'health' => 2, 'state' => 'active'],
            ],
        ];
    }

    public function testMonsterAttacksIfHeroIsInRange(): void
    {
        $state = $this->state;
        $state['entities'][20]['tile_id'] = 2;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[1]);

        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame(2, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterAttack', 'source_entity_id' => 20, 'target_entity_id' => 10, 'damage' => 1, 'target_health' => 9]], $result['events']);
    }

    public function testMonsterMovesTowardClosestHeroWhenItCannotAttack(): void
    {
        $result = HNS_MonsterAi::activate(20, $this->state, $this->monsters[1]);

        $this->assertSame(2, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 2]], $result['events']);
    }

    public function testGoblinDoesNotAttackDiagonally(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 1, 'y' => 1, 'type' => 'floor'];
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 6;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[1]);

        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertSame([['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 2]], $result['events']);
    }

    public function testGoblinDoesNotAttackOnItsOwnTile(): void
    {
        $state = $this->state;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 1;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[1]);

        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertSame([], $result['events']);
    }

    public function testMonsterThatCannotMoveOnlyAttacksIfAlreadyInRange(): void
    {
        $state = $this->state;
        // Use Evil Eye (range 3 chebyshev, cannot move) but place monster far enough to be out of range.
        $state['tiles'][6] = ['id' => 6, 'x' => 5, 'y' => 0, 'type' => 'floor'];
        $state['entities'][20]['tile_id'] = 6;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[3]);

        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertSame(6, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([], $result['events']);
    }

    public function testMonsterThatCanMoveAndAttackDoesBothInActivation(): void
    {
        $monster = $this->monsters[1];
        $monster['can_attack_and_move'] = true;

        $result = HNS_MonsterAi::activate(20, $this->state, $monster);

        $this->assertSame(2, $result['state']['entities'][20]['tile_id']);
        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame([
            ['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 2],
            ['type' => 'monsterAttack', 'source_entity_id' => 20, 'target_entity_id' => 10, 'damage' => 1, 'target_health' => 9],
        ], $result['events']);
    }

    public function testSlimeSlimesOrthogonallyAdjacentHeroWithoutDamage(): void
    {
        $state = $this->state;
        $state['entities'][20]['tile_id'] = 2;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[2]);

        $this->assertSame('slimed', $result['state']['entities'][10]['status']);
        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertSame([['type' => 'monsterSlime', 'source_entity_id' => 20, 'target_entity_id' => 10]], $result['events']);
    }

    public function testSlimeMovesDiagonallyUntilItCanStickHero(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 1, 'y' => 1, 'type' => 'floor'];
        $state['tiles'][7] = ['id' => 7, 'x' => 2, 'y' => 2, 'type' => 'floor'];
        $state['tiles'][8] = ['id' => 8, 'x' => 3, 'y' => 2, 'type' => 'floor'];
        $state['entities'][10]['tile_id'] = 8;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 1;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[2]);

        $this->assertSame(7, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([
            ['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 7],
            ['type' => 'monsterSlime', 'source_entity_id' => 20, 'target_entity_id' => 10],
        ], $result['events']);
    }

    public function testSlimeSticksAfterMovingOrthogonallyAdjacentToHero(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 3, 'y' => 1, 'type' => 'floor'];
        $state['entities'][10]['tile_id'] = 4;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 1;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[2]);

        $this->assertSame('slimed', $result['state']['entities'][10]['status']);
        $this->assertSame([
            ['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 3],
            ['type' => 'monsterSlime', 'source_entity_id' => 20, 'target_entity_id' => 10],
        ], $result['events']);
    }

    public function testSlimeUsesOnlyOneOfTwoMovePointsWhenThatReachesStickRange(): void
    {
        $state = $this->state;
        $state['entities'][10]['tile_id'] = 4;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 2;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[2]);

        $this->assertSame(3, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([
            ['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 3],
            ['type' => 'monsterSlime', 'source_entity_id' => 20, 'target_entity_id' => 10],
        ], $result['events']);
    }

    public function testMonsterPathfindsAroundBlockedDirectRoute(): void
    {
        $state = [
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'pillar'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
                4 => ['id' => 4, 'x' => 0, 'y' => 1, 'type' => 'floor'],
                5 => ['id' => 5, 'x' => 1, 'y' => 1, 'type' => 'floor'],
                6 => ['id' => 6, 'x' => 2, 'y' => 1, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 3, 'health' => 10, 'state' => 'active'],
                20 => ['id' => 20, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 1, 'health' => 2, 'state' => 'active'],
            ],
        ];

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[1]);

        $this->assertSame(4, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 4]], $result['events']);
    }

    public function testEvilEyeDoesNotMoveAndShootsAtChebyshevRangeThree(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 5, 'y' => 5, 'type' => 'floor'];
        $state['tiles'][7] = ['id' => 7, 'x' => 2, 'y' => 2, 'type' => 'floor'];
        $state['entities'][10]['tile_id'] = 6;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 7;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[3]);

        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame(7, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterAttack', 'source_entity_id' => 20, 'target_entity_id' => 10, 'damage' => 1, 'target_health' => 9]], $result['events']);
    }

    public function testKamikazeExplodesWhenOrthogonalToHero(): void
    {
        $state = $this->state;
        $state['entities'][20]['tile_id'] = 2;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[4]);

        $this->assertSame(8, $result['state']['entities'][10]['health']);
        $this->assertSame(0, $result['state']['entities'][20]['health']);
        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertSame([['type' => 'monsterExplode', 'source_entity_id' => 20, 'target_entity_ids' => [10], 'target_health_by_entity_id' => [10 => 8], 'damage' => 2]], $result['events']);
    }

    public function testKamikazeMovesDiagonallyOneTileWhenNotOrthogonalToHero(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 1, 'y' => 1, 'type' => 'floor'];
        $state['tiles'][7] = ['id' => 7, 'x' => 2, 'y' => 2, 'type' => 'floor'];
        $state['entities'][10]['tile_id'] = 7;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 1;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[4]);

        $this->assertSame(6, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 6]], $result['events']);
    }

    public function testWizardAttacksAtChebyshevRangeFour(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 4, 'y' => 4, 'type' => 'floor'];
        $state['tiles'][7] = ['id' => 7, 'x' => 1, 'y' => 1, 'type' => 'floor'];
        $state['entities'][10]['tile_id'] = 6;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 7;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[5]);

        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame([['type' => 'monsterAttack', 'source_entity_id' => 20, 'target_entity_id' => 10, 'damage' => 1, 'target_health' => 9]], $result['events']);
    }

    public function testWizardDoesNotAttackAtRangeZero(): void
    {
        $state = $this->state;
        $state['entities'][10]['tile_id'] = 3;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 3;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[5]);

        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertSame(2, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 2]], $result['events']);
    }

    public function testWizardDoesNotAttackAtMeleeRange(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 2, 'y' => 1, 'type' => 'floor'];
        $state['entities'][20]['tile_id'] = 2;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[5]);

        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertSame(3, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 3]], $result['events']);
    }

    public function testWizardStaysInRangeAndAttacksInsteadOfMovingCloser(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 4, 'y' => 0, 'type' => 'floor'];
        $state['entities'][10]['tile_id'] = 1;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 6;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[5]);

        $this->assertSame(6, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterAttack', 'source_entity_id' => 20, 'target_entity_id' => 10, 'damage' => 1, 'target_health' => 9]], $result['events']);
    }

    public function testWizardCannotAttackThroughObstacle(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 4, 'y' => 0, 'type' => 'floor'];
        $state['tiles'][3]['type'] = 'pillar';
        $state['entities'][10]['tile_id'] = 6;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 2;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[5]);

        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertNotContains('monsterAttack', array_column($result['events'], 'type'));
    }

    public function testWizardMovesOrthogonallyOneTileWhenItCannotAttack(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 10, 'y' => 0, 'type' => 'floor'];
        $state['tiles'][7] = ['id' => 7, 'x' => 4, 'y' => 0, 'type' => 'floor'];
        $state['entities'][10]['tile_id'] = 6;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 4;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[5]);

        $this->assertSame(7, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 7]], $result['events']);
    }

    public function testBomberAttacksAtChebyshevRangeThree(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 3, 'y' => 3, 'type' => 'floor'];
        $state['entities'][10]['tile_id'] = 6;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 1;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[6]);

        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame([['type' => 'monsterAttack', 'source_entity_id' => 20, 'target_entity_id' => 10, 'damage' => 1, 'target_health' => 9]], $result['events']);
    }

    public function testBomberDoesNotAttackAtMeleeRange(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 2, 'y' => 1, 'type' => 'floor'];
        $state['entities'][20]['tile_id'] = 2;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[6]);

        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertSame(3, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 3]], $result['events']);
    }

    public function testOrcHitsThreeFrontTilesForTwoDamage(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 1, 'y' => -1, 'type' => 'floor'];
        $state['tiles'][7] = ['id' => 7, 'x' => 1, 'y' => 1, 'type' => 'floor'];
        $state['entities'][20]['tile_id'] = 1;
        $state['entities'][10]['tile_id'] = 2;
        $state['entities'][11]['tile_id'] = 7;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[7]);

        $this->assertSame(8, $result['state']['entities'][10]['health']);
        $this->assertSame(8, $result['state']['entities'][11]['health']);
        $this->assertSame([['type' => 'monsterFrontArc', 'source_entity_id' => 20, 'target_entity_ids' => [10, 11], 'damage' => 2]], $result['events']);
    }

    public function testOrcMovesOrthogonallyOneTileWhenNoHeroInFrontArc(): void
    {
        $state = $this->state;
        $state['entities'][10]['tile_id'] = 4;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 1;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[7]);

        $this->assertSame(2, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 2]], $result['events']);
    }

    public function testPigRiderChargesPushesHeroAndTakesTheirTile(): void
    {
        $state = $this->state;
        $state['entities'][20]['tile_id'] = 1;
        $state['entities'][10]['tile_id'] = 2;
        $state['entities'][11]['state'] = 'dead';

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[8]);

        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame(3, $result['state']['entities'][10]['tile_id']);
        $this->assertSame(2, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterCharge', 'source_entity_id' => 20, 'target_entity_id' => 10, 'damage' => 1, 'push_tile_id' => 3]], $result['events']);
    }

    public function testPigRiderChargeCollisionDamagesBothWhenHeroCannotBePushed(): void
    {
        $state = $this->state;
        $state['entities'][20]['tile_id'] = 2;
        $state['entities'][10]['tile_id'] = 1;
        $state['entities'][11]['state'] = 'dead';

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[8]);

        $this->assertSame(8, $result['state']['entities'][10]['health']);
        $this->assertSame(1, $result['state']['entities'][10]['tile_id']);
        $this->assertSame(1, $result['state']['entities'][20]['health']);
        $this->assertSame(2, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([['type' => 'monsterCharge', 'source_entity_id' => 20, 'target_entity_id' => 10, 'damage' => 1, 'push_tile_id' => null]], $result['events']);
    }

    public function testWolfRiderSummonsGoblinThenMovesAwayFromHero(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 3, 'y' => 1, 'type' => 'floor'];
        $state['entities'][10]['tile_id'] = 1;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20]['tile_id'] = 3;

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[9]);

        $this->assertSame(21, $result['state']['entities'][21]['id']);
        $this->assertSame(1, $result['state']['entities'][21]['type_arg']);
        $this->assertSame(4, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([
            ['type' => 'monsterSummon', 'source_entity_id' => 20, 'summoned_entity_id' => 21, 'monster_id' => 1, 'target_tile_id' => 2, 'summoned_entity' => $result['state']['entities'][21]],
            ['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 4],
        ], $result['events']);
    }

    public function testWolfRiderDoesNotFleeIfNoAdjacentTileIncreasesDistance(): void
    {
        $state = $this->state;
        $state['entities'][10]['tile_id'] = 2;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20] = ['id' => 20, 'type' => 'monster', 'type_arg' => 9, 'monster_size' => 'big', 'tile_id' => 1, 'health' => 3, 'state' => 'active'];

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[9]);

        $this->assertSame(1, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([
            ['type' => 'monsterSummon', 'source_entity_id' => 20, 'summoned_entity_id' => 21, 'monster_id' => 1, 'target_tile_id' => 5, 'summoned_entity' => $result['state']['entities'][21]],
        ], $result['events']);
    }

    public function testWolfRiderDoesNotFleeOntoOccupiedTile(): void
    {
        $state = $this->state;
        $state['tiles'][6] = ['id' => 6, 'x' => 2, 'y' => 1, 'type' => 'floor'];
        $state['entities'][10]['tile_id'] = 1;
        $state['entities'][11]['state'] = 'dead';
        $state['entities'][20] = ['id' => 20, 'type' => 'monster', 'type_arg' => 9, 'monster_size' => 'big', 'tile_id' => 3, 'health' => 3, 'state' => 'active'];
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 4, 'health' => 1, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 4, 'health' => 1, 'state' => 'active'];

        $result = HNS_MonsterAi::activate(20, $state, $this->monsters[9]);

        $this->assertSame(6, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([
            ['type' => 'monsterSummon', 'source_entity_id' => 20, 'summoned_entity_id' => 32, 'monster_id' => 1, 'target_tile_id' => 2, 'summoned_entity' => $result['state']['entities'][32]],
            ['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 6],
        ], $result['events']);
    }
}
