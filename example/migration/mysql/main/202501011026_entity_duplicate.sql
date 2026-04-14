-- @skip
-- проверяем что секция skip будет проигнорирована
CREATE TABLE `entity_duplicate`
(
    `id`         int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name`       varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` timestamp                              NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp                                       DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS `entity_duplicate`;
