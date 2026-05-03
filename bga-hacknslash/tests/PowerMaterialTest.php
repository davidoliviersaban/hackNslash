<?php

use PHPUnit\Framework\TestCase;

final class PowerMaterialTest extends TestCase
{
    public function testFirstPlayableScopeDefinesFourFixedPowers(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame(['attack', 'strike', 'dash_1', 'dash_2', 'dash_3', 'vortex', 'vortex_2'], array_keys($bonus_cards));
    }

    public function testAttackPowerDefinition(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $attack = $bonus_cards['attack'];

        $this->assertSame('Attack', $attack['name']);
        $this->assertSame('attack', $attack['effect']);
        $this->assertSame(1, $attack['targets']);
        $this->assertSame(1, $attack['damage']);
        $this->assertSame([0, 1], $attack['range']);
        $this->assertSame('chebyshev', $attack['range_metric']);
        $this->assertSame(1, $attack['cooldown']);
        $this->assertSame([], $attack['free_triggers']);
    }

    public function testStrikePowerDefinition(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $strike = $bonus_cards['strike'];

        $this->assertSame('Strike', $strike['name']);
        $this->assertSame('attack', $strike['effect']);
        $this->assertSame(1, $strike['targets']);
        $this->assertSame(2, $strike['damage']);
        $this->assertSame([0, 1], $strike['range']);
        $this->assertSame('orthogonal', $strike['range_metric']);
        $this->assertSame(1, $strike['cooldown']);
        $this->assertSame([], $strike['free_triggers']);
    }

    public function testDashPowerDefinition(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $dash = $bonus_cards['dash_1'];

        $this->assertSame('Dash', $dash['name']);
        $this->assertSame('dash', $dash['effect']);
        $this->assertSame([1, 2], $dash['distance']);
        $this->assertSame('orthogonal', $dash['range_metric']);
        $this->assertSame(2, $dash['cooldown']);
        $this->assertSame(['afterCardPlayed'], $dash['free_triggers']);
    }

    public function testDashCooldownImprovesAtRankThree(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame(2, $bonus_cards['dash_1']['cooldown']);
        $this->assertSame(2, $bonus_cards['dash_2']['cooldown']);
        $this->assertSame(1, $bonus_cards['dash_3']['cooldown']);
    }

    public function testVortexPowerDefinition(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $vortex = $bonus_cards['vortex'];

        $this->assertSame('Vortex', $vortex['name']);
        $this->assertSame('pull', $vortex['effect']);
        $this->assertSame(2, $vortex['targets']);
        $this->assertSame([0, 2], $vortex['range']);
        $this->assertSame('chebyshev', $vortex['range_metric']);
        $this->assertSame([1, 1], $vortex['target_range_from_selected_tile']);
        $this->assertSame(1, $vortex['pull_distance']);
        $this->assertSame(2, $vortex['cooldown']);
        $this->assertSame([], $vortex['free_triggers']);
        $this->assertSame('vortex_2', $vortex['upgrades_to']);
        $this->assertSame(2, $bonus_cards['vortex_2']['rank']);
        $this->assertSame(3, $bonus_cards['vortex_2']['targets']);
    }
}
