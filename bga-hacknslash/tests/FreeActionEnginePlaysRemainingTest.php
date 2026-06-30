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

    public function testPlaysRemainingAllowsRepeatEvenWithoutMatchingEvent(): void
    {
        $engine = new HNS_FreeActionEngine([
            ['type' => HNS_FreeActionEngine::EVENT_AFTER_KILL, 'source_entity_id' => 10, 'target_entity_id' => 20],
        ], []);

        $this->assertTrue($engine->canUseFreeAction(
            'dash_attack_2',
            [HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK],
            $cooldown = 2,
            $playsRemaining = 5
        ));
    }

    public function testReinforcedDashAttackRankThreeStartsSixTotalPlays(): void
    {
        $dashAttackRankThree = ['effect' => 'dash_attack', 'plays' => 3];

        $this->assertSame(2, HNS_FreeActionEngine::playsRemainingAfterUse($dashAttackRankThree, 0, false));
        $this->assertSame(5, HNS_FreeActionEngine::playsRemainingAfterUse($dashAttackRankThree, 0, true));
    }

    public function testReinforcedSinglePlayPowerStartsTwoTotalPlays(): void
    {
        $strike = ['effect' => 'attack'];

        $this->assertSame(0, HNS_FreeActionEngine::playsRemainingAfterUse($strike, 0, false));
        $this->assertSame(1, HNS_FreeActionEngine::playsRemainingAfterUse($strike, 0, true));
        $this->assertSame(0, HNS_FreeActionEngine::playsRemainingAfterUse($strike, 1, false));
    }

    public function testCooldownStartsOnlyAfterLastPlay(): void
    {
        $cooldown = 2;
        $this->assertSame(0, HNS_FreeActionEngine::cooldownAfterUse($cooldown, $playsRemainingAfterUse = 1));
        $this->assertSame(2, HNS_FreeActionEngine::cooldownAfterUse($cooldown, $playsRemainingAfterUse = 0));
    }

    public function testReinforcedDashAttackRankThreeCanResolveSixTimes(): void
    {
        $dashAttackRankThree = ['effect' => 'dash_attack', 'plays' => 3];
        $playsRemaining = HNS_FreeActionEngine::playsRemainingAfterUse($dashAttackRankThree, 0, true);
        $engine = new HNS_FreeActionEngine($this->dashAttackEvents(), []);
        $resolved = 1;

        while ($playsRemaining > 0) {
            $this->assertTrue($engine->canUseFreeAction(
                'dash_attack_3',
                [HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK],
                $cooldown = 2,
                $playsRemaining
            ));
            $result = $engine->useFreeAction(
                'dash_attack_3',
                [HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK],
                $currentCooldown = 2,
                $nextCooldown = 2,
                $this->dashAttackEvents(),
                $playsRemaining
            );
            $engine = new HNS_FreeActionEngine($result['active_event_chain'], $result['used_action_keys']);
            $playsRemaining = HNS_FreeActionEngine::playsRemainingAfterUse($dashAttackRankThree, $playsRemaining, false);
            $resolved++;
        }

        $this->assertSame(6, $resolved);
        $this->assertFalse($engine->canUseFreeAction(
            'dash_attack_3',
            [HNS_FreeActionEngine::EVENT_AFTER_DASH_ATTACK],
            $cooldown = 2,
            $playsRemaining
        ));
    }
}
