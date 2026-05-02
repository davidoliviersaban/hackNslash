<?php

use PHPUnit\Framework\TestCase;

final class PowerMaterialTest extends TestCase
{
    public function testFirstPlayableScopeDefinesThreeFixedPowers(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame(['attack', 'dash', 'vortex'], array_keys($bonus_cards));
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
        $this->assertSame(0, $attack['cooldown']);
        $this->assertSame([], $attack['free_triggers']);
    }

    public function testDashPowerDefinition(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $dash = $bonus_cards['dash'];

        $this->assertSame('Dash', $dash['name']);
        $this->assertSame('dash', $dash['effect']);
        $this->assertSame([1, 2], $dash['distance']);
        $this->assertSame(1, $dash['cooldown']);
        $this->assertSame(['afterCardPlayed'], $dash['free_triggers']);
    }

    public function testVortexPowerDefinition(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $vortex = $bonus_cards['vortex'];

        $this->assertSame('Vortex', $vortex['name']);
        $this->assertSame('pull', $vortex['effect']);
        $this->assertSame(2, $vortex['targets']);
        $this->assertSame([1, 2], $vortex['range']);
        $this->assertSame(1, $vortex['pull_distance']);
        $this->assertSame(2, $vortex['cooldown']);
        $this->assertSame([], $vortex['free_triggers']);
    }
}
