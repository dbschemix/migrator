-- @up
ALTER TABLE `account` ADD COLUMN `email` varchar(256);

-- @down
ALTER TABLE `account` DROP COLUMN `email`;
