<?php

final class HNS_LevelGenerator
{
    private const TERRAIN_FLOOR = 'floor';
    private const TERRAIN_WALL = 'wall';
    private const TERRAIN_PILLAR = 'pillar';
    private const TERRAIN_SPIKES = 'spikes';
    private const TERRAIN_HOLE = 'hole';
    private const TERRAIN_ENTRY = 'entry';
    private const TERRAIN_EXIT = 'exit';

    private const COUNTS_BY_SIZE = [
        5 => ['walls' => 0, 'pillars' => 3, 'spikes' => 2, 'holes' => 1, 'monsters' => 7],
        7 => ['walls' => 0, 'pillars' => 10, 'spikes' => 4, 'holes' => 3, 'monsters' => 7],
    ];

    /** @return array<string, mixed> */
    public static function generate(int $size, int $seed): array
    {
        if (!isset(self::COUNTS_BY_SIZE[$size])) {
            throw new InvalidArgumentException('Level size must be 5 or 7.');
        }

        $rng = new HNS_SeededRandom($seed);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $level = self::generateOnce($size, $seed, $rng);
            if ($level !== null) {
                return $level;
            }
        }

        throw new RuntimeException('Could not generate a valid level after 100 attempts.');
    }

    /** @return array<string, mixed>|null */
    private static function generateOnce(int $size, int $seed, HNS_SeededRandom $rng): ?array
    {
        $gridSize = $size + 2;
        $counts = self::COUNTS_BY_SIZE[$size];
        $grid = array_fill(0, $gridSize, array_fill(0, $gridSize, self::TERRAIN_FLOOR));
        foreach (self::borderCells($gridSize) as $cell) {
            $grid[$cell['y']][$cell['x']] = self::TERRAIN_WALL;
        }

        $entry = $rng->pick(self::entryCells($gridSize));
        $exit = $rng->pick(array_values(array_filter(self::borderCells($gridSize), static fn(array $cell): bool => $cell['y'] === ($entry['y'] === 0 ? $gridSize - 1 : 0))));
        $grid[$entry['y']][$entry['x']] = self::TERRAIN_ENTRY;
        $grid[$exit['y']][$exit['x']] = self::TERRAIN_EXIT;

        $anchor = self::borderInnerAnchor($entry, $gridSize);
        $exitAnchor = self::borderInnerAnchor($exit, $gridSize);
        $playerStarts = [['x' => $anchor['x'] - 1, 'y' => $anchor['y']], ['x' => $anchor['x'] + 1, 'y' => $anchor['y']]];
        if (!self::insidePlayableArea($playerStarts[0], $gridSize) || !self::insidePlayableArea($playerStarts[1], $gridSize)) {
            return null;
        }

        $reserved = [$entry, $exit, $anchor, $exitAnchor, ...$playerStarts];
        foreach ([[self::TERRAIN_WALL, $counts['walls']], [self::TERRAIN_PILLAR, $counts['pillars']], [self::TERRAIN_HOLE, $counts['holes']]] as [$terrain, $count]) {
            if (!self::placeBlockingTerrain($grid, $terrain, $count, $reserved, $rng)) {
                return null;
            }
        }

        if (!self::placeSpikes($grid, $counts['spikes'], $reserved, $rng)) {
            return null;
        }

        $reachable = array_values(array_filter(self::reachableCells($grid, $anchor), static function (array $cell) use ($grid): bool {
            return in_array($grid[$cell['y']][$cell['x']], [self::TERRAIN_FLOOR, self::TERRAIN_SPIKES], true);
        }));
        $monsterCandidates = array_values(array_filter($reachable, static fn(array $cell): bool => !self::containsCell($reserved, $cell)));
        if (count($monsterCandidates) < $counts['monsters']) {
            return null;
        }

        $monsterCells = array_slice($rng->shuffle($monsterCandidates), 0, $counts['monsters']);
        $monsters = [];
        foreach ($monsterCells as $index => $cell) {
            $monsters[] = ['label' => (string) ($index + 1), 'x' => $cell['x'], 'y' => $cell['y']];
        }

        return [
            'size' => $size,
            'grid_size' => $gridSize,
            'seed' => $seed,
            'terrain' => self::terrainPayload($grid),
            'player_starts' => $playerStarts,
            'monster_starts' => $monsters,
            'entry' => $entry,
            'exit' => $exit,
        ];
    }

    /** @param array<int, array<int, string>> $grid */
    private static function placeBlockingTerrain(array &$grid, string $terrain, int $count, array $reserved, HNS_SeededRandom $rng): bool
    {
        for ($placedCount = 0; $placedCount < $count; $placedCount++) {
            $placed = false;
            foreach ($rng->shuffle(self::candidateCells(count($grid), $reserved)) as $cell) {
                if ($grid[$cell['y']][$cell['x']] !== self::TERRAIN_FLOOR) {
                    continue;
                }
                if ($terrain === self::TERRAIN_WALL && !self::adjacentToWall($grid, $cell)) {
                    continue;
                }

                $grid[$cell['y']][$cell['x']] = $terrain;
                if (self::validLayout($grid, $reserved)) {
                    $placed = true;
                    break;
                }
                $grid[$cell['y']][$cell['x']] = self::TERRAIN_FLOOR;
            }
            if (!$placed) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, array<int, string>> $grid */
    private static function placeSpikes(array &$grid, int $count, array $reserved, HNS_SeededRandom $rng): bool
    {
        $candidates = array_values(array_filter(self::candidateCells(count($grid), $reserved), static fn(array $cell): bool => $grid[$cell['y']][$cell['x']] === self::TERRAIN_FLOOR));
        $spikes = array_slice($rng->shuffle($candidates), 0, $count);
        if (count($spikes) < $count) {
            return false;
        }

        foreach ($spikes as $cell) {
            $grid[$cell['y']][$cell['x']] = self::TERRAIN_SPIKES;
        }

        return true;
    }

    /** @param array<int, array<int, string>> $grid */
    private static function validLayout(array $grid, array $reserved): bool
    {
        $reachable = self::reachableCells($grid, $reserved[2] ?? $reserved[0]);

        foreach (array_slice($reserved, 2) as $cell) {
            if (!self::containsCell($reachable, $cell)) {
                return false;
            }
        }

        foreach (self::innerCells(count($grid)) as $cell) {
            if (self::walkable($grid, $cell) && !self::containsCell($reachable, $cell)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, array<int, string>> $grid */
    private static function reachableCells(array $grid, array $start): array
    {
        $queue = [$start];
        $visited = [self::key($start) => true];
        $output = [];

        while ($queue !== []) {
            $cell = array_shift($queue);
            $output[] = $cell;
            foreach (self::neighbors($cell, count($grid)) as $neighbor) {
                if (isset($visited[self::key($neighbor)]) || !self::walkable($grid, $neighbor)) {
                    continue;
                }
                $visited[self::key($neighbor)] = true;
                $queue[] = $neighbor;
            }
        }

        return $output;
    }

    /** @param array<int, array<int, string>> $grid */
    private static function walkable(array $grid, array $cell): bool
    {
        return in_array($grid[$cell['y']][$cell['x']], [self::TERRAIN_FLOOR, self::TERRAIN_SPIKES], true);
    }

    /** @param array<int, array<int, string>> $grid */
    private static function adjacentToWall(array $grid, array $cell): bool
    {
        foreach (self::neighbors($cell, count($grid)) as $neighbor) {
            if ($grid[$neighbor['y']][$neighbor['x']] === self::TERRAIN_WALL) {
                return true;
            }
        }

        return false;
    }

    private static function borderInnerAnchor(array $entryOrExit, int $gridSize): array
    {
        return ['x' => $entryOrExit['x'], 'y' => $entryOrExit['y'] === 0 ? 1 : $gridSize - 2];
    }

    private static function insidePlayableArea(array $cell, int $gridSize): bool
    {
        return $cell['x'] > 0 && $cell['y'] > 0 && $cell['x'] < $gridSize - 1 && $cell['y'] < $gridSize - 1;
    }

    private static function key(array $cell): string
    {
        return $cell['x'] . ',' . $cell['y'];
    }

    private static function containsCell(array $cells, array $needle): bool
    {
        foreach ($cells as $cell) {
            if ($cell['x'] === $needle['x'] && $cell['y'] === $needle['y']) {
                return true;
            }
        }

        return false;
    }

    private static function candidateCells(int $gridSize, array $reserved): array
    {
        return array_values(array_filter(self::innerCells($gridSize), static fn(array $cell): bool => !self::containsCell($reserved, $cell)));
    }

    private static function entryCells(int $gridSize): array
    {
        return array_values(array_filter(self::borderCells($gridSize), static fn(array $cell): bool => ($cell['y'] === 0 || $cell['y'] === $gridSize - 1) && $cell['x'] >= 2 && $cell['x'] <= $gridSize - 3));
    }

    private static function borderCells(int $gridSize): array
    {
        return array_values(array_filter(self::allCells($gridSize), static fn(array $cell): bool => $cell['x'] === 0 || $cell['y'] === 0 || $cell['x'] === $gridSize - 1 || $cell['y'] === $gridSize - 1));
    }

    private static function innerCells(int $gridSize): array
    {
        return array_values(array_filter(self::allCells($gridSize), static fn(array $cell): bool => $cell['x'] > 0 && $cell['y'] > 0 && $cell['x'] < $gridSize - 1 && $cell['y'] < $gridSize - 1));
    }

    private static function allCells(int $gridSize): array
    {
        $cells = [];
        for ($y = 0; $y < $gridSize; $y++) {
            for ($x = 0; $x < $gridSize; $x++) {
                $cells[] = ['x' => $x, 'y' => $y];
            }
        }

        return $cells;
    }

    private static function neighbors(array $cell, int $gridSize): array
    {
        $neighbors = [];
        foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
            $x = $cell['x'] + $dx;
            $y = $cell['y'] + $dy;
            if ($x < 0 || $y < 0 || $x >= $gridSize || $y >= $gridSize) {
                continue;
            }
            $neighbors[] = ['x' => $x, 'y' => $y];
        }

        return $neighbors;
    }

    /** @param array<int, array<int, string>> $grid */
    private static function terrainPayload(array $grid): array
    {
        $terrain = [];
        foreach ($grid as $y => $row) {
            foreach ($row as $x => $type) {
                $terrain[] = ['x' => $x, 'y' => $y, 'terrain' => $type];
            }
        }

        return $terrain;
    }
}

