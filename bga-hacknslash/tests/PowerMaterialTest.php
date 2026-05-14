<?php

use PHPUnit\Framework\TestCase;

final class PowerMaterialTest extends TestCase
{
    private const DASH_FREE_TRIGGERS = ['afterCardPlayed'];

    public function testFirstPlayableScopeDefinesFourFixedPowers(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame(['attack', 'strike', 'dash_1', 'dash_2', 'dash_3', 'dash_attack_1', 'dash_attack_2', 'dash_attack_3', 'fireball_1', 'fireball_2', 'fireball_3', 'grab_1', 'grab_2', 'grab_3', 'heal_1', 'heal_2', 'heal_3', 'jump_1', 'jump_2', 'jump_3', 'leech_1', 'leech_2', 'leech_3', 'vortex_1', 'vortex_2', 'vortex_3', 'whirlwind_1', 'whirlwind_2', 'whirlwind_3', 'power_strike_1', 'power_strike_2', 'power_strike_3', 'quick_strike_1', 'quick_strike_2', 'quick_strike_3', 'quick_shot_1', 'quick_shot_2', 'quick_shot_3', 'point_blank_1', 'point_blank_2', 'point_blank_3'], array_keys($bonus_cards));
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

        $vortex = $bonus_cards['vortex_1'];

        $this->assertSame('Vortex', $vortex['name']);
        $this->assertSame('pull', $vortex['effect']);
        $this->assertSame([0, 2], $vortex['range']);
        $this->assertSame('chebyshev', $vortex['range_metric']);
        $this->assertSame([0, 1], $vortex['area']);
        $this->assertSame('orthogonal', $vortex['area_metric']);
        $this->assertSame(1, $vortex['pull_distance']);
        $this->assertSame(2, $vortex['cooldown']);
        $this->assertSame([], $vortex['free_triggers']);
        $this->assertSame('vortex_2', $vortex['upgrades_to']);
        $this->assertSame(2, $bonus_cards['vortex_2']['rank']);
        $this->assertSame([0, 1], $bonus_cards['vortex_2']['area']);
        $this->assertSame('chebyshev', $bonus_cards['vortex_2']['area_metric']);
        $this->assertSame('vortex_3', $bonus_cards['vortex_2']['upgrades_to']);
        $this->assertSame(3, $bonus_cards['vortex_3']['rank']);
        $this->assertSame([0, 2], $bonus_cards['vortex_3']['area']);
        $this->assertSame('orthogonal', $bonus_cards['vortex_3']['area_metric']);
        $this->assertNull($bonus_cards['vortex_3']['upgrades_to']);
    }

