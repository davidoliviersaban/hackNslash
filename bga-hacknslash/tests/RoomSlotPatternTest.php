<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_RoomSlotPattern.php';

final class RoomSlotPatternTest extends TestCase
{
    public function testValidRoomAcceptsLargeMonstersOnEvenSlotsAndSmallMonstersOrEventsOnOddSlots(): void
    {
        $room = [
            1 => ['type' => 'monster', 'size' => 'small'],
            2 => ['type' => 'monster', 'size' => 'large'],
            3 => ['type' => 'event', 'event' => 'shield'],
            4 => ['type' => 'monster', 'size' => 'large'],
            5 => ['type' => 'monster', 'size' => 'small'],
            6 => ['type' => 'monster', 'size' => 'large'],
            7 => ['type' => 'event', 'event' => 'spikes'],
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

    public function testEventOnEvenSlotIsRejected(): void
    {
        $errors = HNS_RoomSlotPattern::validate([
            2 => ['type' => 'event', 'event' => 'shield'],
        ]);

        $this->assertContains('slot 2 cannot contain an event', $errors);
    }

    public function testRoomRejectsMoreThanTwoEvents(): void
    {
        $errors = HNS_RoomSlotPattern::validate([
            1 => ['type' => 'event', 'event' => 'shield'],
            3 => ['type' => 'event', 'event' => 'spikes'],
            5 => ['type' => 'event', 'event' => 'spikes'],
        ]);

        $this->assertContains('room cannot contain more than 2 events', $errors);
    }
}
