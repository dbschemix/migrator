-- @up
CREATE UNIQUE INDEX `UI_account_email` ON `account` (`email`);

-- @down
DROP INDEX `UI_account_email` ON `account`;