    public function testNewPowerDefinitions(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame('Dash Attack', $bonus_cards['dash_attack_1']['name']);
        $this->assertSame('dash_attack', $bonus_cards['dash_attack_1']['effect']);
        $this->assertSame([1, 1], $bonus_cards['dash_attack_1']['range']);
        $this->assertSame(1, $bonus_cards['dash_attack_1']['targets']);
        $this->assertSame(1, $bonus_cards['dash_attack_1']['damage']);
        $this->assertSame([1, 2], $bonus_cards['dash_attack_2']['range']);
        $this->assertSame(1, $bonus_cards['dash_attack_2']['targets']);
        $this->assertSame(2, $bonus_cards['dash_attack_2']['plays']);
        $this->assertSame(['afterDashAttack'], $bonus_cards['dash_attack_2']['free_triggers']);
        $this->assertSame(1, $bonus_cards['dash_attack_2']['damage']);
        $this->assertSame(1, $bonus_cards['dash_attack_3']['targets']);
        $this->assertSame(2, $bonus_cards['dash_attack_3']['plays']);
        $this->assertSame(2, $bonus_cards['dash_attack_3']['damage']);

        $this->assertSame('area_attack', $bonus_cards['fireball_1']['effect']);
        $this->assertSame('orthogonal', $bonus_cards['fireball_1']['area_metric']);
        $this->assertTrue($bonus_cards['fireball_1']['ignores_shield']);

        $this->assertSame('Projection', $bonus_cards['grab_1']['name']);
        $this->assertSame('attack', $bonus_cards['grab_1']['effect']);
        $this->assertSame(0, $bonus_cards['grab_1']['damage']);
        $this->assertSame(3, $bonus_cards['grab_1']['push_distance']);
        $this->assertSame('orthogonal', $bonus_cards['grab_1']['range_metric']);
        $this->assertSame('chebyshev', $bonus_cards['grab_2']['range_metric']);
        $this->assertSame(1, $bonus_cards['grab_3']['damage']);
        $this->assertSame(['afterDash'], $bonus_cards['grab_1']['free_triggers']);
        $this->assertSame(['afterDash'], $bonus_cards['grab_2']['free_triggers']);
        $this->assertSame(['afterDash'], $bonus_cards['grab_3']['free_triggers']);

        $this->assertSame('heal', $bonus_cards['heal_1']['effect']);
        $this->assertSame(1, $bonus_cards['heal_1']['heal']);
        $this->assertSame(2, $bonus_cards['heal_2']['heal']);
        $this->assertSame(4, $bonus_cards['heal_3']['heal']);
        $this->assertSame([0, 2], $bonus_cards['heal_1']['range']);
        $this->assertSame(['afterKill'], $bonus_cards['heal_1']['free_triggers']);
        $this->assertSame(['afterKill'], $bonus_cards['heal_2']['free_triggers']);
        $this->assertSame(['afterKill'], $bonus_cards['heal_3']['free_triggers']);

        $this->assertSame('jump', $bonus_cards['jump_1']['effect']);
        $this->assertSame([1, 2], $bonus_cards['jump_1']['distance']);
        $this->assertSame([1, 2], $bonus_cards['jump_2']['distance']);
        $this->assertSame([1, 3], $bonus_cards['jump_3']['distance']);
        $this->assertSame(0, $bonus_cards['jump_1']['push_distance']);
        $this->assertSame(1, $bonus_cards['jump_2']['push_distance']);
        $this->assertSame(1, $bonus_cards['jump_3']['damage']);
        $this->assertSame(['afterMove'], $bonus_cards['jump_1']['free_triggers']);
        $this->assertSame(['afterMove'], $bonus_cards['jump_2']['free_triggers']);
        $this->assertSame(['afterMove'], $bonus_cards['jump_3']['free_triggers']);

        $this->assertSame('attack', $bonus_cards['leech_1']['effect']);
        $this->assertSame('chebyshev', $bonus_cards['leech_1']['range_metric']);
        $this->assertSame('chebyshev', $bonus_cards['leech_2']['range_metric']);
        $this->assertSame('chebyshev', $bonus_cards['leech_3']['range_metric']);
        $this->assertTrue($bonus_cards['leech_1']['ignores_shield']);
        $this->assertSame(1, $bonus_cards['leech_1']['heal_on_damage']);
        $this->assertSame(1, $bonus_cards['leech_1']['damage']);
        $this->assertSame(2, $bonus_cards['leech_2']['damage']);
        $this->assertSame(3, $bonus_cards['leech_3']['damage']);

        $this->assertSame('move_area_attack', $bonus_cards['whirlwind_1']['effect']);
        $this->assertSame(1, $bonus_cards['whirlwind_1']['damage']);
        $this->assertSame(1, $bonus_cards['whirlwind_2']['damage']);
        $this->assertSame(2, $bonus_cards['whirlwind_3']['damage']);
        $this->assertSame([0, 0], $bonus_cards['whirlwind_1']['distance']);
        $this->assertSame([0, 1], $bonus_cards['whirlwind_2']['distance']);
        $this->assertSame([0, 1], $bonus_cards['whirlwind_1']['area']);
        $this->assertSame('chebyshev', $bonus_cards['whirlwind_1']['area_metric']);
        $this->assertSame(['afterKill'], $bonus_cards['whirlwind_1']['free_triggers']);
        $this->assertSame(['afterKill'], $bonus_cards['whirlwind_2']['free_triggers']);
        $this->assertSame(['afterKill'], $bonus_cards['whirlwind_3']['free_triggers']);
    }

    public function testQuickStrikePowerDefinition(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame('Quick Strike', $bonus_cards['quick_strike_1']['name']);
        $this->assertSame('attack', $bonus_cards['quick_strike_1']['effect']);
        $this->assertSame(2, $bonus_cards['quick_strike_1']['targets']);
        $this->assertSame(1, $bonus_cards['quick_strike_1']['damage']);
        $this->assertSame([0, 1], $bonus_cards['quick_strike_1']['range']);
        $this->assertSame('chebyshev', $bonus_cards['quick_strike_1']['range_metric']);
        $this->assertSame(2, $bonus_cards['quick_strike_1']['cooldown']);
        $this->assertSame(['afterMove'], $bonus_cards['quick_strike_1']['free_triggers']);
        $this->assertSame('quick_strike_2', $bonus_cards['quick_strike_1']['upgrades_to']);
        $this->assertSame(3, $bonus_cards['quick_strike_2']['targets']);
        $this->assertSame(1, $bonus_cards['quick_strike_2']['damage']);
        $this->assertSame(2, $bonus_cards['quick_strike_2']['cooldown']);
        $this->assertSame(['afterMove'], $bonus_cards['quick_strike_2']['free_triggers']);
        $this->assertSame('quick_strike_3', $bonus_cards['quick_strike_2']['upgrades_to']);
        $this->assertSame(4, $bonus_cards['quick_strike_3']['targets']);
        $this->assertSame(1, $bonus_cards['quick_strike_3']['damage']);
        $this->assertSame(2, $bonus_cards['quick_strike_3']['cooldown']);
        $this->assertSame(['afterMove'], $bonus_cards['quick_strike_3']['free_triggers']);
        $this->assertNull($bonus_cards['quick_strike_3']['upgrades_to']);
    }

