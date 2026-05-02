<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_BoardRules.php';

final class BoardRulesTest extends TestCase
{
    public function testOrthogonalDistanceIsUsedForMovementAndRange(): void
    {
        $this->assertSame(0, HNS_BoardRules::distance(['x' => 2, 'y' => 2], ['x' => 2, 'y' => 2]));
        $this->assertSame(1, HNS_BoardRules::distance(['x' => 2, 'y' => 2], ['x' => 3, 'y' => 2]));
        $this->assertSame(3, HNS_BoardRules::distance(['x' => 2, 'y' => 2], ['x' => 4, 'y' => 3]));
    }

    public function testFreeMoveMustBeExactlyOneOrthogonalStep(): void
    {
        $this->assertTrue(HNS_BoardRules::isExactStep(['x' => 1, 'y' => 1], ['x' => 1, 'y' => 2], 1));
        $this->assertFalse(HNS_BoardRules::isExactStep(['x' => 1, 'y' => 1], ['x' => 1, 'y' => 1], 1));
        $this->assertFalse(HNS_BoardRules::isExactStep(['x' => 1, 'y' => 1], ['x' => 2, 'y' => 2], 1));
    }

    public function testRangeAcceptsInclusiveMinAndMaxDistance(): void
    {
        $this->assertTrue(HNS_BoardRules::isInRange(['x' => 0, 'y' => 0], ['x' => 0, 'y' => 1], [0, 1]));
        $this->assertTrue(HNS_BoardRules::isInRange(['x' => 0, 'y' => 0], ['x' => 0, 'y' => 0], [0, 1]));
        $this->assertFalse(HNS_BoardRules::isInRange(['x' => 0, 'y' => 0], ['x' => 2, 'y' => 0], [0, 1]));
    }

    public function testOnlyWalkableTilesCanBeEnteredByDefault(): void
    {
        $tiles = [
            1 => ['id' => 1, 'x' => 0, 'y' => 0, 'type' => 'floor'],
            2 => ['id' => 2, 'x' => 1, 'y' => 0, 'type' => 'spikes'],
            3 => ['id' => 3, 'x' => 2, 'y' => 0, 'type' => 'wall'],
            4 => ['id' => 4, 'x' => 3, 'y' => 0, 'type' => 'pillar'],
            5 => ['id' => 5, 'x' => 4, 'y' => 0, 'type' => 'hole'],
        ];

        $this->assertTrue(HNS_BoardRules::isTileWalkable($tiles[1]));
        $this->assertTrue(HNS_BoardRules::isTileWalkable($tiles[2]));
        $this->assertFalse(HNS_BoardRules::isTileWalkable($tiles[3]));
        $this->assertFalse(HNS_BoardRules::isTileWalkable($tiles[4]));
        $this->assertFalse(HNS_BoardRules::isTileWalkable($tiles[5]));
    }

    public function testOccupiedTilesCannotBeEnteredExceptByTheirCurrentOccupant(): void
    {
        $entities = [
            10 => ['id' => 10, 'tile_id' => 2, 'state' => 'active'],
            11 => ['id' => 11, 'tile_id' => 3, 'state' => 'dead'],
        ];

        $this->assertFalse(HNS_BoardRules::isTileAvailable(2, $entities));
        $this->assertTrue(HNS_BoardRules::isTileAvailable(2, $entities, 10));
        $this->assertTrue(HNS_BoardRules::isTileAvailable(3, $entities));
    }
}
