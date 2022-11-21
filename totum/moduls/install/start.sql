create table tables
(
    id      serial                      not null
        constraint tables_pkey
            primary key,
    is_del  boolean default false       not null,

    updated jsonb   default '{}'::jsonb not null,
    header  jsonb   default '{}'::jsonb not null,

    name    jsonb   default '{
      "v": null
    }'::jsonb                           not null,
    type    jsonb   default '{
      "v": null
    }'::jsonb                           not null
);


INSERT INTO tables (type, name)
VALUES ('{
  "v": "simple"
}', '{
  "v": "tables"
}');
INSERT INTO tables (type, name)
VALUES ('{
  "v": "simple"
}', '{
  "v": "tables_fields"
}');
INSERT INTO tables (type, name)
VALUES ('{
  "v": "simple"
}', '{
  "v": "roles"
}');
INSERT INTO tables (type, name)
VALUES ('{
  "v": "simple"
}', '{
  "v": "tree"
}');

INSERT INTO tables (type, name)
VALUES ('{
  "v": "simple"
}', '{
  "v": "table_categories"
}');

INSERT INTO tables (type, name)
VALUES ('{
  "v": "simple"
}', '{
  "v": "settings"
}');

-- tables_fields

create table tables_fields
(
    id         serial                      not null
        constraint tables_fields_id_pk
            primary key,
    table_id   jsonb   default '{}'::jsonb not null,
    name       jsonb   default '{}'::jsonb not null,
    data       json    default '{}'::json  not null,
    ord        jsonb   default '{}'::jsonb not null,
    title      jsonb   default '{}'::jsonb not null,
    category   jsonb   default '{}'::jsonb not null,
    is_del     boolean default false       not null,
    data_src   jsonb   default '{
      "v": null
    }'::jsonb                              not null,
    table_name jsonb   default '{
      "v": null
    }'::jsonb                              not null,
    version    jsonb   default '{
      "v": null
    }'::jsonb                              not null
);

create index tables_fields___ind___table_id
    on tables_fields ((table_id ->> 'v'::text));

-- roles

create table roles
(
    id     serial                not null
        constraint roles_pkey
            primary key,
    is_del boolean default false not null
);
create table tree
(
    id        serial                not null
        constraint tree_pkey
            primary key,
    is_del    boolean default false not null,
    parent_id jsonb   default '{"v": null}'::jsonb not null,
    top jsonb   default '{"v": null}'::jsonb not null
);
create or replace view tree__v (id, parent_id, top) as

WITH RECURSIVE temp1(id, parent_id, top) AS (
    SELECT t1.id,
           ((t1.parent_id ->> 'v'::text))::integer AS parent_id,
           t1.id                                   as top
    FROM tree t1
    WHERE ((t1.parent_id ->> 'v'::text) IS NULL)
    UNION
    SELECT t2.id,
           ((t2.parent_id ->> 'v'::text))::integer AS parent_id,
           temp1_1.top                             AS top
    FROM (tree t2
             JOIN temp1 temp1_1 ON ((temp1_1.id = ((t2.parent_id ->> 'v'::text))::integer)))
)
SELECT temp1.id,
       temp1.parent_id,
       temp1.top
FROM temp1;

create table table_categories
(
    id        serial                not null
        constraint table_categories_pkey
            primary key,
    is_del    boolean default false not null
);
create table settings
(
    id        serial                not null
        constraint settings_pkey
            primary key,
    is_del    boolean default false not null
);

create view tables_fields__v as
SELECT tables_fields.id,
       ((tables_fields.table_id ->> 'v'::text))::integer AS table_id,
       (tables_fields.category ->> 'v'::text)            AS category,
       (tables_fields.title ->> 'v'::text)               AS title,
       (tables_fields.name ->> 'v'::text)                AS name,
       (tables_fields.data ->> 'v'::text)                AS data,
       ((tables_fields.ord ->> 'v'::text))::integer      AS ord,
       ((tables_fields.version ->> 'v'::text))           AS version,
       tables_fields.is_del
FROM tables_fields;


-- tables_nonproject_calcs

create table tables_nonproject_calcs
(
    tbl_name text                      not null
        constraint tables_nonproject_calcs_pkey
            primary key,
    tbl      jsonb default '{}'::jsonb not null,
    updated  jsonb                     not null
);

create unique index tables_nonproject_calcs_name_uindex
    on tables_nonproject_calcs (tbl_name);


-- tables_calcs_connects

create table tables_calcs_connects
(
    table_id        integer not null
        constraint tables_calcs_connects_tables_id_fk
            references tables
            on update cascade on delete cascade,
    cycle_id        integer not null,
    source_table_id integer not null
        constraint tables_calcs_connects_source_table_id_fk
            references tables
            on update cascade on delete cascade,
    id              serial  not null
        constraint tables_calcs_connects_id_pk
            primary key,
    cycles_table_id integer not null
);

comment on column tables_calcs_connects.table_id is 'calcs table';

create index tables_calcs_connects_cycles_table_id_index
    on tables_calcs_connects (cycles_table_id);

create unique index tables_calcs_connects_table_id_source_table_id_project_id_uinde
    on tables_calcs_connects (table_id, source_table_id, cycle_id);


create table "_log"
(
    tableid     integer                                                      not null,
    cycleid     integer,
    rowid       integer,
    field       text,
    modify_text text,
    v           text,
    action      integer                                                      not null,
    userid      integer                                                      not null,
    dt          timestamp default ('now'::text)::timestamp without time zone not null,
    from_code   boolean   default false                                      not null
);
create index _log_tableid_rowid_field_userid_index
    on _log (tableid, rowid, field, userid);
comment on column "_log".action is '1-add;2-modify;3-clear;4-delete';

create table "_comments_viewed"
(
    table_id   integer           not null,
    cycle_id   integer           not null,
    row_id     integer           not null,
    field_name text              not null,
    user_id    integer           not null,
    nums       integer default 0 not null
);

create unique index "_comments_viewed_table_id_row_id_user_id_cycle_id_field_name_ui"
    on "_comments_viewed" (table_id, row_id, user_id, cycle_id, field_name);


create table "_tmp_tables"
(
    touched    text    not null,
    user_id    integer not null,
    table_name text    not null,
    hash       text    not null,
    tbl        jsonb,
    updated    jsonb,
    constraint "_tmp_tables_pk"
        primary key (table_name, user_id, hash)
);

create table "_services_vars"
(
    name     text not null,
    value     text,
    mark     text,
    expire   timestamp without time zone
);
create UNIQUE INDEX _services_vars_name_index on _services_vars (name);
insert into "_services_vars" (name, value) values ('last-check-creator-notifications', '"' || TO_CHAR(NOW() :: DATE, 'yyyy-mm-dd') || '"');


