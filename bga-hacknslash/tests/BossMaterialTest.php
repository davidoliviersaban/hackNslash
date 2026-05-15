<?php

use PHPUnit\Framework\TestCase;

final class BossMaterialTest extends TestCase
{
    public function testSlasherPhaseOneHasConfiguredStats(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';

        $phase = $bosses['slasher']['phases'][1];

        $this->assertSame(8, $phase['health']);
        $this->assertSame(2, $phase['move']);
        $this->assertSame('orthogonal', $phase['move_metric']);
        $this->assertSame(1, $phase['range']);
        $this->assertSame('chebyshev', $phase['range_metric']);
        $this->assertSame(2, $phase['damage']);
    }

    public function testSlasherPhaseTwoSpawnsTwoGoblinOrSlimeMinions(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';

        $phase = $bosses['slasher']['phases'][2];

        $this->assertSame(8, $phase['health']);
        $this->assertSame(['type' => 'spawn_minions', 'count' => 2, 'monster_ids' => [1, 2]], $phase['pre_actions'][0]);
    }

    public function testSlasherPhaseThreeGrantsShieldThenSpawnsMinions(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';

        $phase = $bosses['slasher']['phases'][3];

        $this->assertSame(8, $phase['health']);
        $this->assertSame(['type' => 'grant_shield'], $phase['pre_actions'][0]);
        $this->assertSame(['type' => 'spawn_minions', 'count' => 2, 'monster_ids' => [1, 2]], $phase['pre_actions'][1]);
    }

    public function testStrikerBossIsDeclared(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';

        $this->assertArrayHasKey('striker', $bosses);
    }

    public function testStrikerUsesMoveAreaAttackAndChargePhases(): void
    {
        include dirname(__DIR__) . '/modules/material/bosses.inc.php';

        $phaseOne = $bosses['striker']['phases'][1];
        $phaseTwo = $bosses['striker']['phases'][2];
        $phaseThree = $bosses['striker']['phases'][3];

        $this->assertSame(10, $phaseOne['health']);
        $this->assertSame('move_area_attack', $phaseOne['effect']);
        $this->assertSame(1, $phaseOne['move']);
        $this->assertSame('chebyshev', $phaseOne['move_metric']);
        $this->assertSame([0, 1], $phaseOne['area']);
        $this->assertSame('chebyshev', $phaseOne['area_metric']);
        $this->assertSame(2, $phaseOne['damage']);
        $this->assertSame(['type' => 'charge', 'damage' => 1, 'range' => 1, 'range_metric' => 'orthogonal'], $phaseTwo['pre_actions'][0]);
        $this->assertSame(['type' => 'grant_shield'], $phaseThree['pre_actions'][0]);
        $this->assertSame(['type' => 'charge', 'damage' => 1, 'range' => 1, 'range_metric' => 'orthogonal'], $phaseThree['pre_actions'][1]);
    }
}
