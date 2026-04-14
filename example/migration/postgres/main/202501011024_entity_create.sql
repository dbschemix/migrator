-- @up
CREATE TABLE IF NOT EXISTS public.entity (
    id serial NOT NULL,
    parent_id integer NOT NULL,
    created_at timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT entity_pkey PRIMARY KEY (id)
);
CREATE INDEX IF NOT EXISTS "I_entity_parent_id" ON public.entity USING btree (parent_id);

-- @down
DROP INDEX IF EXISTS I_entity_parent_id;
DROP TABLE IF EXISTS public.entity;
