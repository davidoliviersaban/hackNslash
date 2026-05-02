<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_BoardRules.php';
require_once dirname(__DIR__) . '/modules/HNS_MonsterAi.php';
require_once dirname(__DIR__) . '/modules/HNS_LevelGenerator.php';
require_once dirname(__DIR__) . '/modules/HNS_BossEngine.php';

final class BossEngineTest extends TestCase
{
    public function testInitialBossEntityUsesPhaseOneHealth(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';

        $entity = HNS_BossEngine::initialBossEntity('slasher', 900, 12, $bosses);

        $this->assertSame('boss', $entity['type']);
        $this->assertSame('slasher', $entity['boss_key']);
        $this->assertSame(1, $entity['phase']);
        $this->assertSame(8, $entity['health']);
    }

    public function testSlasherPhaseTwoSpawnsTwoMinionsBeforeAction(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $events = [];
        $state = $this->bossState(2, $monsters);

        $state = HNS_BossEngine::activateBossTurn(30, $state, $bosses, $events);

        $this->assertArrayHasKey(31, $state['entities']);
        $this->assertArrayHasKey(32, $state['entities']);
        $this->assertSame('bossSpawnMinion', $events[0]['type']);
        $this->assertSame('bossSpawnMinion', $events[1]['type']);
        $this->assertSame('monsterMove', $events[2]['type']);
    }

    public function testSlasherPhaseThreeGrantsShieldThenSpawnsThenActs(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $events = [];
        $state = $this->bossState(3, $monsters);
        $state['entities'][20] = ['id' => 20, 'type' => 'monster', 'type_arg' => 1, 'monster_size' => 'small', 'tile_id' => 5, 'health' => 1, 'state' => 'active', 'has_shield' => true, 'shield_broken' => false];

        $state = HNS_BossEngine::activateBossTurn(30, $state, $bosses, $events);

        $this->assertContains('shield', $state['level_monster_abilities']);
        $this->assertTrue($state['entities'][30]['has_shield']);
        $this->assertTrue($state['entities'][20]['has_shield']);
        $this->assertFalse($state['entities'][20]['shield_broken']);
        $this->assertTrue($state['entities'][31]['has_shield']);
        $this->assertTrue($state['entities'][32]['has_shield']);
        $this->assertSame('bossGrantShield', $events[0]['type']);
        $this->assertSame('bossSpawnMinion', $events[1]['type']);
        $this->assertSame('bossSpawnMinion', $events[2]['type']);
    }

    private function bossState(int $phase, array $monsters): array
    {
        return [
            'boss_spawn_seed' => 1,
            'monster_material' => $monsters,
            'level_monster_abilities' => [],
            'tiles' => [
                1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
                2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'floor'],
                3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'floor'],
                4 => ['id' => 4, 'x' => 3, 'y' => 0, 'type' => 'floor'],
                5 => ['id' => 5, 'x' => 1, 'y' => 1, 'type' => 'floor'],
                6 => ['id' => 6, 'x' => 2, 'y' => 1, 'type' => 'floor'],
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 4, 'health' => 10, 'state' => 'active'],
                30 => ['id' => 30, 'type' => 'boss', 'boss_key' => 'slasher', 'phase' => $phase, 'monster_size' => 'boss', 'tile_id' => 1, 'health' => 9, 'state' => 'active'],
            ],
        ];
    }
}
