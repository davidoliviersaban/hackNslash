<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_FreeActionEngine.php';

final class FreeActionEngineTest extends TestCase
{
    public function testFreeActionIsAvailableWhenAnActiveEventMatchesItsTrigger(): void
    {
        $engine = new HNS_FreeActionEngine([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_MOVE, 'source_player_id' => 1],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_DASH, 'source_player_id' => 1],
        ]);

        $this->assertTrue($engine->canUseFreeAction(
            'quick_strikes',
            [HNS_FreeActionEngine::EVENT_AFTER_MOVE],
            0
        ));
    }

    public function testFreeActionIsRejectedWithoutMatchingEvent(): void
    {
        $engine = new HNS_FreeActionEngine([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_player_id' => 1],
        ]);

        $this->assertFalse($engine->canUseFreeAction(
            'quick_strikes',
            [HNS_FreeActionEngine::EVENT_AFTER_MOVE],
            0
        ));
    }

    public function testFreeActionIsRejectedWhenPowerIsInCooldown(): void
    {
        $engine = new HNS_FreeActionEngine([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_MOVE, 'source_player_id' => 1],
        ]);

        $this->assertFalse($engine->canUseFreeAction(
            'quick_strikes',
            [HNS_FreeActionEngine::EVENT_AFTER_MOVE],
            1
        ));
    }

    public function testFreeActionConsumesTriggerAndReplacesEventChainWithProducedEvents(): void
    {
        $engine = new HNS_FreeActionEngine([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_MOVE, 'source_player_id' => 1],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_DASH, 'source_player_id' => 1],
        ]);

        $result = $engine->useFreeAction(
            'throw',
            [HNS_FreeActionEngine::EVENT_AFTER_DASH],
            0,
            2,
            [['type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL, 'source_player_id' => 2]]
        );

        $this->assertSame(HNS_FreeActionEngine::EVENT_AFTER_DASH, $result['consumed_event']['type']);
        $this->assertSame(2, $result['cooldown']);
        $this->assertSame(['throw'], $result['used_action_keys']);
        $this->assertSame(
            [['type' => HNS_FreeActionEngine::EVENT_AFTER_PUSH_OR_PULL, 'source_player_id' => 2]],
            $result['active_event_chain']
        );
    }

    public function testSamePowerCannotBeUsedTwiceInTheSameFreeActionChain(): void
    {
        $engine = new HNS_FreeActionEngine([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_MOVE, 'source_player_id' => 1],
        ], ['quick_strikes']);

        $this->assertFalse($engine->canUseFreeAction(
            'quick_strikes',
            [HNS_FreeActionEngine::EVENT_AFTER_MOVE],
            0
        ));
    }

    public function testFreeActionCanChainFromEventsProducedByAFreeAction(): void
    {
        $engine = new HNS_FreeActionEngine([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_player_id' => 1],
        ]);

        $result = $engine->useFreeAction(
            'dash_1',
            [HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED],
            0,
            2,
            [
                ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_player_id' => 1],
                ['type' => HNS_FreeActionEngine::EVENT_ENTITY_DAMAGED, 'source_player_id' => 1],
            ]
        );

        $nextEngine = new HNS_FreeActionEngine($result['active_event_chain'], $result['used_action_keys']);

        $this->assertTrue($nextEngine->canUseFreeAction(
            'dash_2',
            [HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED],
            0
        ));
    }

    public function testDashFreeTriggerDoesNotMatchDamageOnlyEvents(): void
    {
        $engine = new HNS_FreeActionEngine([
            ['type' => HNS_FreeActionEngine::EVENT_ENTITY_DAMAGED, 'source_player_id' => 1],
        ]);

        $this->assertFalse($engine->canUseFreeAction(
            'dash_1',
            [HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED],
            0
        ));
    }
}
