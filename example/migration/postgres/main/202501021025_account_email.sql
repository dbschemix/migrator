-- @up
ALTER TABLE IF EXISTS account ADD COLUMN email varchar(256);

-- @down
ALTER TABLE IF EXISTS account DROP COLUMN email;
