-- @up
ALTER TABLE account ADD COLUMN email TEXT;

-- @down
ALTER TABLE account DROP COLUMN email;
