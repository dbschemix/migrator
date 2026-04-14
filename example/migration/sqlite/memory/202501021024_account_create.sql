-- @up
CREATE TABLE IF NOT EXISTS account
(
    id INTEGER PRIMARY KEY,
    name TEXT
);

-- @down
DROP TABLE IF EXISTS account;