    public function testPowerStrikePowerDefinition(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame('Power Strike', $bonus_cards['power_strike_1']['name']);
        $this->assertSame('attack', $bonus_cards['power_strike_1']['effect']);
        $this->assertSame(1, $bonus_cards['power_strike_1']['targets']);
        $this->assertSame(3, $bonus_cards['power_strike_1']['damage']);
        $this->assertSame([0, 1], $bonus_cards['power_strike_1']['range']);
        $this->assertSame('orthogonal', $bonus_cards['power_strike_1']['range_metric']);
        $this->assertSame(0, $bonus_cards['power_strike_1']['push_distance']);
        $this->assertSame(['shieldBroken'], $bonus_cards['power_strike_1']['free_triggers']);
        $this->assertSame('power_strike_2', $bonus_cards['power_strike_1']['upgrades_to']);
        $this->assertSame(3, $bonus_cards['power_strike_2']['damage']);
        $this->assertSame(1, $bonus_cards['power_strike_2']['push_distance']);
        $this->assertSame(['shieldBroken'], $bonus_cards['power_strike_2']['free_triggers']);
        $this->assertSame('power_strike_3', $bonus_cards['power_strike_2']['upgrades_to']);
        $this->assertSame(4, $bonus_cards['power_strike_3']['damage']);
        $this->assertSame(1, $bonus_cards['power_strike_3']['push_distance']);
        $this->assertSame(['shieldBroken'], $bonus_cards['power_strike_3']['free_triggers']);
        $this->assertNull($bonus_cards['power_strike_3']['upgrades_to']);
    }

    public function testQuickShotPowerDefinition(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame('Quick Shot', $bonus_cards['quick_shot_1']['name']);
        $this->assertSame('attack', $bonus_cards['quick_shot_1']['effect']);
        $this->assertSame(2, $bonus_cards['quick_shot_1']['targets']);
        $this->assertSame(1, $bonus_cards['quick_shot_1']['damage']);
        $this->assertSame([2, 3], $bonus_cards['quick_shot_1']['range']);
        $this->assertSame('chebyshev', $bonus_cards['quick_shot_1']['range_metric']);
        $this->assertSame(2, $bonus_cards['quick_shot_1']['cooldown']);
        $this->assertSame(['afterMove'], $bonus_cards['quick_shot_1']['free_triggers']);
        $this->assertSame('quick_shot_2', $bonus_cards['quick_shot_1']['upgrades_to']);
        $this->assertSame(3, $bonus_cards['quick_shot_2']['targets']);
        $this->assertSame(1, $bonus_cards['quick_shot_2']['damage']);
        $this->assertSame(2, $bonus_cards['quick_shot_2']['cooldown']);
        $this->assertSame(['afterMove'], $bonus_cards['quick_shot_2']['free_triggers']);
        $this->assertSame('quick_shot_3', $bonus_cards['quick_shot_2']['upgrades_to']);
        $this->assertSame(4, $bonus_cards['quick_shot_3']['targets']);
        $this->assertSame([2, 4], $bonus_cards['quick_shot_3']['range']);
        $this->assertSame(2, $bonus_cards['quick_shot_3']['cooldown']);
        $this->assertSame(['afterMove'], $bonus_cards['quick_shot_3']['free_triggers']);
        $this->assertNull($bonus_cards['quick_shot_3']['upgrades_to']);
    }

    public function testPointBlankPowerDefinition(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $this->assertSame('Point Blank', $bonus_cards['point_blank_1']['name']);
        $this->assertSame('attack', $bonus_cards['point_blank_1']['effect']);
        $this->assertSame(1, $bonus_cards['point_blank_1']['targets']);
        $this->assertSame(1, $bonus_cards['point_blank_1']['damage']);
        $this->assertSame([1, 2], $bonus_cards['point_blank_1']['range']);
        $this->assertSame('chebyshev', $bonus_cards['point_blank_1']['range_metric']);
        $this->assertSame(1, $bonus_cards['point_blank_1']['push_distance']);
        $this->assertSame(['afterPushOrPull'], $bonus_cards['point_blank_1']['free_triggers']);
        $this->assertSame('point_blank_2', $bonus_cards['point_blank_1']['upgrades_to']);
        $this->assertSame(2, $bonus_cards['point_blank_2']['damage']);
        $this->assertSame(1, $bonus_cards['point_blank_2']['push_distance']);
        $this->assertSame(['afterPushOrPull'], $bonus_cards['point_blank_2']['free_triggers']);
        $this->assertSame('point_blank_3', $bonus_cards['point_blank_2']['upgrades_to']);
        $this->assertSame(3, $bonus_cards['point_blank_3']['damage']);
        $this->assertSame(2, $bonus_cards['point_blank_3']['push_distance']);
        $this->assertSame(['afterPushOrPull'], $bonus_cards['point_blank_3']['free_triggers']);
        $this->assertNull($bonus_cards['point_blank_3']['upgrades_to']);
    }
}
