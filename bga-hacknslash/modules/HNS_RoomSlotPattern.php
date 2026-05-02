<?php

final class HNS_RoomSlotPattern
{
    public const MIN_SLOT = 1;
    public const MAX_SLOT = 7;
    public const MAX_EVENTS = 2;

    /**
     * @param array<int, array<string, mixed>> $slots
     * @return array<int, string>
     */
    public static function validate(array $slots): array
    {
        $errors = [];
        $eventCount = 0;

        foreach ($slots as $slot => $content) {
            $slot = (int) $slot;
            if ($slot < self::MIN_SLOT || $slot > self::MAX_SLOT) {
                $errors[] = "slot $slot is outside the room slot range";
                continue;
            }

            $type = $content['type'] ?? null;
            if ($type === 'event') {
                $eventCount++;
                if (self::isEvenSlot($slot)) {
                    $errors[] = "slot $slot cannot contain an event";
                }
                continue;
            }

            if ($type !== 'monster') {
                $errors[] = "slot $slot has an unknown content type";
                continue;
            }

            $size = $content['size'] ?? null;
            if ($size === 'large' && !self::isEvenSlot($slot)) {
                $errors[] = "slot $slot cannot contain a large monster";
            }

            if ($size === 'small' && self::isEvenSlot($slot)) {
                $errors[] = "slot $slot cannot contain a small monster";
            }

            if ($size !== 'large' && $size !== 'small') {
                $errors[] = "slot $slot has an unknown monster size";
            }
        }

        if ($eventCount > self::MAX_EVENTS) {
            $errors[] = 'room cannot contain more than 2 events';
        }

        return $errors;
    }

    private static function isEvenSlot(int $slot): bool
    {
        return $slot % 2 === 0;
    }
}
