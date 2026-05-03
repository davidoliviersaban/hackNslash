<?php

use PHPUnit\Framework\TestCase;

final class PowerMaterialTest extends TestCase
{
    private const DASH_FREE_TRIGGERS = ['afterCardPlayed'];

    public function testFirstPlayableScopeDefinesFourFixedPowers(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame(['attack', 'strike', 'dash_1', 'dash_2', 'dash_3', 'vortex', 'vortex_2', 'vortex_3', 'quick-strike_1', 'quick-strike_2', 'quick-strike_3', 'quick-shot_1', 'quick-shot_2', 'quick-shot_3'], array_keys($bonus_cards));
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
        $this->assertSame(self::DASH_FREE_TRIGGERS, $dash['free_triggers']);
    }

    public function testDashCooldownImprovesAtRankThree(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame(2, $bonus_cards['dash_1']['cooldown']);
        $this->assertSame(2, $bonus_cards['dash_2']['cooldown']);
        $this->assertSame(1, $bonus_cards['dash_3']['cooldown']);
        $this->assertSame(self::DASH_FREE_TRIGGERS, $bonus_cards['dash_1']['free_triggers']);
        $this->assertSame(self::DASH_FREE_TRIGGERS, $bonus_cards['dash_2']['free_triggers']);
        $this->assertSame(self::DASH_FREE_TRIGGERS, $bonus_cards['dash_3']['free_triggers']);
    }

    public function testDashRankTwoCanMoveUpToThreeTiles(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame([1, 2], $bonus_cards['dash_1']['distance']);
        $this->assertSame([1, 3], $bonus_cards['dash_2']['distance']);
        $this->assertSame([1, 3], $bonus_cards['dash_3']['distance']);
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
        $this->assertSame('vortex_3', $bonus_cards['vortex_2']['upgrades_to']);
        $this->assertSame(3, $bonus_cards['vortex_3']['rank']);
        $this->assertSame(4, $bonus_cards['vortex_3']['targets']);
        $this->assertNull($bonus_cards['vortex_3']['upgrades_to']);
    }

    public function testQuickStrikePowerDefinition(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame('Quick Strike', $bonus_cards['quick-strike_1']['name']);
        $this->assertSame('attack', $bonus_cards['quick-strike_1']['effect']);
        $this->assertSame(2, $bonus_cards['quick-strike_1']['targets']);
        $this->assertSame(1, $bonus_cards['quick-strike_1']['damage']);
        $this->assertSame([0, 1], $bonus_cards['quick-strike_1']['range']);
        $this->assertSame('chebyshev', $bonus_cards['quick-strike_1']['range_metric']);
        $this->assertSame(2, $bonus_cards['quick-strike_1']['cooldown']);
        $this->assertSame(['afterMove'], $bonus_cards['quick-strike_1']['free_triggers']);
        $this->assertSame('quick-strike_2', $bonus_cards['quick-strike_1']['upgrades_to']);
        $this->assertSame(3, $bonus_cards['quick-strike_2']['targets']);
        $this->assertSame(1, $bonus_cards['quick-strike_2']['damage']);
        $this->assertSame(2, $bonus_cards['quick-strike_2']['cooldown']);
        $this->assertSame(['afterMove'], $bonus_cards['quick-strike_2']['free_triggers']);
        $this->assertSame('quick-strike_3', $bonus_cards['quick-strike_2']['upgrades_to']);
        $this->assertSame(4, $bonus_cards['quick-strike_3']['targets']);
        $this->assertSame(1, $bonus_cards['quick-strike_3']['damage']);
        $this->assertSame(2, $bonus_cards['quick-strike_3']['cooldown']);
        $this->assertSame(['afterMove'], $bonus_cards['quick-strike_3']['free_triggers']);
        $this->assertNull($bonus_cards['quick-strike_3']['upgrades_to']);
    }

    public function testQuickShotPowerDefinition(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame('Quick Shot', $bonus_cards['quick-shot_1']['name']);
        $this->assertSame('attack', $bonus_cards['quick-shot_1']['effect']);
        $this->assertSame(2, $bonus_cards['quick-shot_1']['targets']);
        $this->assertSame(1, $bonus_cards['quick-shot_1']['damage']);
        $this->assertSame([2, 3], $bonus_cards['quick-shot_1']['range']);
        $this->assertSame('chebyshev', $bonus_cards['quick-shot_1']['range_metric']);
        $this->assertSame(2, $bonus_cards['quick-shot_1']['cooldown']);
        $this->assertSame(['afterMove'], $bonus_cards['quick-shot_1']['free_triggers']);
        $this->assertSame('quick-shot_2', $bonus_cards['quick-shot_1']['upgrades_to']);
        $this->assertSame(3, $bonus_cards['quick-shot_2']['targets']);
        $this->assertSame(1, $bonus_cards['quick-shot_2']['damage']);
        $this->assertSame(2, $bonus_cards['quick-shot_2']['cooldown']);
        $this->assertSame(['afterMove'], $bonus_cards['quick-shot_2']['free_triggers']);
        $this->assertSame('quick-shot_3', $bonus_cards['quick-shot_2']['upgrades_to']);
        $this->assertSame(4, $bonus_cards['quick-shot_3']['targets']);
        $this->assertSame([2, 4], $bonus_cards['quick-shot_3']['range']);
        $this->assertSame(2, $bonus_cards['quick-shot_3']['cooldown']);
        $this->assertSame(['afterMove'], $bonus_cards['quick-shot_3']['free_triggers']);
        $this->assertNull($bonus_cards['quick-shot_3']['upgrades_to']);
    }
}
