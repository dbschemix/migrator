-- @up
CREATE TABLE IF NOT EXISTS public.account (
    id serial NOT NULL,
    name varchar(256) NOT NULL,
    created_at timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT account_pkey PRIMARY KEY (id)
);

-- @down
DROP TABLE IF EXISTS public.account;
