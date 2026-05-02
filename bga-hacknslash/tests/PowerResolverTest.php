<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_BoardRules.php';
require_once dirname(__DIR__) . '/modules/HNS_FreeActionEngine.php';
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
            ],
            'entities' => [
                10 => ['id' => 10, 'type' => 'hero', 'owner' => 1, 'tile_id' => 1, 'health' => 10, 'state' => 'active'],
                20 => ['id' => 20, 'type' => 'monster', 'tile_id' => 2, 'health' => 2, 'state' => 'active'],
                21 => ['id' => 21, 'type' => 'monster', 'tile_id' => 3, 'health' => 1, 'state' => 'active'],
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

    public function testDashMovesHeroAndEmitsDashAndCardPlayedEvents(): void
    {
        $result = HNS_PowerResolver::resolve('dash', 10, ['target_tile_id' => 4], $this->state, $this->powers);

        $this->assertSame(4, $result['state']['entities'][10]['tile_id']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'dash'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_DASH, 'source_entity_id' => 10, 'target_tile_id' => 4],
        ], $result['events']);
    }

    public function testVortexPullsTargetMonsterOneStepTowardSelectedTileAndEmitsPullEvent(): void
    {
        $state = $this->state;
        unset($state['entities'][20]);

        $result = HNS_PowerResolver::resolve(
            'vortex',
            10,
            ['selected_tile_id' => 2, 'target_entity_ids' => [21]],
            $state,
            $this->powers
        );

        $this->assertSame(2, $result['state']['entities'][21]['tile_id']);
        $this->assertSame([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'vortex'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL, 'source_entity_id' => 10, 'target_entity_ids' => [21]],
        ], $result['events']);
    }
}
