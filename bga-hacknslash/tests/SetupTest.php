<?php

use PHPUnit\Framework\TestCase;

final class SetupTest extends TestCase
{
    public function testStartingPowersAreOneStrikeAndTwoAttacks(): void
    {
        $setupSource = file_get_contents(dirname(__DIR__) . '/modules/HNS_Setup.php');

        $this->assertStringContainsString("private const HNS_STARTING_POWER_KEYS = ['strike', 'attack', 'attack'];", $setupSource);
        $this->assertStringContainsString('INSERT INTO player_power (player_id, power_slot, power_key, power_cooldown)', $setupSource);
    }

    public function testSetupUsesGeneratedLevelAndPersistsMonsterAbilities(): void
    {
        $setupSource = file_get_contents(dirname(__DIR__) . '/modules/HNS_Setup.php');

        $this->assertStringContainsString('HNS_GameEngine::createLevel', $setupSource);
        $this->assertStringContainsString('level_monster_abilities', $setupSource);
        $this->assertStringContainsString('entity_monster_size', $setupSource);
        $this->assertStringContainsString('entity_on_death', $setupSource);
    }
}
