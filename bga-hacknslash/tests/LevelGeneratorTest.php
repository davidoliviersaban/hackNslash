<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_SeededRandom.php';
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

    public function testGeneratedLevelsAreFullyReachableWithOrthogonalMovement(): void
    {
        foreach ([5, 7] as $size) {
            foreach ([1, 2, 3, 42, 99, 1234, 20260510] as $seed) {
                $level = HNS_LevelGenerator::generate($size, $seed);
                $terrain = $this->terrainByCoordinate($level);
                $reachable = $this->orthogonallyReachableWalkableCells($level, $level['player_starts'][0]);

                foreach ($terrain as $key => $type) {
                    if (!in_array($type, ['floor', 'spikes'], true)) {
                        continue;
                    }
                    $this->assertArrayHasKey($key, $reachable, "Walkable cell $key should be reachable orthogonally for size $size seed $seed.");
                }
            }
        }
    }

    public function testExitInteriorAnchorIsReachableWithOrthogonalMovement(): void
    {
        foreach ([5, 7] as $size) {
            foreach ([1, 42, 1234, 20260510] as $seed) {
                $level = HNS_LevelGenerator::generate($size, $seed);
                $exit = $level['exit'];
                $exitAnchor = [
                    'x' => $exit['x'],
                    'y' => $exit['y'] === 0 ? 1 : $level['grid_size'] - 2,
                ];
                $terrain = $this->terrainByCoordinate($level);
                $reachable = $this->orthogonallyReachableWalkableCells($level, $level['player_starts'][0]);
                $key = $exitAnchor['x'] . ',' . $exitAnchor['y'];

                $this->assertContains($terrain[$key] ?? null, ['floor', 'spikes']);
                $this->assertArrayHasKey($key, $reachable, "Exit anchor $key should be reachable orthogonally for size $size seed $seed.");
            }
        }
    }

    public function testRejectsUnsupportedLevelSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Level size must be 5 or 7.');

        HNS_LevelGenerator::generate(6, 1);
    }

    public function testBossLevelUsesCentralThreeByThreeFloorArea(): void
    {
        require_once dirname(__DIR__) . '/modules/material/constants.inc.php';
        require_once dirname(__DIR__) . '/modules/HNS_BoardRules.php';
        require_once dirname(__DIR__) . '/modules/HNS_RoomSlotPattern.php';
        require_once dirname(__DIR__) . '/modules/HNS_BossEngine.php';
        require_once dirname(__DIR__) . '/modules/HNS_GameEngine.php';

        $state = HNS_GameEngine::createLevel(HNS_BOSS_LEVEL, 1234, [], [], [], ['slasher' => ['phases' => [1 => ['health' => 8]]]]);
        $center = (int) floor($state['layout']['grid_size'] / 2);

        foreach ($state['tiles'] as $tile) {
            if (abs((int) $tile['x'] - $center) <= 1 && abs((int) $tile['y'] - $center) <= 1) {
                $this->assertSame('floor', $tile['type']);
            }
        }

        $this->assertSame(($center * 100) + $center + 1, $state['entities'][900]['tile_id']);
    }

    /** @param array<string, mixed> $level */
    private function terrainByCoordinate(array $level): array
    {
        $terrain = [];
        foreach ($level['terrain'] as $cell) {
            $terrain[$cell['x'] . ',' . $cell['y']] = $cell['terrain'];
        }

        return $terrain;
    }

    /**
     * @param array<string, mixed> $level
     * @param array{x:int, y:int} $start
     * @return array<string, true>
     */
    private function orthogonallyReachableWalkableCells(array $level, array $start): array
    {
        $terrain = $this->terrainByCoordinate($level);
        $queue = [$start];
        $visited = [$start['x'] . ',' . $start['y'] => true];

        while ($queue !== []) {
            $cell = array_shift($queue);
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $neighbor = ['x' => $cell['x'] + $dx, 'y' => $cell['y'] + $dy];
                $key = $neighbor['x'] . ',' . $neighbor['y'];
                if (isset($visited[$key]) || !in_array($terrain[$key] ?? null, ['floor', 'spikes'], true)) {
                    continue;
                }
                $visited[$key] = true;
                $queue[] = $neighbor;
            }
        }

        return $visited;
    }
}
