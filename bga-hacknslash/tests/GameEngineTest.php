<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/material/constants.inc.php';
require_once dirname(__DIR__) . '/modules/HNS_LevelGenerator.php';
require_once dirname(__DIR__) . '/modules/HNS_RoomSlotPattern.php';
require_once dirname(__DIR__) . '/modules/HNS_BoardRules.php';
require_once dirname(__DIR__) . '/modules/HNS_MonsterAi.php';
require_once dirname(__DIR__) . '/modules/HNS_LevelReward.php';
require_once dirname(__DIR__) . '/modules/HNS_GameEngine.php';

final class GameEngineTest extends TestCase
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

    public function testCreateLevelDrawsMonstersForLevelSlotsAndAppliesEnchantment(): void
    {
        $state = HNS_GameEngine::createLevel(2, 123, $this->monsters, array_keys($this->monsters), ['shield']);

        $this->assertFalse($state['is_boss_level']);
        $this->assertCount(2, $state['monster_slots']);
        $this->assertNotEmpty($state['tiles']);
        $this->assertNotEmpty($state['entities']);
        $this->assertSame(['shield'], $state['level_monster_abilities']);
    }

    public function testBossLevelDoesNotGenerateRoomMonsterSlots(): void
    {
        $state = HNS_GameEngine::createLevel(8, 123, $this->monsters, array_keys($this->monsters));

        $this->assertTrue($state['is_boss_level']);
        $this->assertSame([], $state['monster_slots']);
        $this->assertSame([], $state['entities']);
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

    public function testPrepareLevelRewardAddsOfferOnlyWhenCleared(): void
    {
        $state = ['entities' => [20 => ['type' => 'monster', 'state' => 'dead']]];

        $updated = HNS_GameEngine::prepareLevelReward($state, $this->powers, ['attack', 'dash_1', 'vortex']);

        $this->assertSame(['dash_1', 'vortex'], $updated['reward_offer']);
    }
}
