-- BGA framework database model for HackNSlash.
-- Keep this file name unchanged: BGA Studio reads it to create the database schema.

ALTER TABLE `player` ADD `player_health` SMALLINT UNSIGNED NOT NULL DEFAULT '10';
ALTER TABLE `player` ADD `player_action_points` TINYINT UNSIGNED NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `player_position_x` SMALLINT NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `player_position_y` SMALLINT NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `player_level` TINYINT UNSIGNED NOT NULL DEFAULT '1';
ALTER TABLE `player` ADD `player_free_move_available` TINYINT UNSIGNED NOT NULL DEFAULT '1';
ALTER TABLE `player` ADD `player_main_action_available` TINYINT UNSIGNED NOT NULL DEFAULT '1';

CREATE TABLE IF NOT EXISTS `card` (
    `card_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `card_type` VARCHAR(32) NOT NULL COMMENT 'bonus, monster, boss, loot, etc.',
    `card_type_arg` INT(11) NOT NULL COMMENT 'Static material id',
    `card_location` VARCHAR(32) NOT NULL COMMENT 'deck, hand_PLAYER, discard, market, monster_row, etc.',
    `card_location_arg` INT(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `tile` (
    `tile_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tile_x` SMALLINT NOT NULL,
    `tile_y` SMALLINT NOT NULL,
    `tile_type` VARCHAR(32) NOT NULL COMMENT 'floor, wall, entry, exit, hole, spikes, etc.',
    `tile_state` VARCHAR(32) NOT NULL DEFAULT 'hidden' COMMENT 'hidden, revealed, cleared',
    `tile_level` TINYINT UNSIGNED NOT NULL DEFAULT '1',
    PRIMARY KEY (`tile_id`),
    UNIQUE KEY `coords_level` (`tile_x`, `tile_y`, `tile_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `entity` (
    `entity_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(32) NOT NULL COMMENT 'hero, monster, boss, token',
    `entity_type_arg` INT(11) NOT NULL DEFAULT '0' COMMENT 'Static material id',
    `entity_owner` INT(10) UNSIGNED DEFAULT NULL COMMENT 'Player id for heroes/summons',
    `entity_tile_id` INT(10) UNSIGNED DEFAULT NULL,
    `entity_health` SMALLINT NOT NULL DEFAULT '1',
    `entity_state` VARCHAR(32) NOT NULL DEFAULT 'active',
    `entity_monster_size` VARCHAR(16) DEFAULT NULL COMMENT 'small, big, boss',
    `entity_boss_key` VARCHAR(32) DEFAULT NULL COMMENT 'slasher, striker',
    `entity_phase` TINYINT UNSIGNED NOT NULL DEFAULT '0',
    `entity_status` VARCHAR(32) DEFAULT NULL COMMENT 'stuck, etc.',
    `entity_on_death` VARCHAR(32) DEFAULT NULL COMMENT 'explode, etc.',
    `entity_shield_broken` TINYINT UNSIGNED NOT NULL DEFAULT '0',
    `entity_slot` TINYINT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`entity_id`),
    KEY `fk_entity_tile` (`entity_tile_id`),
    KEY `entity_type_owner` (`entity_type`, `entity_owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `player_power` (
    `player_power_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id` INT(10) UNSIGNED NOT NULL,
    `power_slot` TINYINT UNSIGNED NOT NULL,
    `power_key` VARCHAR(32) NOT NULL,
    `power_cooldown` TINYINT UNSIGNED NOT NULL DEFAULT '0',
    PRIMARY KEY (`player_power_id`),
    UNIQUE KEY `player_power_slot_unique` (`player_id`, `power_slot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `free_chain` (
    `chain_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `active_event_chain` TEXT NOT NULL,
    `used_action_keys` TEXT NOT NULL,
    `passed_player_ids` TEXT NOT NULL,
    PRIMARY KEY (`chain_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `global_var` (
    `var_name` VARCHAR(64) NOT NULL,
    `var_value` TEXT,
    PRIMARY KEY (`var_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
