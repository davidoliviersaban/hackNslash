<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_BoardRules.php';
require_once dirname(__DIR__) . '/modules/HNS_FreeActionEngine.php';
require_once dirname(__DIR__) . '/modules/HNS_MonsterAi.php';
require_once dirname(__DIR__) . '/modules/HNS_BossEngine.php';
require_once dirname(__DIR__) . '/modules/HNS_PowerResolver.php';

final class PowerResolverTest extends TestCase
{
    private array $powers;
    private array $state;

    protected function setUp(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->powers = $bonus_cards;
        $this->state = [
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
                4 => ['id' => 4, 'x' => 1, 'y' => 1, 'type' => 'floor'],
                5 => ['id' => 5, 'x' => 2, 'y' => 2, 'type' => 'floor'],
                6 => ['id' => 6, 'x' => 0, 'y' => 2, 'type' => 'floor'],
                7 => ['id' => 7, 'x' => 2, 'y' => 1, 'type' => 'floor'],
                8 => ['id' => 8, 'x' => 0, 'y' => 1, 'type' => 'floor'],
                9 => ['id' => 9, 'x' => 0, 'y' => 2, 'type' => 'wall'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'owner' => 1, 'tile_id' => 1, 'health' => 10, 'state' => 'active'],
                20 => ['id' => 20, 'type' => 'monster', 'tile_id' => 2, 'health' => 2, 'state' => 'active'],
                21 => ['id' => 21, 'type' => 'monster', 'tile_id' => 3, 'health' => 1, 'state' => 'active'],
                22 => ['id' => 22, 'type' => 'monster', 'tile_id' => 4, 'health' => 3, 'state' => 'active'],
                23 => ['id' => 23, 'type' => 'monster', 'tile_id' => 7, 'health' => 3, 'state' => 'active'],
            ],
        ];
    }

    public function testAttackDamagesMonsterInRangeAndEmitsCardPlayedEvent(): void
    {
        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 20], $this->state, $this->powers);

        $this->assertSame(1, $result['state']['entities'][20]['health']);
        $this->assertSame('active', $result['state']['entities'][20]['state']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'attack'],
        ], $result['events']);
    }

    public function testAttackKillsMonsterAndEmitsKillEvent(): void
    {
        $state = $this->state;
        $state['entities'][20]['health'] = 1;

        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(0, $result['state']['entities'][20]['health']);
        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'attack'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 20],
        ], $result['events']);
    }

    public function testAttackCanHitAdjacentDiagonalTarget(): void
    {
        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 22], $this->state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][22]['health']);
    }

    public function testThornsDamageHeroWhenAttackingOrthogonallyAdjacentMonster(): void
    {
        $state = $this->state;
        $state['level_monster_abilities'] = ['thorns'];

        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'attack'],
            ['type' => 'thornsDamage', 'source_entity_id' => 20, 'target_entity_id' => 10, 'damage' => 1],
        ], $result['events']);
    }

    public function testThornsDoNotDamageHeroWhenAttackingDiagonally(): void
    {
        $state = $this->state;
        $state['level_monster_abilities'] = ['thorns'];

        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 22], $state, $this->powers);

        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'attack'],
        ], $result['events']);
    }

    public function testThornsDoNotDamageHeroWhenAttackingAtRangeTwo(): void
    {
        $state = $this->state;
        $state['level_monster_abilities'] = ['thorns'];

        $result = HNS_PowerResolver::resolve('vortex', 10, ['selected_tile_id' => 2, 'target_entity_ids' => [21]], $state, $this->powers);

        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'vortex'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL, 'source_entity_id' => 10, 'target_entity_ids' => [21]],
        ], $result['events']);
    }

    public function testShieldAbsorbsFirstDamageRegardlessOfAmount(): void
    {
        $state = $this->state;
        $state['level_monster_abilities'] = ['shield'];

        $result = HNS_PowerResolver::resolve('strike', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][20]['health']);
        $this->assertTrue($result['state']['entities'][20]['shield_broken']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'strike'],
            ['type' => 'shieldBroken', 'source_entity_id' => 20, 'damage_absorbed' => 2],
        ], $result['events']);
    }

    public function testBrokenShieldDoesNotAbsorbLaterDamage(): void
    {
        $state = $this->state;
        $state['level_monster_abilities'] = ['shield'];
        $state['entities'][20]['shield_broken'] = true;

        $result = HNS_PowerResolver::resolve('strike', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(0, $result['state']['entities'][20]['health']);
        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'strike'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 20],
        ], $result['events']);
    }

    public function testStrikeDamagesOrthogonalTargetForTwoDamage(): void
    {
        $result = HNS_PowerResolver::resolve('strike', 10, ['target_entity_id' => 20], $this->state, $this->powers);

        $this->assertSame(0, $result['state']['entities'][20]['health']);
        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'strike'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 20],
        ], $result['events']);
    }

    public function testStrikeCannotHitAdjacentDiagonalTarget(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Target is out of range for strike.');

        HNS_PowerResolver::resolve('strike', 10, ['target_entity_id' => 22], $this->state, $this->powers);
    }

    public function testDashMovesHeroAndEmitsDashAndCardPlayedEvents(): void
    {
        $state = $this->state;
        unset($state['entities'][22]);

        $result = HNS_PowerResolver::resolve('dash_1', 10, ['target_tile_id' => 6], $state, $this->powers);

        $this->assertSame(6, $result['state']['entities'][10]['tile_id']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'dash_1'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_DASH, 'source_entity_id' => 10, 'target_tile_id' => 6],
        ], $result['events']);
    }

    public function testDashCannotMoveDiagonally(): void
    {
        $state = $this->state;
        unset($state['entities'][22]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dash target is out of range.');

        HNS_PowerResolver::resolve('dash_1', 10, ['target_tile_id' => 4], $state, $this->powers);
    }

    public function testDashDamagesWeakestBlockingMonsterAndDoesNotMoveWhenTileRemainsBlocked(): void
    {
        $state = $this->state;
        unset($state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][20]['health'] = 2;
        $state['entities'][24] = ['id' => 24, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'health' => 3, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('dash_1', 10, ['target_tile_id' => 2], $state, $this->powers);

        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame(1, $result['state']['entities'][20]['health']);
        $this->assertSame(1, $result['state']['entities'][10]['tile_id']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'dash_1'],
        ], $result['events']);
    }

    public function testDashKillsBlockingMonsterAndMovesIntoFreedTile(): void
    {
        $state = $this->state;
        unset($state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][20]['health'] = 1;

        $result = HNS_PowerResolver::resolve('dash_1', 10, ['target_tile_id' => 2], $state, $this->powers);

        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertSame(2, $result['state']['entities'][10]['tile_id']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'dash_1'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 20],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_DASH, 'source_entity_id' => 10, 'target_tile_id' => 2],
        ], $result['events']);
    }

    public function testDashCannotTargetObstacle(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dash target is not available.');

        HNS_PowerResolver::resolve('dash_1', 10, ['target_tile_id' => 9], $state, $this->powers);
    }

    public function testVortexPullsTargetMonsterOneStepTowardSelectedTileAndEmitsPullEvent(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][22]);

        $result = HNS_PowerResolver::resolve(
            'vortex',
            10,
            ['selected_tile_id' => 2, 'target_entity_ids' => [21, 23]],
            $state,
            $this->powers
        );

        $this->assertSame(2, $result['state']['entities'][21]['tile_id']);
        $this->assertSame(2, $result['state']['entities'][23]['tile_id']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'vortex'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL, 'source_entity_id' => 10, 'target_entity_ids' => [21, 23]],
        ], $result['events']);
    }

    public function testVortexCanSelectTileAtDiagonalRangeTwo(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][23]);

        $result = HNS_PowerResolver::resolve(
            'vortex',
            10,
            ['selected_tile_id' => 5, 'target_entity_ids' => [22]],
            $state,
            $this->powers
        );

        $this->assertSame(5, $result['state']['entities'][22]['tile_id']);
    }

    public function testVortexRequiresTargetsAdjacentToSelectedTile(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][22], $state['entities'][23]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pull target is out of range from selected tile.');

        HNS_PowerResolver::resolve(
            'vortex',
            10,
            ['selected_tile_id' => 6, 'target_entity_ids' => [21]],
            $state,
            $this->powers
        );
    }

    public function testVortexResolvesSmallMonstersBeforeBigMonsters(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'big', 'tile_id' => 2, 'health' => 3, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 8, 'health' => 2, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve(
            'vortex',
            10,
            ['selected_tile_id' => 4, 'target_entity_ids' => [30, 31]],
            $state,
            $this->powers
        );

        $this->assertSame(4, $result['state']['entities'][31]['tile_id']);
        $this->assertSame(1, $result['state']['entities'][31]['health']);
        $this->assertSame(2, $result['state']['entities'][30]['health']);
        $this->assertSame(2, $result['state']['entities'][30]['tile_id']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'vortex'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL, 'source_entity_id' => 10, 'target_entity_ids' => [31, 30]],
        ], $result['events']);
    }

    public function testKamikazeExplodesWhenKilled(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][20] = ['id' => 20, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active', 'on_death' => 'explode', 'damage' => 2];

        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertSame(8, $result['state']['entities'][10]['health']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'attack'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 20],
            ['type' => 'monsterExplode', 'source_entity_id' => 20, 'target_entity_ids' => [10], 'damage' => 2],
        ], $result['events']);
    }

    public function testBossAtZeroHealthInterruptsAndStartsNextPhase(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';
        $bosses['slasher']['phases'][2] = ['health' => 9];
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['bosses'] = $bosses;
        $state['entities'][30] = ['id' => 30, 'type' => 'boss', 'boss_key' => 'slasher', 'phase' => 1, 'monster_size' => 'boss', 'tile_id' => 2, 'health' => 1, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 30], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][30]['phase']);
        $this->assertSame(9, $result['state']['entities'][30]['health']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'attack'],
            ['type' => 'bossPhaseDefeated', 'source_entity_id' => 30, 'boss_key' => 'slasher', 'phase' => 1],
            ['type' => 'monsterMove', 'source_entity_id' => 30, 'target_tile_id' => 4],
            ['type' => 'monsterMove', 'source_entity_id' => 30, 'target_tile_id' => 2],
            ['type' => 'monsterAttack', 'source_entity_id' => 30, 'target_entity_id' => 10, 'damage' => 2],
            ['type' => 'bossPhaseStarted', 'source_entity_id' => 30, 'boss_key' => 'slasher', 'phase' => 2],
        ], $result['events']);
    }

    public function testThirdBossPhaseDefeatWinsGame(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['bosses'] = $bosses;
        $state['entities'][30] = ['id' => 30, 'type' => 'boss', 'boss_key' => 'slasher', 'phase' => 3, 'monster_size' => 'boss', 'tile_id' => 2, 'health' => 1, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 30], $state, $this->powers);

        $this->assertTrue($result['state']['game_won']);
        $this->assertSame('dead', $result['state']['entities'][30]['state']);
        $this->assertSame('gameWon', $result['events'][2]['type']);
    }
}
