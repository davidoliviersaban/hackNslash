<?php

use PHPUnit\Framework\TestCase;

final class DbModelTest extends TestCase
{
    private string $sql;

    protected function setUp(): void
    {
        $this->sql = file_get_contents(dirname(__DIR__) . '/dbmodel.sql');
    }

    public function testPlayerStoresRoundActionFlags(): void
    {
        $this->assertStringContainsString('player_free_move_available', $this->sql);
        $this->assertStringContainsString('player_main_action_available', $this->sql);
    }

    public function testPlayerPowerTableStoresCooldowns(): void
    {
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `player_power`', $this->sql);
        $this->assertStringContainsString('`player_power_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT', $this->sql);
        $this->assertStringContainsString('`power_slot` TINYINT UNSIGNED NOT NULL', $this->sql);
        $this->assertStringContainsString('`power_key` VARCHAR(32) NOT NULL', $this->sql);
        $this->assertStringContainsString('`power_cooldown` TINYINT UNSIGNED NOT NULL DEFAULT \'0\'', $this->sql);
        $this->assertStringContainsString('UNIQUE KEY `player_power_slot_unique` (`player_id`, `power_slot`)', $this->sql);
    }

    public function testFreeChainTableStoresActiveEventChain(): void
    {
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `free_chain`', $this->sql);
        $this->assertStringContainsString('`active_event_chain` TEXT NOT NULL', $this->sql);
        $this->assertStringContainsString('`used_action_keys` TEXT NOT NULL', $this->sql);
        $this->assertStringContainsString('`passed_player_ids` TEXT NOT NULL', $this->sql);
    }

    public function testEntityStoresMonsterRuntimeState(): void
    {
        $this->assertStringContainsString('`entity_monster_size` VARCHAR(16) DEFAULT NULL', $this->sql);
        $this->assertStringContainsString('`entity_boss_key` VARCHAR(32) DEFAULT NULL', $this->sql);
        $this->assertStringContainsString('`entity_phase` TINYINT UNSIGNED NOT NULL DEFAULT \'0\'', $this->sql);
        $this->assertStringContainsString('`entity_status` VARCHAR(32) DEFAULT NULL', $this->sql);
        $this->assertStringContainsString('`entity_on_death` VARCHAR(32) DEFAULT NULL', $this->sql);
        $this->assertStringContainsString('`entity_shield_broken` TINYINT UNSIGNED NOT NULL DEFAULT \'0\'', $this->sql);
        $this->assertStringContainsString('`entity_slot` TINYINT UNSIGNED DEFAULT NULL', $this->sql);
    }
}
