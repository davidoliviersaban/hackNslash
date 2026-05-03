<?php

final class HNS_BoardRules
{
    private const WALKABLE_TILE_TYPES = ['floor', 'spikes'];

    /**
     * @param array{x:int, y:int} $from
     * @param array{x:int, y:int} $to
     */
    public static function distance(array $from, array $to): int
    {
        return abs((int) $from['x'] - (int) $to['x']) + abs((int) $from['y'] - (int) $to['y']);
    }

    /**
     * @param array{x:int, y:int} $from
     * @param array{x:int, y:int} $to
     */
    public static function diagonalDistance(array $from, array $to): int
    {
        return max(abs((int) $from['x'] - (int) $to['x']), abs((int) $from['y'] - (int) $to['y']));
    }

    /**
     * @param array{x:int, y:int} $from
     * @param array{x:int, y:int} $to
     */
    public static function isExactStep(array $from, array $to, int $distance): bool
    {
        return self::distance($from, $to) === $distance;
    }

    /**
     * @param array{x:int, y:int} $from
     * @param array{x:int, y:int} $to
     * @param array{0:int, 1:int} $range
     */
    public static function isInRange(array $from, array $to, array $range): bool
    {
        $distance = self::distance($from, $to);

        return $distance >= (int) $range[0] && $distance <= (int) $range[1];
    }

    /**
     * @param array{x:int, y:int} $from
     * @param array{x:int, y:int} $to
     * @param array{0:int, 1:int} $range
     */
    public static function isInOrthogonalRange(array $from, array $to, array $range): bool
    {
        if ((int) $from['x'] !== (int) $to['x'] && (int) $from['y'] !== (int) $to['y']) {
            return false;
        }

        return self::isInRange($from, $to, $range);
    }

    /**
     * @param array{x:int, y:int} $from
     * @param array{x:int, y:int} $to
     * @param array{0:int, 1:int} $range
     */
    public static function isInDiagonalRange(array $from, array $to, array $range): bool
    {
        $distance = self::diagonalDistance($from, $to);

        return $distance >= (int) $range[0] && $distance <= (int) $range[1];
    }

    /**
     * @param array{x:int, y:int} $from
     * @param array{x:int, y:int} $to
     * @param array<int, array<string, mixed>> $tiles
     */
    public static function hasLineOfSight(array $from, array $to, array $tiles): bool
    {
        $x = (int) $from['x'];
        $y = (int) $from['y'];
        $targetX = (int) $to['x'];
        $targetY = (int) $to['y'];
        $dx = abs($targetX - $x);
        $dy = abs($targetY - $y);
        $stepX = $targetX <=> $x;
        $stepY = $targetY <=> $y;
        $walkedX = 0;
        $walkedY = 0;

        while ($walkedX < $dx || $walkedY < $dy) {
            $decision = (1 + (2 * $walkedX)) * $dy - (1 + (2 * $walkedY)) * $dx;
            if ($decision === 0) {
                $x += $stepX;
                $y += $stepY;
                $walkedX++;
                $walkedY++;
            } elseif ($decision < 0) {
                $x += $stepX;
                $walkedX++;
            } else {
                $y += $stepY;
                $walkedY++;
            }

            if ($x === $targetX && $y === $targetY) {
                return true;
            }

            if (self::hasBlockingTileAt($x, $y, $tiles)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $tile
     */
    public static function isTileWalkable(array $tile): bool
    {
        return in_array($tile['type'] ?? null, self::WALKABLE_TILE_TYPES, true);
    }

    /** @param array<int, array<string, mixed>> $tiles */
    private static function hasBlockingTileAt(int $x, int $y, array $tiles): bool
    {
        foreach ($tiles as $tile) {
            if ((int) ($tile['x'] ?? 0) === $x && (int) ($tile['y'] ?? 0) === $y && !self::isTileWalkable($tile)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     */
    public static function isTileAvailable(int $tileId, array $entities, ?int $movingEntityId = null): bool
    {
        return self::canEnterTile($tileId, $entities, null, $movingEntityId);
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     * @param array<string, mixed>|null $movingEntity
     */
    public static function canEnterTile(int $tileId, array $entities, ?array $movingEntity = null, ?int $movingEntityId = null): bool
    {
        $movingEntityId ??= $movingEntity === null ? null : (int) ($movingEntity['id'] ?? 0);
        $movingType = $movingEntity['type'] ?? null;

        foreach ($entities as $entity) {
            if (($entity['state'] ?? 'active') !== 'active') {
                continue;
            }

            if ((int) ($entity['id'] ?? 0) === $movingEntityId) {
                continue;
            }

            if ((int) ($entity['tile_id'] ?? 0) !== $tileId) {
                continue;
            }

            if ($movingType === 'monster' && ($entity['type'] ?? null) === 'monster') {
                return self::canShareMonsterTile($movingEntity, $entities, $tileId, $movingEntityId);
            }

            return false;
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     * @param array<string, mixed>|null $movingEntity
     */
    private static function canShareMonsterTile(?array $movingEntity, array $entities, int $tileId, ?int $movingEntityId): bool
    {
        if ($movingEntity === null) {
            return false;
        }

        $movingSize = $movingEntity['monster_size'] ?? 'small';
        $smallMonsters = 0;
        $hasBigMonster = false;

        foreach ($entities as $entity) {
            if (($entity['state'] ?? 'active') !== 'active') {
                continue;
            }

            if ((int) ($entity['id'] ?? 0) === $movingEntityId) {
                continue;
            }

            if ((int) ($entity['tile_id'] ?? 0) !== $tileId || ($entity['type'] ?? null) !== 'monster') {
                continue;
            }

            if (($entity['monster_size'] ?? 'small') === 'big') {
                $hasBigMonster = true;
            } else {
                $smallMonsters++;
            }
        }

        if ($movingSize === 'big') {
            return !$hasBigMonster && $smallMonsters === 0;
        }

        if ($hasBigMonster) {
            return false;
        }

        return $smallMonsters < 2;
    }
}
