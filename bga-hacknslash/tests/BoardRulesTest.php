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
            6 => ['id' => 6, 'x' => 5, 'y' => 0, 'type' => 'entry'],
            7 => ['id' => 7, 'x' => 6, 'y' => 0, 'type' => 'exit'],
        ];

        $this->assertTrue(HNS_BoardRules::isTileWalkable($tiles[1]));
        $this->assertTrue(HNS_BoardRules::isTileWalkable($tiles[2]));
        $this->assertFalse(HNS_BoardRules::isTileWalkable($tiles[3]));
        $this->assertFalse(HNS_BoardRules::isTileWalkable($tiles[4]));
        $this->assertFalse(HNS_BoardRules::isTileWalkable($tiles[5]));
        $this->assertFalse(HNS_BoardRules::isTileWalkable($tiles[6]));
        $this->assertFalse(HNS_BoardRules::isTileWalkable($tiles[7]));
    }

    public function testOccupiedTilesCannotBeEnteredExceptByTheirCurrentOccupant(): void
    {
        $entities = [
            10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 2, 'state' => 'active'],
            11 => ['id' => 11, 'type' => 'hero', 'tile_id' => 3, 'state' => 'dead'],
        ];

        $this->assertFalse(HNS_BoardRules::isTileAvailable(2, $entities));
        $this->assertTrue(HNS_BoardRules::isTileAvailable(2, $entities, 10));
        $this->assertTrue(HNS_BoardRules::isTileAvailable(3, $entities));
    }

    public function testMonsterTileCanContainUpToTwoSmallMonsters(): void
    {
        $entities = [
            20 => ['id' => 20, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'state' => 'active'],
            21 => ['id' => 21, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 3, 'state' => 'active'],
        ];

        $this->assertTrue(HNS_BoardRules::canEnterTile(2, $entities, $entities[21], 21));

        $entities[22] = ['id' => 22, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'state' => 'active'];
        $entities[23] = ['id' => 23, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 4, 'state' => 'active'];

        $this->assertFalse(HNS_BoardRules::canEnterTile(2, $entities, $entities[23], 23));
    }

    public function testBigMonstersCannotShareWithAnyOtherMonster(): void
    {
        $entities = [
            20 => ['id' => 20, 'type' => 'monster', 'monster_size' => 'big', 'tile_id' => 2, 'state' => 'active'],
            21 => ['id' => 21, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 3, 'state' => 'active'],
            22 => ['id' => 22, 'type' => 'monster', 'monster_size' => 'big', 'tile_id' => 4, 'state' => 'active'],
        ];

        $this->assertFalse(HNS_BoardRules::canEnterTile(2, $entities, $entities[21], 21));
        $this->assertFalse(HNS_BoardRules::canEnterTile(3, $entities, $entities[22], 22));
    }

    public function testHeroesCannotEnterMonsterOccupiedTiles(): void
    {
        $entities = [
            10 => ['id' => 10, 'type' => 'hero', 'tile_id' => 1, 'state' => 'active'],
            20 => ['id' => 20, 'type' => 'monster', 'monster_size' => 'small', 'tile_id' => 2, 'state' => 'active'],
        ];

        $this->assertFalse(HNS_BoardRules::canEnterTile(2, $entities, $entities[10], 10));
    }
}
