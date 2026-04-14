-- @up
CREATE TABLE IF NOT EXISTS `entity` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `UI_entity_name` (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP INDEX `UI_entity_name` ON `entity`;
DROP TABLE IF EXISTS `entity`;
