<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_BoardRules.php';
require_once dirname(__DIR__) . '/modules/HNS_FreeActionEngine.php';
require_once dirname(__DIR__) . '/modules/HNS_SeededRandom.php';
require_once dirname(__DIR__) . '/modules/HNS_MonsterAi.php';
require_once dirname(__DIR__) . '/modules/HNS_BossEngine.php';
require_once dirname(__DIR__) . '/modules/HNS_GameEngine.php';
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
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 20, 'damage' => 1, 'target_health' => 1],
        ], $result['events']);
    }

    public function testStrikeCanTargetMonsterByTileIdFromBoardClick(): void
    {
        $result = HNS_PowerResolver::resolve('strike', 10, ['target_tile_id' => 2, 'selected_tile_id' => 2], $this->state, $this->powers);

        $this->assertSame(0, $result['state']['entities'][20]['health']);
        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'strike'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 20],
        ], $result['events']);
    }

    public function testStrikeOverkillDoesNotSpillOverStackedGoblinsOnSameTile(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('strike', 10, ['target_tile_id' => 2, 'selected_tile_id' => 2], $state, $this->powers);

        $this->assertSame('dead', $result['state']['entities'][30]['state']);
        $this->assertSame('active', $result['state']['entities'][31]['state']);
        $this->assertSame(1, $result['state']['entities'][31]['health']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'strike'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 30],
        ], $result['events']);
    }

    public function testAnyAttackOverkillDoesNotSpillOverStackedMonsters(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active'];
        $powers = $this->powers;
        $powers['attack']['damage'] = 2;

        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 30], $state, $powers);

        $this->assertSame('dead', $result['state']['entities'][30]['state']);
        $this->assertSame('active', $result['state']['entities'][31]['state']);
        $this->assertSame(1, $result['state']['entities'][31]['health']);
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

    public function testAttackCannotTargetAlliedHero(): void
    {
        $state = $this->state;
        $state['entities'][11] = ['id' => 11, 'type' => 'hero', 'owner' => 2, 'tile_id' => 2, 'health' => 10, 'state' => 'active'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot target an allied hero with attack.');

        HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 11], $state, $this->powers);
    }

    public function testAttackCanHitAdjacentDiagonalTarget(): void
    {
        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 22], $this->state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][22]['health']);
    }

    public function testQuickStrikeHitsTwoAdjacentTargetsIncludingDiagonal(): void
    {
        $result = HNS_PowerResolver::resolve('quick_strike_1', 10, ['target_entity_ids' => [20, 22]], $this->state, $this->powers);

        $this->assertSame(1, $result['state']['entities'][20]['health']);
        $this->assertSame(2, $result['state']['entities'][22]['health']);
    }

    public function testQuickStrikeCanHitTheSameTargetMultipleTimes(): void
    {
        $result = HNS_PowerResolver::resolve('quick_strike_2', 10, ['target_entity_ids' => [20, 20, 20]], $this->state, $this->powers);

        $this->assertSame(0, $result['state']['entities'][20]['health']);
        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'quick_strike_2'],
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 20, 'damage' => 1, 'target_health' => 1],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 20],
        ], $result['events']);
    }

    public function testQuickStrikeCannotHitMoreTargetsThanRankAllows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Too many targets for quick_strike_1.');

        HNS_PowerResolver::resolve('quick_strike_1', 10, ['target_entity_ids' => [20, 22, 23]], $this->state, $this->powers);
    }

    public function testQuickShotRankTwoHitsThreeTargetsAtChebyshevRange(): void
    {
        $state = $this->state;
        unset($state['entities'][20]);
        $state['entities'][24] = ['id' => 24, 'type' => 'monster', 'tile_id' => 5, 'health' => 2, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('quick_shot_2', 10, ['target_entity_ids' => [21, 23, 24]], $state, $this->powers);

        $this->assertSame('dead', $result['state']['entities'][21]['state']);
        $this->assertSame(2, $result['state']['entities'][23]['health']);
        $this->assertSame(1, $result['state']['entities'][24]['health']);
    }

    public function testQuickShotCanHitTheSameTargetMultipleTimes(): void
    {
        $state = $this->state;
        $state['entities'][23]['health'] = 3;

        $result = HNS_PowerResolver::resolve('quick_shot_2', 10, ['target_entity_ids' => [23, 23, 23]], $state, $this->powers);

        $this->assertSame(0, $result['state']['entities'][23]['health']);
        $this->assertSame('dead', $result['state']['entities'][23]['state']);
    }

    public function testQuickShotDoesNotIgnoreShield(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][22]);
        $state['entities'][21]['has_shield'] = true;
        $state['entities'][21]['shield_broken'] = false;
        $state['entities'][21]['health'] = 2;

        $result = HNS_PowerResolver::resolve('quick_shot_1', 10, ['target_entity_id' => 21], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][21]['health']);
        $this->assertTrue($result['state']['entities'][21]['shield_broken']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'quick_shot_1'],
            ['type' => 'shieldBroken', 'source_entity_id' => 21, 'damage_absorbed' => 1],
        ], $result['events']);
    }

    public function testQuickShotCannotHitAdjacentTarget(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Target is out of range for quick_shot_1.');

        HNS_PowerResolver::resolve('quick_shot_1', 10, ['target_entity_id' => 20], $this->state, $this->powers);
    }

    public function testRangedAttackCannotHitThroughObstacle(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][22], $state['entities'][23]);
        $state['tiles'][2]['type'] = 'pillar';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Target is not in line of sight for quick_shot_1.');

        HNS_PowerResolver::resolve('quick_shot_1', 10, ['target_entity_id' => 21], $state, $this->powers);
    }

    public function testPowerStrikeRankTwoDamagesAndPushesOrthogonalTarget(): void
    {
        $state = $this->state;
        $state['entities'][20]['health'] = 5;
        unset($state['entities'][21], $state['entities'][22], $state['entities'][23]);

        $result = HNS_PowerResolver::resolve('power_strike_2', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][20]['health']);
        $this->assertSame(3, $result['state']['entities'][20]['tile_id']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'power_strike_2'],
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 20, 'damage' => 3, 'target_health' => 2],
            ['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 3],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL, 'source_entity_id' => 10, 'target_entity_ids' => [20]],
        ], $result['events']);
    }

    public function testReflexShotRankThreeHitsAtChebyshevRangeTwoAndPushesTwoTiles(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['tiles'][10] = ['id' => 10, 'x' => 3, 'y' => 3, 'type' => 'floor'];
        $state['tiles'][11] = ['id' => 11, 'x' => 4, 'y' => 4, 'type' => 'floor'];
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'tile_id' => 5, 'health' => 5, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('point_blank_3', 10, ['target_entity_id' => 30], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][30]['health']);
        $this->assertSame(11, $result['state']['entities'][30]['tile_id']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'point_blank_3'],
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 30, 'damage' => 3, 'target_health' => 2],
            ['type' => 'monsterMove', 'source_entity_id' => 30, 'target_tile_id' => 10],
            ['type' => 'monsterMove', 'source_entity_id' => 30, 'target_tile_id' => 11],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL, 'source_entity_id' => 10, 'target_entity_ids' => [30]],
        ], $result['events']);
    }

    public function testReflexShotCannotHitAtChebyshevRangeThree(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'tile_id' => 10, 'health' => 5, 'state' => 'active'];
        $state['tiles'][10] = ['id' => 10, 'x' => 3, 'y' => 3, 'type' => 'floor'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Target is out of range for point_blank_1.');

        HNS_PowerResolver::resolve('point_blank_1', 10, ['target_entity_id' => 30], $state, $this->powers);
    }

    public function testThornsDamageHeroWhenAttackingOrthogonallyAdjacentMonster(): void
    {
        $state = $this->state;
        $state['level_monster_abilities'] = ['thorns'];

        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'attack'],
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 20, 'damage' => 1, 'target_health' => 1],
            ['type' => 'entityDamaged', 'source_entity_id' => 20, 'target_entity_id' => 10, 'damage' => 1, 'target_health' => 9],
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
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 22, 'damage' => 1, 'target_health' => 2],
        ], $result['events']);
    }

    public function testThornsDoNotDamageHeroWhenAttackingAtRangeTwo(): void
    {
        $state = $this->state;
        $state['level_monster_abilities'] = ['thorns'];

        $result = HNS_PowerResolver::resolve('vortex_1', 10, ['selected_tile_id' => 2, 'target_entity_ids' => [21]], $state, $this->powers);

        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'vortex_1'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 21],
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 20, 'damage' => 1, 'target_health' => 1],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL, 'source_entity_id' => 10, 'target_entity_ids' => [21]],
        ], $result['events']);
    }

    public function testGlobalShieldAbilityDoesNotShieldEveryMonster(): void
    {
        $state = $this->state;
        $state['level_monster_abilities'] = ['shield'];

        $result = HNS_PowerResolver::resolve('strike', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(0, $result['state']['entities'][20]['health']);
        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'strike'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 20],
        ], $result['events']);
    }

    public function testShieldedMonsterAbsorbsFirstDamageRegardlessOfAmount(): void
    {
        $state = $this->state;
        $state['entities'][20]['has_shield'] = true;

        $result = HNS_PowerResolver::resolve('strike', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][20]['health']);
        $this->assertTrue($result['state']['entities'][20]['shield_broken']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'strike'],
            ['type' => 'shieldBroken', 'source_entity_id' => 20, 'damage_absorbed' => 2],
        ], $result['events']);
    }

    public function testShieldedMonsterAbsorbsEveryHitFromTheFirstAttack(): void
    {
        $state = $this->state;
        $state['entities'][20]['health'] = 5;
        $state['entities'][20]['has_shield'] = true;

        $result = HNS_PowerResolver::resolve('quick_strike_2', 10, ['target_entity_ids' => [20, 20, 20]], $state, $this->powers);

        $this->assertSame(5, $result['state']['entities'][20]['health']);
        $this->assertSame('active', $result['state']['entities'][20]['state']);
        $this->assertTrue($result['state']['entities'][20]['shield_broken']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'quick_strike_2'],
            ['type' => 'shieldBroken', 'source_entity_id' => 20, 'damage_absorbed' => 3],
        ], $result['events']);
    }

    public function testShieldLoadedFromDatabaseAbsorbsDamageAndBreaks(): void
    {
        $state = $this->state;
        $state['entities'][20]['has_shield'] = '1';
        $state['entities'][20]['shield_broken'] = '0';

        $result = HNS_PowerResolver::resolve('strike', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][20]['health']);
        $this->assertTrue($result['state']['entities'][20]['shield_broken']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'strike'],
            ['type' => 'shieldBroken', 'source_entity_id' => 20, 'damage_absorbed' => 2],
        ], $result['events']);
    }

    public function testShieldedTargetStillGetsPushedAndCollisionBreaksBlockingShield(): void
    {
        $state = $this->state;
        unset($state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][20] = ['id' => 20, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active', 'has_shield' => '1', 'shield_broken' => '0'];
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 3, 'health' => 2, 'state' => 'active', 'has_shield' => '1', 'shield_broken' => '0'];

        $result = HNS_PowerResolver::resolve('power_strike_2', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(0, $result['state']['entities'][20]['health']);
        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertTrue($result['state']['entities'][20]['shield_broken']);
        $this->assertSame(2, $result['state']['entities'][30]['health']);
        $this->assertTrue($result['state']['entities'][30]['shield_broken']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'power_strike_2'],
            ['type' => 'shieldBroken', 'source_entity_id' => 20, 'damage_absorbed' => 3],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 20],
            ['type' => 'shieldBroken', 'source_entity_id' => 30, 'damage_absorbed' => 1],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL, 'source_entity_id' => 10, 'target_entity_ids' => [20]],
        ], $result['events']);
    }

    public function testBrokenShieldDoesNotAbsorbLaterDamage(): void
    {
        $state = $this->state;
        $state['entities'][20]['has_shield'] = true;
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

    public function testDashClearsSlimedStatus(): void
    {
        $state = $this->state;
        unset($state['entities'][22]);
        $state['entities'][10]['status'] = 'slimed';

        $result = HNS_PowerResolver::resolve('dash_1', 10, ['target_tile_id' => 6], $state, $this->powers);

        $this->assertSame(6, $result['state']['entities'][10]['tile_id']);
        $this->assertNull($result['state']['entities'][10]['status']);
    }

    public function testSlimedHeroCannotJump(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'type_arg' => 2, 'tile_id' => 8, 'health' => 2, 'state' => 'active'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slimed heroes cannot move with jump_1.');

        HNS_PowerResolver::resolve('jump_1', 10, ['target_tile_id' => 3], $state, $this->powers);
    }

    public function testSlimedHeroCannotDashAttack(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'type_arg' => 2, 'tile_id' => 8, 'health' => 2, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 3, 'health' => 2, 'state' => 'active'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slimed heroes cannot move with dash_attack_1.');

        HNS_PowerResolver::resolve('dash_attack_1', 10, ['selected_tile_id' => 2, 'target_entity_id' => 31], $state, $this->powers);
    }

    public function testSlimedHeroCannotUseMovingWhirlwind(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'type_arg' => 2, 'tile_id' => 8, 'health' => 2, 'state' => 'active'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slimed heroes cannot move with whirlwind_2.');

        HNS_PowerResolver::resolve('whirlwind_2', 10, ['target_tile_id' => 2], $state, $this->powers);
    }

    public function testSlimedHeroCanUseWhirlwindWithoutMoving(): void
    {
        $state = $this->state;
        unset($state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][10]['status'] = 'slimed';

        $result = HNS_PowerResolver::resolve('whirlwind_1', 10, [], $state, $this->powers);

        $this->assertSame(1, $result['state']['entities'][10]['tile_id']);
        $this->assertSame('slimed', $result['state']['entities'][10]['status']);
        $this->assertSame(1, $result['state']['entities'][20]['health']);
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
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 10, 'damage' => 1, 'target_health' => 9],
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 20, 'damage' => 1, 'target_health' => 1],
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 24, 'damage' => 1, 'target_health' => 2],
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
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 10, 'damage' => 1, 'target_health' => 9],
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
            'vortex_1',
            10,
            ['selected_tile_id' => 2, 'target_entity_ids' => [21, 23]],
            $state,
            $this->powers
        );

        $this->assertSame('dead', $result['state']['entities'][21]['state']);
        $this->assertSame(0, $result['state']['entities'][21]['health']);
        $this->assertSame(2, $result['state']['entities'][21]['tile_id']);
        $this->assertSame(2, $result['state']['entities'][23]['tile_id']);
        $this->assertSame(2, $result['state']['entities'][23]['health']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'vortex_1'],
            ['type' => 'monsterMove', 'source_entity_id' => 21, 'target_tile_id' => 2],
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 23, 'damage' => 1, 'target_health' => 2],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 21],
            ['type' => 'monsterMove', 'source_entity_id' => 23, 'target_tile_id' => 2],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL, 'source_entity_id' => 10, 'target_entity_ids' => [21, 23]],
        ], $result['events']);
    }

    public function testVortexCanSelectTileAtDiagonalRangeTwo(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][23]);

        $result = HNS_PowerResolver::resolve(
            'vortex_1',
            10,
            ['selected_tile_id' => 5, 'target_entity_ids' => [22]],
            $state,
            $this->powers
        );

        $this->assertSame(5, $result['state']['entities'][22]['tile_id']);
        $this->assertSame(3, $result['state']['entities'][22]['health']);
    }

    public function testVortexRequiresTargetsAdjacentToSelectedTile(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][22], $state['entities'][23]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pull target is out of range from selected tile.');

        HNS_PowerResolver::resolve(
            'vortex_1',
            10,
            ['selected_tile_id' => 6, 'target_entity_ids' => [21]],
            $state,
            $this->powers
        );
    }

    public function testVortexCannotBeCastThroughObstacle(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][22], $state['entities'][23]);
        $state['tiles'][2]['type'] = 'pillar';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Selected tile is not in line of sight for pull power.');

        HNS_PowerResolver::resolve('vortex_1', 10, ['selected_tile_id' => 3, 'target_entity_ids' => [21]], $state, $this->powers);
    }

    public function testVortexCannotTargetTheSameEntityMoreThanOnce(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pull power cannot target the same entity more than once.');

        HNS_PowerResolver::resolve(
            'vortex_2',
            10,
            ['selected_tile_id' => 2, 'target_entity_ids' => [21, 21]],
            $this->state,
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
            'vortex_1',
            10,
            ['selected_tile_id' => 4, 'target_entity_ids' => [30, 31]],
            $state,
            $this->powers
        );

        $this->assertSame('active', $result['state']['entities'][31]['state']);
        $this->assertSame(1, $result['state']['entities'][31]['health']);
        $this->assertSame(2, $result['state']['entities'][30]['health']);
        $this->assertSame(2, $result['state']['entities'][30]['tile_id']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'vortex_1'],
            ['type' => 'monsterMove', 'source_entity_id' => 31, 'target_tile_id' => 4],
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 30, 'damage' => 1, 'target_health' => 2],
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 31, 'damage' => 1, 'target_health' => 1],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL, 'source_entity_id' => 10, 'target_entity_ids' => [31, 30]],
        ], $result['events']);
    }

    public function testVortexCollisionDamagesMovedMonstersAndMonstersAlreadyOnTargetTile(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 3, 'health' => 1, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 7, 'health' => 1, 'state' => 'active'];
        $state['entities'][32] = ['id' => 32, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active'];
        $state['entities'][33] = ['id' => 33, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve(
            'vortex_1',
            10,
            ['selected_tile_id' => 2, 'target_entity_ids' => [30, 31]],
            $state,
            $this->powers
        );

        foreach ([30, 32, 33] as $entityId) {
            $this->assertSame('dead', $result['state']['entities'][$entityId]['state']);
            $this->assertSame(0, $result['state']['entities'][$entityId]['health']);
        }
        $this->assertSame('active', $result['state']['entities'][31]['state']);
        $this->assertSame(1, $result['state']['entities'][31]['health']);
        $this->assertContains(['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 30], $result['events']);
        $this->assertContains(['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 32], $result['events']);
        $this->assertContains(['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 33], $result['events']);
    }

    public function testVortexCollisionDistributesDamageAcrossBigMonsters(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'big', 'tile_id' => 3, 'health' => 4, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'big', 'tile_id' => 7, 'health' => 4, 'state' => 'active'];
        $state['entities'][32] = ['id' => 32, 'type' => 'monster', 'monster_size' => 'big', 'tile_id' => 2, 'health' => 4, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve(
            'vortex_1',
            10,
            ['selected_tile_id' => 2, 'target_entity_ids' => [30, 31]],
            $state,
            $this->powers
        );

        $this->assertSame(3, $result['state']['entities'][30]['health']);
        $this->assertSame(3, $result['state']['entities'][31]['health']);
        $this->assertSame(2, $result['state']['entities'][32]['health']);
        $this->assertSame('active', $result['state']['entities'][30]['state']);
        $this->assertSame('active', $result['state']['entities'][31]['state']);
        $this->assertSame('active', $result['state']['entities'][32]['state']);
        $this->assertContains(['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 30, 'damage' => 1, 'target_health' => 3], $result['events']);
        $this->assertContains(['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 31, 'damage' => 1, 'target_health' => 3], $result['events']);
        $this->assertContains(['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 32, 'damage' => 1, 'target_health' => 2], $result['events']);
    }

    public function testVortexResolvesEachPulledMonsterSequentiallyAgainstCurrentBoard(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 7, 'health' => 2, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 7, 'health' => 3, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve(
            'vortex_1',
            10,
            ['selected_tile_id' => 2, 'target_entity_ids' => [30, 31]],
            $state,
            $this->powers
        );

        $this->assertSame(1, $result['state']['entities'][30]['health']);
        $this->assertSame(2, $result['state']['entities'][31]['health']);
        $this->assertSame('active', $result['state']['entities'][30]['state']);
        $this->assertSame('active', $result['state']['entities'][31]['state']);
        $this->assertSame(2, $result['state']['entities'][30]['tile_id']);
        $this->assertSame(7, $result['state']['entities'][31]['tile_id']);
        $this->assertContains(['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 30, 'damage' => 1, 'target_health' => 1], $result['events']);
        $this->assertContains(['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 31, 'damage' => 1, 'target_health' => 2], $result['events']);
    }

    public function testVortexCanDamageBossOnSelectedTile(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 7, 'health' => 2, 'state' => 'active'];
        $state['entities'][900] = ['id' => 900, 'type' => 'boss', 'boss_key' => 'slasher', 'phase' => 1, 'monster_size' => 'boss', 'tile_id' => 2, 'health' => 8, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('vortex_1', 10, ['selected_tile_id' => 2, 'target_entity_ids' => [30]], $state, $this->powers);

        $this->assertSame(1, $result['state']['entities'][30]['health']);
        $this->assertSame(7, $result['state']['entities'][900]['health']);
        $this->assertContains(['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 900, 'damage' => 1, 'target_health' => 7], $result['events']);
    }

    public function testLeechIgnoresShieldAndHealsOnlyOneHealth(): void
    {
        $state = $this->state;
        $state['entities'][10]['health'] = 7;
        $state['entities'][20] = ['id' => 20, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'health' => 3, 'state' => 'active', 'has_shield' => '1', 'shield_broken' => '0'];

        $result = HNS_PowerResolver::resolve('leech_3', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(0, $result['state']['entities'][20]['health']);
        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertSame('0', $result['state']['entities'][20]['shield_broken']);
        $this->assertSame(8, $result['state']['entities'][10]['health']);
        $this->assertNotContains('shieldBroken', array_column($result['events'], 'type'));
        $this->assertContains(['type' => 'entityHealed', 'source_entity_id' => 10, 'target_entity_id' => 10, 'heal' => 1, 'target_health' => 8], $result['events']);
    }

    public function testLeechCanTargetAdjacentDiagonalMonster(): void
    {
        $state = $this->state;
        $state['entities'][10]['health'] = 7;
        $state['entities'][22] = ['id' => 22, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 4, 'health' => 2, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('leech_1', 10, ['target_entity_id' => 22], $state, $this->powers);

        $this->assertSame(1, $result['state']['entities'][22]['health']);
        $this->assertSame(8, $result['state']['entities'][10]['health']);
    }

    public function testLeechHealsAfterThornsDamage(): void
    {
        $state = $this->state;
        $state['level_monster_abilities'] = ['thorns'];
        $state['entities'][10]['health'] = 1;

        $result = HNS_PowerResolver::resolve('leech_1', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(1, $result['state']['entities'][10]['health']);
        $this->assertSame('active', $result['state']['entities'][10]['state']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'leech_1'],
            ['type' => 'entityDamaged', 'source_entity_id' => 10, 'target_entity_id' => 20, 'damage' => 1, 'target_health' => 1],
            ['type' => 'thornsDamage', 'source_entity_id' => 20, 'target_entity_id' => 10, 'damage' => 1],
            ['type' => 'entityHealed', 'source_entity_id' => 10, 'target_entity_id' => 10, 'heal' => 1, 'target_health' => 1],
        ], $result['events']);
    }

    public function testFireballDamagesOrthogonalAreaAndIgnoresShield(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['tiles'][10] = ['id' => 10, 'x' => 3, 'y' => 1, 'type' => 'floor'];
        $state['tiles'][11] = ['id' => 11, 'x' => 3, 'y' => 2, 'type' => 'floor'];
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 11, 'health' => 2, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 7, 'health' => 2, 'state' => 'active', 'has_shield' => '1', 'shield_broken' => '0'];
        $state['entities'][32] = ['id' => 32, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 5, 'health' => 2, 'state' => 'active'];
        $state['entities'][33] = ['id' => 33, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 10, 'health' => 2, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('fireball_1', 10, ['target_tile_id' => 7], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][30]['health']);
        $this->assertSame(1, $result['state']['entities'][31]['health']);
        $this->assertSame(1, $result['state']['entities'][32]['health']);
        $this->assertSame(1, $result['state']['entities'][33]['health']);
        $this->assertSame('0', $result['state']['entities'][31]['shield_broken']);
        $this->assertNotContains('shieldBroken', array_column($result['events'], 'type'));
    }

    public function testFireballCanUseClickedMonsterTile(): void
    {
        $state = $this->state;
        $state['entities'][20]['health'] = 2;

        $result = HNS_PowerResolver::resolve('fireball_1', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(1, $result['state']['entities'][20]['health']);
    }

    public function testHealCanTargetPartnerWithinRangeAndIsCapped(): void
    {
        $state = $this->state;
        $state['entities'][11] = ['id' => 11, 'type' => 'hero', 'owner' => 2, 'tile_id' => 4, 'health' => 8, 'state' => 'active'];
        $state['players'] = [1 => ['id' => 1, 'max_health' => 10], 2 => ['id' => 2, 'max_health' => 10]];

        $result = HNS_PowerResolver::resolve('heal_3', 10, ['target_entity_id' => 11], $state, $this->powers);

        $this->assertSame(10, $result['state']['entities'][11]['health']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'heal_3'],
            ['type' => 'entityHealed', 'source_entity_id' => 10, 'target_entity_id' => 11, 'heal' => 2, 'target_health' => 10],
        ], $result['events']);
    }

    public function testDashAttackRankTwoMovesAndHitsOneTargetPerUse(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 3, 'health' => 2, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 4, 'health' => 2, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('dash_attack_2', 10, ['selected_tile_id' => 2, 'target_entity_id' => 30], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][10]['tile_id']);
        $this->assertSame(1, $result['state']['entities'][30]['health']);
        $this->assertSame(2, $result['state']['entities'][31]['health']);
        $this->assertContains(['type' => HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK, 'source_entity_id' => 10, 'power_key' => 'dash_attack_2'], $result['events']);
    }

    public function testDashAttackCanHitOneTilePastDashRange(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 3, 'health' => 2, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('dash_attack_1', 10, ['selected_tile_id' => 2, 'target_entity_id' => 30], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][10]['tile_id']);
        $this->assertSame(1, $result['state']['entities'][30]['health']);
    }

    public function testDashAttackUsesSelectedDestinationTile(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 7, 'health' => 2, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('dash_attack_2', 10, ['selected_tile_id' => 4, 'target_entity_id' => 30], $state, $this->powers);

        $this->assertSame(4, $result['state']['entities'][10]['tile_id']);
        $this->assertSame(1, $result['state']['entities'][30]['health']);
    }

    public function testDashAttackCanDashToEmptyDestinationAndHitNothing(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);

        $result = HNS_PowerResolver::resolve('dash_attack_2', 10, ['selected_tile_id' => 3, 'target_tile_id' => 3], $state, $this->powers);

        $this->assertSame(3, $result['state']['entities'][10]['tile_id']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'dash_attack_2'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_DASH, 'source_entity_id' => 10, 'target_tile_id' => 3],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK, 'source_entity_id' => 10, 'power_key' => 'dash_attack_2'],
        ], $result['events']);
    }

    public function testDashAttackTriggersThornsAfterMovingIntoMelee(): void
    {
        $state = $this->state;
        $state['level_monster_abilities'] = ['thorns'];
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 3, 'health' => 2, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('dash_attack_2', 10, ['selected_tile_id' => 2, 'target_entity_id' => 30], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][10]['tile_id']);
        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame(1, $result['state']['entities'][30]['health']);
        $this->assertContains(['type' => 'thornsDamage', 'source_entity_id' => 30, 'target_entity_id' => 10, 'damage' => 1], $result['events']);
    }

    public function testDashAttackRankThreeDealsTwoDamageOncePerUse(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 3, 'health' => 3, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 4, 'health' => 3, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('dash_attack_3', 10, ['selected_tile_id' => 2, 'target_entity_id' => 30], $state, $this->powers);

        $this->assertSame(1, $result['state']['entities'][30]['health']);
        $this->assertSame(3, $result['state']['entities'][31]['health']);
    }

    public function testWhirlwindRankOneDamagesAroundCurrentHeroTileWithoutMoving(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'health' => 2, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 5, 'health' => 2, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('whirlwind_1', 10, [], $state, $this->powers);

        $this->assertSame(1, $result['state']['entities'][10]['tile_id']);
        $this->assertSame(1, $result['state']['entities'][30]['health']);
        $this->assertSame(2, $result['state']['entities'][31]['health']);
    }

    public function testWhirlwindRankOneDefaultsZeroTargetTileToCurrentHeroTile(): void
    {
        $state = $this->state;
        unset($state['entities'][21], $state['entities'][22], $state['entities'][23]);

        $result = HNS_PowerResolver::resolve('whirlwind_1', 10, ['target_tile_id' => 0, 'selected_tile_id' => 0], $state, $this->powers);

        $this->assertSame(1, $result['state']['entities'][10]['tile_id']);
        $this->assertSame(1, $result['state']['entities'][20]['health']);
    }

    public function testWhirlwindRankTwoMovesThenDamagesAroundLandingTile(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 7, 'health' => 2, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 5, 'health' => 2, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('whirlwind_2', 10, ['target_tile_id' => 4], $state, $this->powers);

        $this->assertSame(4, $result['state']['entities'][10]['tile_id']);
        $this->assertSame(1, $result['state']['entities'][30]['health']);
        $this->assertSame(1, $result['state']['entities'][31]['health']);
    }

    public function testProjectionRankOnePushesOrthogonalTargetWithoutDamage(): void
    {
        $state = $this->state;
        unset($state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['tiles'][10] = ['id' => 10, 'x' => 3, 'y' => 0, 'type' => 'floor'];
        $state['tiles'][11] = ['id' => 11, 'x' => 4, 'y' => 0, 'type' => 'floor'];
        $state['entities'][20]['health'] = 2;

        $result = HNS_PowerResolver::resolve('grab_1', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][20]['health']);
        $this->assertSame(11, $result['state']['entities'][20]['tile_id']);
    }

    public function testProjectionRankTwoCanPushDiagonalTarget(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][23]);
        $state['tiles'][10] = ['id' => 10, 'x' => 2, 'y' => 2, 'type' => 'floor'];
        $state['tiles'][11] = ['id' => 11, 'x' => 3, 'y' => 3, 'type' => 'floor'];
        $state['entities'][22] = ['id' => 22, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 4, 'health' => 2, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('grab_2', 10, ['target_entity_id' => 22], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][22]['health']);
        $this->assertSame(11, $result['state']['entities'][22]['tile_id']);
    }

    public function testProjectionRankThreePushesAndDamages(): void
    {
        $state = $this->state;
        unset($state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['tiles'][10] = ['id' => 10, 'x' => 3, 'y' => 0, 'type' => 'floor'];
        $state['tiles'][11] = ['id' => 11, 'x' => 4, 'y' => 0, 'type' => 'floor'];
        $state['entities'][20]['health'] = 3;

        $result = HNS_PowerResolver::resolve('grab_3', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][20]['health']);
        $this->assertSame(11, $result['state']['entities'][20]['tile_id']);
    }

    public function testJumpIgnoresObstacles(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['tiles'][2]['type'] = 'wall';

        $result = HNS_PowerResolver::resolve('jump_1', 10, ['target_tile_id' => 3], $state, $this->powers);

        $this->assertSame(3, $result['state']['entities'][10]['tile_id']);
    }

    public function testJumpRankOneCannotLandOnMonster(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Jump target is occupied for jump_1.');

        HNS_PowerResolver::resolve('jump_1', 10, ['target_tile_id' => 2], $this->state, $this->powers);
    }

    public function testJumpRankTwoPushesMonsterOnLandingTile(): void
    {
        $state = $this->state;
        unset($state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['tiles'][10] = ['id' => 10, 'x' => 2, 'y' => 0, 'type' => 'floor'];
        $state['entities'][20]['health'] = 2;

        $result = HNS_PowerResolver::resolve('jump_2', 10, ['target_tile_id' => 2], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][10]['tile_id']);
        $this->assertSame(3, $result['state']['entities'][20]['tile_id']);
        $this->assertSame(2, $result['state']['entities'][20]['health']);
    }

    public function testJumpRankThreePushesAndDamagesMonsterOnLandingTile(): void
    {
        $state = $this->state;
        unset($state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['tiles'][10] = ['id' => 10, 'x' => 2, 'y' => 0, 'type' => 'floor'];
        $state['entities'][20]['health'] = 2;

        $result = HNS_PowerResolver::resolve('jump_3', 10, ['target_tile_id' => 2], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][10]['tile_id']);
        $this->assertSame(3, $result['state']['entities'][20]['tile_id']);
        $this->assertSame(1, $result['state']['entities'][20]['health']);
    }

    public function testJumpMovesMonsterToAdjacentFallbackAndDamagesWhenPushTileIsBlocked(): void
    {
        $state = $this->state;
        unset($state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][20]['health'] = 2;
        $state['tiles'][1]['type'] = 'wall';
        $state['entities'][30] = ['id' => 30, 'type' => 'monster', 'monster_size' => 'big', 'tile_id' => 3, 'health' => 4, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('jump_2', 10, ['target_tile_id' => 2], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][10]['tile_id']);
        $this->assertSame(4, $result['state']['entities'][20]['tile_id']);
        $this->assertSame(1, $result['state']['entities'][20]['health']);
    }

    public function testKamikazeExplodesWhenKilled(): void
    {
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['entities'][20] = ['id' => 20, 'type' => 'monster', 'type_arg' => 4, 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active', 'on_death' => 'explode'];

        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 20], $state, $this->powers);

        $this->assertSame('dead', $result['state']['entities'][20]['state']);
        $this->assertSame(8, $result['state']['entities'][10]['health']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'attack'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 20, 'death_effect' => 'explode'],
            ['type' => 'monsterExplode', 'source_entity_id' => 20, 'target_entity_ids' => [10], 'target_health_by_entity_id' => [10 => 8], 'damage' => 2],
        ], $result['events']);
    }

    public function testBossAtZeroHealthStartsNextPhaseWithoutImmediateBossTurn(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';
        $bosses['slasher']['phases'][2] = ['health' => 8, 'move' => 2, 'move_metric' => 'chebyshev', 'range' => 1, 'range_metric' => 'chebyshev', 'damage' => 2, 'can_attack' => true, 'can_move' => true, 'can_attack_and_move' => true];
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['bosses'] = $bosses;
        $state['entities'][30] = ['id' => 30, 'type' => 'boss', 'boss_key' => 'slasher', 'phase' => 1, 'monster_size' => 'boss', 'tile_id' => 2, 'health' => 1, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 30], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][30]['phase']);
        $this->assertSame(8, $result['state']['entities'][30]['health']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'attack'],
            ['type' => 'bossPhaseDefeated', 'source_entity_id' => 30, 'boss_key' => 'slasher', 'phase' => 1],
            ['type' => 'bossPhaseStarted', 'source_entity_id' => 30, 'entity_id' => 30, 'boss_key' => 'slasher', 'phase' => 2, 'health' => 8],
        ], $result['events']);
    }

    public function testBossPhaseTransitionDoesNotMoveBeforeAttacking(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['bosses'] = $bosses;
        $state['monster_material'] = [];
        $state['boss_spawn_seed'] = 1;
        $state['level_monster_abilities'] = [];
        $state['entities'][10]['tile_id'] = 5;
        $state['entities'][30] = ['id' => 30, 'type' => 'boss', 'boss_key' => 'slasher', 'phase' => 1, 'monster_size' => 'boss', 'tile_id' => 2, 'health' => 1, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('quick_shot_1', 10, ['target_entity_ids' => [30]], $state, $this->powers);

        $this->assertSame(2, $result['state']['entities'][30]['tile_id']);
        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertNotContains('monsterMove', array_column($result['events'], 'type'));
        $this->assertNotContains('monsterAttack', array_column($result['events'], 'type'));
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

    public function testThirdBossPhaseDefeatWinsGameEvenWithActiveMinions(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';
        $state = $this->state;
        unset($state['entities'][20], $state['entities'][21], $state['entities'][22], $state['entities'][23]);
        $state['bosses'] = $bosses;
        $state['entities'][30] = ['id' => 30, 'type' => 'boss', 'boss_key' => 'slasher', 'phase' => 3, 'monster_size' => 'boss', 'tile_id' => 2, 'health' => 1, 'state' => 'active'];
        $state['entities'][31] = ['id' => 31, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 5, 'health' => 1, 'state' => 'active'];

        $result = HNS_PowerResolver::resolve('attack', 10, ['target_entity_id' => 30], $state, $this->powers);

        $this->assertTrue($result['state']['game_won']);
        $this->assertTrue(HNS_GameEngine::isLevelCleared($result['state']));
        $this->assertSame('active', $result['state']['entities'][31]['state']);
        $this->assertSame('gameWon', $result['events'][2]['type']);
    }
}
