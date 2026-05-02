<?php

final class HNS_BoardRules
{
    private const WALKABLE_TILE_TYPES = ['floor', 'entry', 'exit', 'spikes'];

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
     * @param array<string, mixed> $tile
     */
    public static function isTileWalkable(array $tile): bool
    {
        return in_array($tile['type'] ?? null, self::WALKABLE_TILE_TYPES, true);
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     */
    public static function isTileAvailable(int $tileId, array $entities, ?int $movingEntityId = null): bool
    {
        foreach ($entities as $entity) {
            if (($entity['state'] ?? 'active') !== 'active') {
                continue;
            }

            if ((int) ($entity['id'] ?? 0) === $movingEntityId) {
                continue;
            }

            if ((int) ($entity['tile_id'] ?? 0) === $tileId) {
                return false;
            }
        }

        return true;
    }
}
