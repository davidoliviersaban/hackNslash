<?php

use PHPUnit\Framework\TestCase;

final class SetupTest extends TestCase
{
    private static function readFile(string $path): string
    {
        $contents = '';
        $file = new SplFileObject($path);
        while (!$file->eof()) {
            $contents .= $file->fgets();
        }
        return $contents;
    }

    public function testStartingPowersAreOneStrikeAndTwoAttacks(): void
    {
        $setupSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Setup.php');

        $this->assertStringContainsString("private const HNS_STARTING_POWER_KEYS = ['strike', 'attack', 'attack'];", $setupSource);
        $this->assertStringContainsString('INSERT INTO player_power (player_id, power_slot, power_key, power_cooldown)', $setupSource);
    }

    public function testSetupUsesGeneratedLevelAndPersistsMonsterAbilities(): void
    {
        $setupSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Setup.php');

        $this->assertStringContainsString('HNS_GameEngine::createLevel', $setupSource);
        $this->assertStringContainsString('level_monster_abilities', $setupSource);
        $this->assertStringContainsString('entity_monster_size', $setupSource);
        $this->assertStringContainsString('entity_on_death', $setupSource);
    }

    public function testPersistEngineStateSynchronizesHeroHealthToPlayer(): void
    {
        $boardSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Board.php');

        $this->assertStringContainsString('syncHeroPlayerHealth', $boardSource);
        $this->assertStringContainsString('player_health', $boardSource);
    }

    public function testRuntimeShieldStateIsLoadedAndPersisted(): void
    {
        $boardSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Board.php');
        $setupSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Setup.php');

        $this->assertStringContainsString('ensureEntityRuntimeColumns', $boardSource);
        $this->assertStringContainsString('entity_has_shield has_shield', $boardSource);
        $this->assertStringContainsString('entity_has_shield = $hasShield', $boardSource);
        $this->assertStringContainsString('entity_has_shield, entity_shield_broken', $setupSource);
    }

    public function testBoardMigratesExistingStudioEntityTables(): void
    {
        $boardSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Board.php');

        $this->assertStringContainsString('SHOW COLUMNS FROM entity LIKE', $boardSource);
        $this->assertStringContainsString('ALTER TABLE entity ADD entity_has_shield', $boardSource);
    }

    public function testHeroesStartOnGeneratedStartTilesNotEntrance(): void
    {
        $setupSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Setup.php');
        $boardSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Board.php');
        $gameSource = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('heroStartTileIdsForLevel', $setupSource);
        $this->assertStringContainsString('$playerCount <= 1', $setupSource);
        $this->assertStringContainsString('[[$anchorX - 1, $anchorY], [$anchorX + 1, $anchorY]]', $setupSource);
        $this->assertStringContainsString("tile_type IN ('floor', 'entry', 'exit', 'spikes')", $setupSource);
        $this->assertStringContainsString('moveHeroesToLevelStarts', $boardSource);
        $this->assertStringContainsString('moveHeroesToLevelStarts', $gameSource);
        $this->assertStringNotContainsString('moveHeroesToCurrentLevelEntry', $boardSource . $gameSource);
    }

    public function testEngineStateOnlyLoadsEntitiesOnCurrentLevelTiles(): void
    {
        $boardSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Board.php');

        $this->assertStringContainsString('getEntitiesForLevel', $boardSource);
        $this->assertStringContainsString('JOIN tile t ON t.tile_id = e.entity_tile_id', $boardSource);
        $this->assertStringContainsString('t.tile_level = $level', $boardSource);
    }

    public function testLevelTransitionDeletesPreviousLevelMonsters(): void
    {
        $boardSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Board.php');
        $gameSource = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('deleteMonstersOutsideLevel', $boardSource);
        $this->assertStringContainsString("entity_type IN ('monster', 'boss')", $boardSource);
        $this->assertStringContainsString('deleteMonstersOutsideLevel($level + 1)', $gameSource);
    }

    public function testInitialSetupDoesNotRandomlyGrantMonsterEnchantments(): void
    {
        $setupSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Setup.php');

        $this->assertStringContainsString('protected function drawLevelEnchantments(): array', $setupSource);
        $this->assertStringNotContainsString("['shield', 'thorns', null]", $setupSource);
    }

    public function testSetupActivatesFirstCreatedPlayer(): void
    {
        $gameSource = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('$this->gamestate->changeActivePlayer((int) array_key_first($players));', $gameSource);
    }
}
