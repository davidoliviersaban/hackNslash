<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_RoomSlotPattern.php';

final class RoomSlotPatternTest extends TestCase
{
    public function testLevelOneAssignsOneMonsterToSlotOne(): void
    {
        $slots = HNS_RoomSlotPattern::assignLevelMonsterSlots(1, [
            ['monster_id' => 1, 'size' => 'small'],
        ]);

        $this->assertSame([
            1 => ['type' => 'monster', 'monster_id' => 1, 'size' => 'small'],
        ], $slots);
    }

    public function testLevelThreeAssignsMonstersToSlotsOneThroughThree(): void
    {
        $slots = HNS_RoomSlotPattern::assignLevelMonsterSlots(3, [
            ['monster_id' => 1, 'size' => 'small'],
            ['monster_id' => 7, 'size' => 'big'],
            ['monster_id' => 2, 'size' => 'small'],
        ]);

        $this->assertSame([1, 2, 3], array_keys($slots));
        $this->assertSame(1, $slots[1]['monster_id']);
        $this->assertSame(7, $slots[2]['monster_id']);
        $this->assertSame(2, $slots[3]['monster_id']);
    }

    public function testSmallMonstersSkipEvenSlots(): void
    {
        $slots = HNS_RoomSlotPattern::assignLevelMonsterSlots(2, [
            ['monster_id' => 1, 'size' => 'small'],
            ['monster_id' => 3, 'size' => 'small'],
        ]);

        $this->assertSame([1, 3], array_keys($slots));
        $this->assertSame(1, $slots[1]['monster_id']);
        $this->assertSame(3, $slots[3]['monster_id']);
    }

    public function testLevelSlotAssignmentRequiresEnoughMonsters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Not enough monsters to fill level slots.');

        HNS_RoomSlotPattern::assignLevelMonsterSlots(2, [
            ['monster_id' => 1, 'size' => 'small'],
        ]);
    }

    public function testLevelEightIsBossLevelAndDoesNotAssignMonsterSlots(): void
    {
        $this->assertSame([], HNS_RoomSlotPattern::assignLevelMonsterSlots(8, []));
    }

    public function testValidRoomAcceptsLargeMonstersOnEvenSlotsAndSmallMonstersOrEnchantmentsOnOddSlots(): void
    {
        $room = [
            1 => ['type' => 'monster', 'size' => 'small'],
            2 => ['type' => 'monster', 'size' => 'big'],
            3 => ['type' => 'enchantment', 'enchantment' => 'shield'],
            4 => ['type' => 'monster', 'size' => 'big'],
            5 => ['type' => 'monster', 'size' => 'small'],
            6 => ['type' => 'monster', 'size' => 'big'],
            7 => ['type' => 'enchantment', 'enchantment' => 'spikes'],
        ];

        $this->assertSame([], HNS_RoomSlotPattern::validate($room));
    }

    public function testLargeMonsterOnOddSlotIsRejected(): void
    {
        $errors = HNS_RoomSlotPattern::validate([
            1 => ['type' => 'monster', 'size' => 'big'],
        ]);

        $this->assertContains('slot 1 cannot contain a big monster', $errors);
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
