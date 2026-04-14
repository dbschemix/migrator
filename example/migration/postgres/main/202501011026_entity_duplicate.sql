-- @skip
-- проверяем что секция skip будет проигнорирована
CREATE TABLE public.entity_duplicate (
    id serial NOT NULL,
    parent_id integer NOT NULL,
    created_at timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT entity_duplicate_pkey PRIMARY KEY (id)
);

-- @down
DROP TABLE IF EXISTS public.entity_duplicate;
