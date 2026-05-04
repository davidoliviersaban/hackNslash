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
        $this->assertStringContainsString('INSERT INTO player_power (player_id, power_slot, power_key, power_cooldown, power_plays_remaining)', $setupSource);
    }

    public function testBossFightDifficultyStartsAtBossWithBoostedHeroes(): void
    {
        $options = self::readFile(dirname(__DIR__) . '/gameoptions.json');
        $constants = self::readFile(dirname(__DIR__) . '/modules/material/constants.inc.php');
        $setupSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Setup.php');
        $gameSource = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('"2": { "name": "Boss fight" }', $options);
        $this->assertStringContainsString('const HNS_DIFFICULTY_BOSS_FIGHT = 2;', $constants);
        $this->assertStringContainsString('const HNS_BOSS_FIGHT_HEALTH = 100;', $constants);
        $this->assertStringContainsString('private const HNS_BOSS_FIGHT_POWER_COUNT = 2;', $setupSource);
        $this->assertStringContainsString('shuffle($powerKeys);', $setupSource);
        $this->assertStringContainsString('$startLevel = $difficulty === HNS_DIFFICULTY_BOSS_FIGHT ? HNS_BOSS_LEVEL : HNS_FIRST_LEVEL;', $gameSource);
        $this->assertStringContainsString('$startingHealth = $difficulty === HNS_DIFFICULTY_BOSS_FIGHT ? HNS_BOSS_FIGHT_HEALTH : HNS_DEFAULT_HEALTH;', $gameSource);
    }

    public function testSetupUsesGeneratedLevelAndPersistsMonsterAbilities(): void
    {
        $setupSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Setup.php');

        $this->assertStringContainsString('HNS_GameEngine::createLevel', $setupSource);
        $this->assertStringContainsString('level_monster_abilities', $setupSource);
        $this->assertStringContainsString('entity_monster_size', $setupSource);
        $this->assertStringContainsString('entity_on_death', $setupSource);
    }

    public function testSetupPersistsBossEntitiesSeparatelyFromMonsters(): void
    {
        $setupSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Setup.php');
        $gameEngineSource = self::readFile(dirname(__DIR__) . '/modules/HNS_GameEngine.php');

        $this->assertStringContainsString('$type === \'boss\'', $setupSource);
        $this->assertStringContainsString("INSERT INTO entity (entity_type, entity_type_arg, entity_tile_id, entity_health, entity_monster_size, entity_boss_key, entity_phase", $setupSource);
        $this->assertStringContainsString("HNS_BossEngine::initialBossEntity('slasher'", $gameEngineSource);
        $this->assertStringContainsString("'layout' => \$layout", $gameEngineSource);
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

    public function testSpawnLabelsAreStoredOnTiles(): void
    {
        $setupSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Setup.php');
        $boardSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Board.php');
        $dbmodel = self::readFile(dirname(__DIR__) . '/dbmodel.sql');

        $this->assertStringContainsString('tile_spawn_label', $dbmodel);
        $this->assertStringContainsString('spawnLabelsByCoordinate', $setupSource);
        $this->assertStringContainsString('tile_spawn_label spawn_label', $boardSource);
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
        $this->assertStringContainsString("tile_type IN ('floor', 'spikes')", $setupSource);
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
        $this->assertStringContainsString('startNextLevelAfterReward', $gameSource);
        $this->assertStringContainsString('deleteMonstersOutsideLevel($nextLevel)', $gameSource);
    }

    public function testSetupDrawsDeterministicMonsterEnchantmentsAfterFirstLevel(): void
    {
        $setupSource = self::readFile(dirname(__DIR__) . '/modules/HNS_Setup.php');

        $this->assertStringContainsString('protected function drawLevelEnchantments(int $level): array', $setupSource);
        $this->assertStringContainsString('$cycle = [\'shield\', \'thorns\', null];', $setupSource);
        $this->assertStringContainsString('drawLevelEnchantments($level)', $setupSource);
    }

    public function testLevelEnchantmentCycle(): void
    {
        require_once dirname(__DIR__) . '/modules/material/constants.inc.php';
        require_once dirname(__DIR__) . '/modules/HNS_Setup.php';

        $setup = new class {
            use HNS_Setup {
                drawLevelEnchantments as public;
            }
        };

        $this->assertSame([], $setup->drawLevelEnchantments(1));
        $this->assertSame(['shield'], $setup->drawLevelEnchantments(2));
        $this->assertSame(['thorns'], $setup->drawLevelEnchantments(3));
        $this->assertSame([], $setup->drawLevelEnchantments(4));
        $this->assertSame(['shield'], $setup->drawLevelEnchantments(5));
        $this->assertSame(['thorns'], $setup->drawLevelEnchantments(6));
        $this->assertSame([], $setup->drawLevelEnchantments(HNS_BOSS_LEVEL));
    }

    public function testBossFightStartingPowersDrawsTwoRankThreePowers(): void
    {
        require_once dirname(__DIR__) . '/modules/HNS_Setup.php';
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';

        $setup = new class($bonus_cards) {
            use HNS_Setup {
                bossFightStartingPowers as public;
            }

            public array $bonus_cards;

            public function __construct(array $bonusCards)
            {
                $this->bonus_cards = $bonusCards;
            }
        };

        $powers = $setup->bossFightStartingPowers();

        $this->assertCount(2, $powers);
        $this->assertCount(2, array_unique($powers));
        foreach ($powers as $powerKey) {
            $this->assertArrayHasKey($powerKey, $bonus_cards);
            $this->assertSame(3, (int) $bonus_cards[$powerKey]['rank']);
        }
    }

    public function testSetupActivatesFirstCreatedPlayer(): void
    {
        $gameSource = self::readFile(dirname(__DIR__) . '/hacknslash.game.php');

        $this->assertStringContainsString('$this->gamestate->changeActivePlayer((int) array_key_first($players));', $gameSource);
    }
}
