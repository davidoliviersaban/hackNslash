<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_LevelGenerator.php';

final class LevelGeneratorTest extends TestCase
{
    public function testGeneratesDeterministicFiveByFiveLevelWithSevenMonsterStarts(): void
    {
        $level = HNS_LevelGenerator::generate(5, 1234);
        $sameLevel = HNS_LevelGenerator::generate(5, 1234);

        $this->assertSame($level, $sameLevel);
        $this->assertSame(5, $level['size']);
        $this->assertSame(7, $level['grid_size']);
        $this->assertCount(49, $level['terrain']);
        $this->assertCount(2, $level['player_starts']);
        $this->assertCount(7, $level['monster_starts']);
    }

    public function testEntryAndExitAreOnOppositeHorizontalBorders(): void
    {
        $level = HNS_LevelGenerator::generate(5, 42);

        $this->assertContains($level['entry']['y'], [0, $level['grid_size'] - 1]);
        $this->assertContains($level['exit']['y'], [0, $level['grid_size'] - 1]);
        $this->assertNotSame($level['entry']['y'], $level['exit']['y']);
    }

    public function testMonsterStartsNeverUseEntryOrExit(): void
    {
        $level = HNS_LevelGenerator::generate(5, 42);
        $blocked = [
            $level['entry']['x'] . ',' . $level['entry']['y'] => true,
            $level['exit']['x'] . ',' . $level['exit']['y'] => true,
        ];

        foreach ($level['monster_starts'] as $start) {
            $this->assertArrayNotHasKey($start['x'] . ',' . $start['y'], $blocked);
        }
    }

    public function testGeneratedLevelContainsExpectedTerrainCountsForFiveByFive(): void
    {
        $level = HNS_LevelGenerator::generate(5, 99);
        $counts = array_count_values(array_column($level['terrain'], 'terrain'));

        $this->assertSame(0, $counts['wall'] - (($level['grid_size'] * 4) - 4 - 2));
        $this->assertSame(3, $counts['pillar']);
        $this->assertSame(2, $counts['spikes']);
        $this->assertSame(1, $counts['hole']);
        $this->assertSame(1, $counts['entry']);
        $this->assertSame(1, $counts['exit']);
    }

    public function testWallsOnlyAppearOnOuterBorder(): void
    {
        $level = HNS_LevelGenerator::generate(7, 1234);
        $max = $level['grid_size'] - 1;

        foreach ($level['terrain'] as $cell) {
            if ($cell['terrain'] !== 'wall') {
                continue;
            }

            $this->assertTrue($cell['x'] === 0 || $cell['x'] === $max || $cell['y'] === 0 || $cell['y'] === $max);
        }
    }

    public function testRejectsUnsupportedLevelSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Level size must be 5 or 7.');

        HNS_LevelGenerator::generate(6, 1);
    }
}
