<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_RoomSlotPattern.php';

final class RoomSlotPatternTest extends TestCase
{
    public function testValidRoomAcceptsLargeMonstersOnEvenSlotsAndSmallMonstersOrEnchantmentsOnOddSlots(): void
    {
        $room = [
            1 => ['type' => 'monster', 'size' => 'small'],
            2 => ['type' => 'monster', 'size' => 'large'],
            3 => ['type' => 'enchantment', 'enchantment' => 'shield'],
            4 => ['type' => 'monster', 'size' => 'large'],
            5 => ['type' => 'monster', 'size' => 'small'],
            6 => ['type' => 'monster', 'size' => 'large'],
            7 => ['type' => 'enchantment', 'enchantment' => 'spikes'],
        ];

        $this->assertSame([], HNS_RoomSlotPattern::validate($room));
    }

    public function testLargeMonsterOnOddSlotIsRejected(): void
    {
        $errors = HNS_RoomSlotPattern::validate([
            1 => ['type' => 'monster', 'size' => 'large'],
        ]);

        $this->assertContains('slot 1 cannot contain a large monster', $errors);
    }

    public function testSmallMonsterOnEvenSlotIsRejected(): void
    {
        $errors = HNS_RoomSlotPattern::validate([
            2 => ['type' => 'monster', 'size' => 'small'],
        ]);

        $this->assertContains('slot 2 cannot contain a small monster', $errors);
    }

    public function testEnchantmentOnEvenSlotIsRejected(): void
    {
        $errors = HNS_RoomSlotPattern::validate([
            2 => ['type' => 'enchantment', 'enchantment' => 'shield'],
        ]);

        $this->assertContains('slot 2 cannot contain an enchantment', $errors);
    }

    public function testRoomRejectsMoreThanTwoEnchantments(): void
    {
        $errors = HNS_RoomSlotPattern::validate([
            1 => ['type' => 'enchantment', 'enchantment' => 'shield'],
            3 => ['type' => 'enchantment', 'enchantment' => 'spikes'],
            5 => ['type' => 'enchantment', 'enchantment' => 'weakness'],
        ]);

        $this->assertContains('room cannot contain more than 2 enchantments', $errors);
    }

    public function testRoomRejectsDuplicateEnchantments(): void
    {
        $errors = HNS_RoomSlotPattern::validate([
            1 => ['type' => 'enchantment', 'enchantment' => 'shield'],
            3 => ['type' => 'enchantment', 'enchantment' => 'shield'],
        ]);

        $this->assertContains('room cannot contain duplicate enchantment shield', $errors);
    }
}
