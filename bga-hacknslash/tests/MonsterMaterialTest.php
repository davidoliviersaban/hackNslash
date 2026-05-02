<?php

use PHPUnit\Framework\TestCase;

final class MonsterMaterialTest extends TestCase
{
    public function testGoblinsArriveByTwoWithOneHealthAndOrthogonalAi(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $goblins = $monsters[1];

        $this->assertSame('Goblins', $goblins['name']);
        $this->assertSame(1, $goblins['health']);
        $this->assertSame(2, $goblins['spawn_count']);
        $this->assertSame(1, $goblins['damage']);
        $this->assertSame(1, $goblins['range']);
        $this->assertSame('orthogonal', $goblins['range_metric']);
        $this->assertSame(1, $goblins['move']);
        $this->assertSame('orthogonal', $goblins['move_metric']);
        $this->assertSame('small', $goblins['size']);
        $this->assertTrue($goblins['can_attack']);
        $this->assertTrue($goblins['can_move']);
        $this->assertFalse($goblins['can_attack_and_move']);
    }

    public function testSlimesArriveByTwoWithTwoHealthDiagonalMoveAndStickAttack(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $slimes = $monsters[2];

        $this->assertSame('Slimes', $slimes['name']);
        $this->assertSame(2, $slimes['health']);
        $this->assertSame(2, $slimes['spawn_count']);
        $this->assertSame(2, $slimes['move']);
        $this->assertSame('chebyshev', $slimes['move_metric']);
        $this->assertSame(1, $slimes['range']);
        $this->assertSame('orthogonal', $slimes['range_metric']);
        $this->assertSame(0, $slimes['damage']);
        $this->assertSame('stick', $slimes['effect']);
    }

    public function testEvilEyeDoesNotMoveAndShootsAtRangeThreeWithDiagonals(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $evilEye = $monsters[3];

        $this->assertSame('Evil Eye', $evilEye['name']);
        $this->assertSame(0, $evilEye['move']);
        $this->assertFalse($evilEye['can_move']);
        $this->assertSame(3, $evilEye['range']);
        $this->assertSame('chebyshev', $evilEye['range_metric']);
        $this->assertSame(1, $evilEye['damage']);
        $this->assertTrue($evilEye['can_attack']);
    }

    public function testKamikazeHasOneHealthMovesDiagonallyAndExplodesOrthogonally(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $kamikaze = $monsters[4];

        $this->assertSame('Kamikaze', $kamikaze['name']);
        $this->assertSame(1, $kamikaze['health']);
        $this->assertSame(1, $kamikaze['move']);
        $this->assertSame('chebyshev', $kamikaze['move_metric']);
        $this->assertSame(1, $kamikaze['range']);
        $this->assertSame('orthogonal', $kamikaze['range_metric']);
        $this->assertSame(2, $kamikaze['damage']);
        $this->assertSame('explode', $kamikaze['effect']);
        $this->assertSame('explode', $kamikaze['on_death']);
    }

    public function testWizardHasTwoHealthAndAttacksOrthogonallyAtRangeOneToFour(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $wizard = $monsters[5];

        $this->assertSame('Wizard', $wizard['name']);
        $this->assertSame(2, $wizard['health']);
        $this->assertSame(4, $wizard['range']);
        $this->assertSame(1, $wizard['min_range']);
        $this->assertSame('orthogonal', $wizard['range_metric']);
        $this->assertSame(1, $wizard['damage']);
        $this->assertSame(1, $wizard['move']);
        $this->assertSame('orthogonal', $wizard['move_metric']);
        $this->assertTrue($wizard['can_move']);
    }

    public function testBomberHasThreeHealthAndAttacksOrthogonallyAtRangeTwoToThree(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $bomber = $monsters[6];

        $this->assertSame('Bomber', $bomber['name']);
        $this->assertSame(3, $bomber['health']);
        $this->assertSame(1, $bomber['move']);
        $this->assertSame('orthogonal', $bomber['move_metric']);
        $this->assertSame(3, $bomber['range']);
        $this->assertSame(2, $bomber['min_range']);
        $this->assertSame('orthogonal', $bomber['range_metric']);
        $this->assertSame(1, $bomber['damage']);
    }

    public function testOrcIsBigWithFourHealthOrthogonalMoveAndFrontArcAttack(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $orc = $monsters[7];

        $this->assertSame('Orc', $orc['name']);
        $this->assertSame(4, $orc['health']);
        $this->assertSame('big', $orc['size']);
        $this->assertSame(1, $orc['move']);
        $this->assertSame('orthogonal', $orc['move_metric']);
        $this->assertSame('front_arc', $orc['range_metric']);
        $this->assertSame('front_arc_damage', $orc['effect']);
        $this->assertSame(2, $orc['damage']);
    }

    public function testPigRiderIsBigWithFourHealthOrthogonalChargeAttack(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $pigRider = $monsters[8];

        $this->assertSame('Pig Rider', $pigRider['name']);
        $this->assertSame(4, $pigRider['health']);
        $this->assertSame('big', $pigRider['size']);
        $this->assertSame(1, $pigRider['move']);
        $this->assertSame('orthogonal', $pigRider['move_metric']);
        $this->assertSame(1, $pigRider['range']);
        $this->assertSame('orthogonal', $pigRider['range_metric']);
        $this->assertSame(1, $pigRider['damage']);
        $this->assertSame('charge', $pigRider['effect']);
    }

    public function testWolfRiderIsBigWithThreeHealthSummonsGoblinAndFlees(): void
    {
        include dirname(__DIR__) . '/modules/material/monsters.inc.php';

        $wolfRider = $monsters[9];

        $this->assertSame('Wolf Rider', $wolfRider['name']);
        $this->assertSame(3, $wolfRider['health']);
        $this->assertSame('big', $wolfRider['size']);
        $this->assertSame(1, $wolfRider['move']);
        $this->assertSame('orthogonal', $wolfRider['move_metric']);
        $this->assertSame('summon_then_flee', $wolfRider['effect']);
        $this->assertSame(1, $wolfRider['summon_monster_id']);
        $this->assertSame(1, $wolfRider['summon_count']);
    }
}
