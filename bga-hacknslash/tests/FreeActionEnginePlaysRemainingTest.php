<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_FreeActionEngine.php';

final class FreeActionEnginePlaysRemainingTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function dashAttackEvents(): array
    {
        return [
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_CARD_PLAYED, 'source_entity_id' => 10, 'power_key' => 'dash_attack_2'],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_DASH, 'source_entity_id' => 10, 'target_tile_id' => 5],
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK, 'source_entity_id' => 10, 'power_key' => 'dash_attack_2'],
        ];
    }

    public function testCannotChainWithoutPlaysRemainingWhenCooldownActive(): void
    {
        $engine = new HNS_FreeActionEngine($this->dashAttackEvents(), []);

        $this->assertFalse($engine->canUseFreeAction(
            'dash_attack_2',
            [HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK],
            $cooldown = 2,
            $playsRemaining = 0
        ));
    }

    public function testCanChainWhenPlaysRemainingPositiveBypassesCooldown(): void
    {
        $engine = new HNS_FreeActionEngine($this->dashAttackEvents(), []);

        $this->assertTrue($engine->canUseFreeAction(
            'dash_attack_2',
            [HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK],
            $cooldown = 2,
            $playsRemaining = 1
        ));
    }

    public function testChainPlayCanRepeatSamePowerWhilePlaysRemain(): void
    {
        $engine = new HNS_FreeActionEngine($this->dashAttackEvents(), []);

        $result = $engine->useFreeAction(
            'dash_attack_2',
            [HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK],
            $currentCooldown = 2,
            $nextCooldown = 2,
            $this->dashAttackEvents(),
            $playsRemaining = 1
        );

        $this->assertSame(['dash_attack_2'], $result['used_action_keys']);

        $nextEngine = new HNS_FreeActionEngine($result['active_event_chain'], $result['used_action_keys']);
        $this->assertTrue($nextEngine->canUseFreeAction(
            'dash_attack_2',
            [HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK],
            $cooldown = 2,
            $playsRemaining = 1
        ));

        $this->assertFalse($nextEngine->canUseFreeAction(
            'dash_attack_2',
            [HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK],
            $cooldown = 2,
            $playsRemaining = 0
        ));
    }

    public function testZeroCooldownStillBypassedNormallyWithoutPlaysRemaining(): void
    {
        $engine = new HNS_FreeActionEngine($this->dashAttackEvents(), []);

        $this->assertTrue($engine->canUseFreeAction(
            'dash_attack_2',
            [HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK],
            $cooldown = 0,
            $playsRemaining = 0
        ));
    }

    public function testNoMatchingEventStillRefusesEvenWithPlaysRemaining(): void
    {
        $engine = new HNS_FreeActionEngine([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 20],
        ], []);

        $this->assertFalse($engine->canUseFreeAction(
            'dash_attack_2',
            [HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK],
            $cooldown = 2,
            $playsRemaining = 5
        ));
    }
}
