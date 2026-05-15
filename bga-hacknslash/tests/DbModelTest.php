<?php

use PHPUnit\Framework\TestCase;

final class DbModelTest extends TestCase
{
    private string $sql;

    protected function setUp(): void
    {
        $contents = '';
        $file = new SplFileObject(dirname(__DIR__) . '/dbmodel.sql');
        while (!$file->eof()) {
            $contents .= $file->fgets();
        }
        $this->sql = $contents;
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
        $this->assertStringContainsString('`entity_has_shield` TINYINT UNSIGNED NOT NULL DEFAULT \'0\'', $this->sql);
        $this->assertStringContainsString('`entity_shield_broken` TINYINT UNSIGNED NOT NULL DEFAULT \'0\'', $this->sql);
        $this->assertStringContainsString('`entity_slot` TINYINT UNSIGNED DEFAULT NULL', $this->sql);
    }

    public function testFinalComboTableStoresEndGameSnapshots(): void
    {
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `final_combo`', $this->sql);
        $this->assertStringContainsString('`scenario` VARCHAR(16) NOT NULL', $this->sql);
        $this->assertStringContainsString('`difficulty` VARCHAR(16) NOT NULL', $this->sql);
        $this->assertStringContainsString('`player_count` TINYINT UNSIGNED NOT NULL', $this->sql);
        $this->assertStringContainsString('`boss_key` VARCHAR(32) DEFAULT NULL', $this->sql);
        $this->assertStringContainsString('`outcome` VARCHAR(8) NOT NULL', $this->sql);
        $this->assertStringContainsString('`combo_key` VARCHAR(255) NOT NULL', $this->sql);
        $this->assertStringContainsString('`combo_json` TEXT NOT NULL', $this->sql);
        $this->assertStringContainsString('KEY `combo_context_outcome` (`scenario`, `difficulty`, `outcome`, `player_count`)', $this->sql);
        $this->assertStringContainsString('KEY `combo_boss_outcome` (`boss_key`, `outcome`)', $this->sql);
        $this->assertStringContainsString('KEY `combo_key_idx` (`combo_key`(191))', $this->sql);
    }

    public function testPowerHistoryTableStoresPlayedAndTakenPowerKeys(): void
    {
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `power_history`', $this->sql);
        $this->assertStringContainsString('`player_id` INT(10) UNSIGNED NOT NULL', $this->sql);
        $this->assertStringContainsString('`scenario` VARCHAR(16) NOT NULL', $this->sql);
        $this->assertStringContainsString('`difficulty` VARCHAR(16) NOT NULL', $this->sql);
        $this->assertStringContainsString('`power_key` VARCHAR(32) NOT NULL', $this->sql);
        $this->assertStringContainsString('`event_type` VARCHAR(8) NOT NULL', $this->sql);
        $this->assertStringContainsString('KEY `power_history_event` (`scenario`, `difficulty`, `event_type`, `power_key`)', $this->sql);
    }

    public function testWinStreakTableStoresContextualStreaks(): void
    {
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `win_streak`', $this->sql);
        $this->assertStringContainsString('`scenario` VARCHAR(16) NOT NULL', $this->sql);
        $this->assertStringContainsString('`difficulty` VARCHAR(16) NOT NULL', $this->sql);
        $this->assertStringContainsString('`player_count` TINYINT UNSIGNED NOT NULL', $this->sql);
        $this->assertStringContainsString('`current_streak` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'', $this->sql);
        $this->assertStringContainsString('`best_streak` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'', $this->sql);
        $this->assertStringContainsString('UNIQUE KEY `win_streak_context` (`scenario`, `difficulty`, `player_count`)', $this->sql);
    }
}
