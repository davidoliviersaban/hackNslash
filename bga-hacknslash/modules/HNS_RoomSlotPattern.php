<?php

final class HNS_RoomSlotPattern
{
    public const MIN_SLOT = 1;
    public const MAX_SLOT = 7;
    public const BOSS_LEVEL = 8;
    public const MAX_ENCHANTMENTS = 2;

    /**
     * @param array<int, array<string, mixed>> $monsters
     * @return array<int, array<string, mixed>>
     */
    public static function assignLevelMonsterSlots(int $level, array $monsters): array
    {
        if ($level === self::BOSS_LEVEL) {
            return [];
        }

        if ($level < self::MIN_SLOT || $level > self::BOSS_LEVEL) {
            throw new InvalidArgumentException('Level must be between 1 and 8.');
        }

        if (count($monsters) < $level) {
            throw new InvalidArgumentException('Not enough monsters to fill level slots.');
        }

        $slots = [];
        for ($slot = self::MIN_SLOT; $slot <= $level; $slot++) {
            $monster = $monsters[$slot - 1];
            $slots[$slot] = ['type' => 'monster'] + $monster;
        }

        return $slots;
    }

    /**
     * @param array<int, array<string, mixed>> $slots
     * @return array<int, string>
     */
    public static function validate(array $slots): array
    {
        $errors = [];
        $enchantmentCount = 0;
        $enchantments = [];

        foreach ($slots as $slot => $content) {
            $slot = (int) $slot;
            if ($slot < self::MIN_SLOT || $slot > self::MAX_SLOT) {
                $errors[] = "slot $slot is outside the room slot range";
                continue;
            }

            $type = $content['type'] ?? null;
            if ($type === 'enchantment') {
                $enchantmentCount++;
                if (self::isEvenSlot($slot)) {
                    $errors[] = "slot $slot cannot contain an enchantment";
                }

                $enchantment = $content['enchantment'] ?? null;
                if (!is_string($enchantment) || $enchantment === '') {
                    $errors[] = "slot $slot has an unknown enchantment";
                    continue;
                }

                if (isset($enchantments[$enchantment])) {
                    $errors[] = "room cannot contain duplicate enchantment $enchantment";
                }

                $enchantments[$enchantment] = true;
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

        if ($enchantmentCount > self::MAX_ENCHANTMENTS) {
            $errors[] = 'room cannot contain more than 2 enchantments';
        }

        return $errors;
    }

    private static function isEvenSlot(int $slot): bool
    {
        return $slot % 2 === 0;
    }
}
