<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/material/constants.inc.php';
require_once dirname(__DIR__) . '/modules/HNS_SeededRandom.php';
require_once dirname(__DIR__) . '/modules/HNS_LevelGenerator.php';
require_once dirname(__DIR__) . '/modules/HNS_RoomSlotPattern.php';
require_once dirname(__DIR__) . '/modules/HNS_BoardRules.php';
require_once dirname(__DIR__) . '/modules/HNS_MonsterAi.php';
require_once dirname(__DIR__) . '/modules/HNS_LevelReward.php';
require_once dirname(__DIR__) . '/modules/HNS_BossEngine.php';
require_once dirname(__DIR__) . '/modules/HNS_GameEngine.php';

final class GameEngineTest extends TestCase
{
    private array $monsters;
    private array $powers;
    private array $bosses;

    protected function setUp(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';
        $this->monsters = $monsters;
        $this->powers = $bonus_cards;
        $this->bosses = $bosses;
    }

    public function testCreateLevelDrawsMonstersForLevelSlotsAndAppliesEnchantment(): void
    {
        $state = HNS_GameEngine::createLevel(2, 123, $this->monsters, array_keys($this->monsters), ['shield']);

        $this->assertFalse($state['is_boss_level']);
        $this->assertCount(2, $state['monster_slots']);
        $this->assertNotEmpty($state['tiles']);
        $this->assertNotEmpty($state['entities']);
        $this->assertSame(['shield'], $state['level_monster_abilities']);

        foreach ($state['entities'] as $entity) {
            $this->assertTrue($entity['has_shield'] ?? false);
            $this->assertFalse($entity['shield_broken'] ?? true);
        }
    }

    public function testBossLevelGeneratesRoomAndRandomBossEntityWithoutMonsterSlots(): void
    {
        $state = HNS_GameEngine::createLevel(8, 123, $this->monsters, array_keys($this->monsters), [], $this->bosses);

        $this->assertTrue($state['is_boss_level']);
        $this->assertSame([], $state['monster_slots']);
        $this->assertNotEmpty($state['layout']['terrain']);
        $this->assertNotEmpty($state['tiles']);
        $this->assertSame('boss', $state['entities'][900]['type']);
        $this->assertContains($state['entities'][900]['boss_key'], array_keys($this->bosses));
        $this->assertSame(1, $state['entities'][900]['phase']);
        $this->assertTrue(HNS_BoardRules::isTileWalkable($state['tiles'][$state['entities'][900]['tile_id']]));
        foreach ($state['entities'] as $entity) {
            $this->assertNotSame('monster', $entity['type']);
        }
    }

    public function testBossLevelSelectionUsesSeed(): void
    {
        $bossKeysBySeed = [];
        foreach ([1, 2, 3, 4, 5, 6] as $seed) {
            $state = HNS_GameEngine::createLevel(8, $seed, $this->monsters, array_keys($this->monsters), [], $this->bosses);
            $bossKeysBySeed[] = $state['entities'][900]['boss_key'];
        }

        $this->assertSame(
            HNS_GameEngine::createLevel(8, 1, $this->monsters, array_keys($this->monsters), [], $this->bosses)['entities'][900]['boss_key'],
            HNS_GameEngine::createLevel(8, 1, $this->monsters, array_keys($this->monsters), [], $this->bosses)['entities'][900]['boss_key']
        );
        $this->assertContains('slasher', $bossKeysBySeed);
        $this->assertContains('striker', $bossKeysBySeed);
    }

    public function testLevelsSixAndSevenCreateRoomsWithoutExceedingSmallOrLargeSlotCapacity(): void
    {
        foreach ([6, 7] as $level) {
            $state = HNS_GameEngine::createLevel($level, 2604625041733785262, $this->monsters, array_keys($this->monsters));

            $this->assertFalse($state['is_boss_level']);
            $this->assertCount($level, $state['monster_slots']);
            $this->assertSame([], HNS_RoomSlotPattern::validate($state['monster_slots']));
        }
    }

    public function testActivateMonstersUsesSmallBeforeBigOrder(): void
    {
        $state = [
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 1, 'health' => 10, 'state' => 'active'],
                20 => ['id' => 20, 'type' => 'monster', 'type_arg' => 7, 'monster_size' => 'big', 'tile_id' => 3, 'health' => 4, 'state' => 'active'],
                21 => ['id' => 21, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active'],
            ],
        ];

        $result = HNS_GameEngine::activateMonsters($state, $this->monsters);

