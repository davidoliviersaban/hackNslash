-- BGA framework database model for HackNSlash.
-- Keep this file name unchanged: BGA Studio reads it to create the database schema.

ALTER TABLE `player` ADD `player_health` SMALLINT UNSIGNED NOT NULL DEFAULT '10';
ALTER TABLE `player` ADD `player_action_points` TINYINT UNSIGNED NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `player_position_x` SMALLINT NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `player_position_y` SMALLINT NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `player_level` TINYINT UNSIGNED NOT NULL DEFAULT '1';

CREATE TABLE IF NOT EXISTS `card` (
    `card_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `card_type` VARCHAR(32) NOT NULL COMMENT 'bonus, monster, boss, loot, etc.',
    `card_type_arg` INT(11) NOT NULL COMMENT 'Static material id',
    `card_location` VARCHAR(32) NOT NULL COMMENT 'deck, hand_PLAYER, discard, market, monster_row, etc.',
    `card_location_arg` INT(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `tile` (
    `tile_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tile_x` SMALLINT NOT NULL,
    `tile_y` SMALLINT NOT NULL,
    `tile_type` VARCHAR(32) NOT NULL COMMENT 'floor, wall, entry, exit, hole, spikes, etc.',
    `tile_state` VARCHAR(32) NOT NULL DEFAULT 'hidden' COMMENT 'hidden, revealed, cleared',
    `tile_level` TINYINT UNSIGNED NOT NULL DEFAULT '1',
    PRIMARY KEY (`tile_id`),
    UNIQUE KEY `coords_level` (`tile_x`, `tile_y`, `tile_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `entity` (
    `entity_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(32) NOT NULL COMMENT 'hero, monster, boss, token',
    `entity_type_arg` INT(11) NOT NULL DEFAULT '0' COMMENT 'Static material id',
    `entity_owner` INT(10) UNSIGNED DEFAULT NULL COMMENT 'Player id for heroes/summons',
    `entity_tile_id` INT(10) UNSIGNED DEFAULT NULL,
    `entity_health` SMALLINT NOT NULL DEFAULT '1',
    `entity_state` VARCHAR(32) NOT NULL DEFAULT 'active',
    PRIMARY KEY (`entity_id`),
    KEY `fk_entity_tile` (`entity_tile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `global_var` (
    `var_name` VARCHAR(64) NOT NULL,
    `var_value` TEXT,
    PRIMARY KEY (`var_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
