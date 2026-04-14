-- @skip
-- проверяем что секция skip будет проигнорирована
CREATE TABLE IF NOT EXISTS entity
(
    id INTEGER PRIMARY KEY,
    name TEXT
);

-- @down
DROP TABLE IF EXISTS entity;