        $this->assertSame(21, $result['events'][0]['source_entity_id']);
    }

    public function testGoblinDoesNotAttackAfterMovingDuringMonsterPhase(): void
    {
        $state = [
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 1, 'health' => 10, 'state' => 'active'],
                20 => ['id' => 20, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 3, 'health' => 1, 'state' => 'active'],
            ],
        ];

        $result = HNS_GameEngine::activateMonsters($state, $this->monsters);

        $this->assertSame(2, $result['state']['entities'][20]['tile_id']);
        $this->assertSame(10, $result['state']['entities'][10]['health']);
        $this->assertSame([['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 2]], $result['events']);
    }

    public function testNonMoveAndAttackMonstersCannotEmitBothEventsInOneActivation(): void
    {
        $state = [
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
                4 => ['id' => 4, 'x' => 3, 'y' => 0, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 1, 'health' => 10, 'state' => 'active'],
                20 => ['id' => 20, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 3, 'health' => 1, 'state' => 'active'],
                21 => ['id' => 21, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 4, 'health' => 1, 'state' => 'active'],
            ],
        ];

        $result = HNS_GameEngine::activateMonsters($state, $this->monsters);
        $eventsBySource = [];
        foreach ($result['events'] as $event) {
            $eventsBySource[$event['source_entity_id']][] = $event['type'];
        }

        foreach ($eventsBySource as $eventTypes) {
            $this->assertFalse(in_array('monsterMove', $eventTypes, true) && in_array('monsterAttack', $eventTypes, true));
        }
    }

    public function testStackedGoblinsOnSameTileActivateAsOneEntity(): void
    {
        $state = [
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 1, 'health' => 10, 'state' => 'active'],
                20 => ['id' => 20, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active'],
                21 => ['id' => 21, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active'],
            ],
        ];

        $result = HNS_GameEngine::activateMonsters($state, $this->monsters);

        $this->assertSame(9, $result['state']['entities'][10]['health']);
        $this->assertSame([
            ['type' => 'monsterAttack', 'source_entity_id' => 20, 'target_entity_id' => 10, 'damage' => 1, 'target_health' => 9, 'actor_entity_ids' => [20, 21]],
        ], $result['events']);
    }

    public function testMonsterAttackEventsExposeAllActorEntityIds(): void
    {
        $state = [
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 1, 'health' => 10, 'state' => 'active'],
                20 => ['id' => 20, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active'],
                21 => ['id' => 21, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active'],
            ],
        ];

        $result = HNS_GameEngine::activateMonsters($state, $this->monsters);

        $this->assertSame([20, 21], $result['events'][0]['actor_entity_ids']);
    }

    public function testStackedGoblinsMoveOnceAsAStack(): void
    {
        $state = [
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 1, 'health' => 10, 'state' => 'active'],
                20 => ['id' => 20, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 3, 'health' => 1, 'state' => 'active'],
                21 => ['id' => 21, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 3, 'health' => 1, 'state' => 'active'],
            ],
        ];

        $result = HNS_GameEngine::activateMonsters($state, $this->monsters);

        $this->assertSame(2, $result['state']['entities'][20]['tile_id']);
        $this->assertSame(2, $result['state']['entities'][21]['tile_id']);
        $this->assertSame([['type' => 'monsterMove', 'source_entity_id' => 20, 'target_tile_id' => 2, 'moved_entity_ids' => [20, 21]]], $result['events']);
    }

    public function testActivateMonstersActivatesBosses(): void
    {
        $state = [
            'bosses' => $this->bosses,
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 3, 'health' => 10, 'state' => 'active'],
                30 => ['id' => 30, 'type' => 'boss', 'boss_key' => 'slasher', 'phase' => 1, 'monster_size' => 'boss', 'tile_id' => 1, 'health' => 8, 'state' => 'active'],
            ],
        ];

        $result = HNS_GameEngine::activateMonsters($state, $this->monsters);

        $this->assertSame(2, $result['state']['entities'][30]['tile_id']);
        $this->assertSame(8, $result['state']['entities'][10]['health']);
        $this->assertSame([
            ['type' => 'monsterMove', 'source_entity_id' => 30, 'target_tile_id' => 2],
            ['type' => 'monsterAttack', 'source_entity_id' => 30, 'target_entity_id' => 10, 'damage' => 2, 'target_health' => 8],
        ], $result['events']);
    }

    public function testBossShieldPreActionRunsBeforeMonstersAndRepairsDbShieldState(): void
    {
        $state = [
            'bosses' => $this->bosses,
            'monster_material' => $this->monsters,
            'level_monster_abilities' => [],
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
                4 => ['id' => 4, 'x' => 3, 'y' => 0, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 4, 'health' => 10, 'state' => 'active'],
                20 => ['id' => 20, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 2, 'health' => 1, 'state' => 'active', 'has_shield' => '0', 'shield_broken' => '0'],
                21 => ['id' => 21, 'type' => 'monster', 'type_arg' => 2, 'monster_size' => 'small', 'tile_id' => 3, 'health' => 2, 'state' => 'active', 'has_shield' => '1', 'shield_broken' => '1'],
                30 => ['id' => 30, 'type' => 'boss', 'boss_key' => 'striker', 'phase' => 3, 'monster_size' => 'boss', 'tile_id' => 1, 'health' => 10, 'state' => 'active', 'has_shield' => '1', 'shield_broken' => '1'],
            ],
        ];

        $result = HNS_GameEngine::activateMonsters($state, $this->monsters);

        $this->assertSame('bossGrantShield', $result['events'][0]['type']);
        $this->assertSame([20, 21, 30], $result['events'][0]['target_entity_ids']);
        $this->assertSame(1, $result['state']['entities'][20]['has_shield']);
        $this->assertSame(0, $result['state']['entities'][20]['shield_broken']);
        $this->assertSame(1, $result['state']['entities'][21]['has_shield']);
        $this->assertSame(0, $result['state']['entities'][21]['shield_broken']);
        $this->assertSame(1, $result['state']['entities'][30]['has_shield']);
        $this->assertSame(0, $result['state']['entities'][30]['shield_broken']);
    }

    public function testPrepareLevelRewardAddsOfferOnlyWhenCleared(): void
    {
        $state = ['entities' => [20 => ['type' => 'monster', 'state' => 'dead']], 'player_powers' => []];

        $updated = HNS_GameEngine::prepareLevelReward($state, $this->powers, ['attack', 'dash_1', 'vortex_1']);

        $this->assertSame(['dash_1', 'vortex_1'], $updated['reward_offer']);
        $this->assertSame([], $updated['reward_upgrades']);
    }
}
