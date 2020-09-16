create view users__v(id, login, fio, boss_id, roles, add_users, all_connected_users, interface, favorite, is_outer, is_del) as
SELECT users.id,
       (users.login ->> 'v'::text)               AS login,
       (users.fio ->> 'v'::text)                 AS fio,
       ((users.boss_id ->> 'v'::text))::integer  AS boss_id,
       (users.roles ->> 'v'::text)               AS roles,
       (users.add_users ->> 'v'::text)           AS add_users,
       (users.all_connected_users ->> 'v'::text) AS all_connected_users,
       (users.interface ->> 'v'::text)           AS interface,
       (users.favorite ->> 'v'::text)            AS favorite,
       ((users.is_outer ->> 'v'::text))::boolean AS is_outer,
       users.is_del
FROM users;


drop view if exists tree__v;
create view tree__v(id, parent_id, title, path, top, ord, is_del, default_table, type, link, icon, roles) as
    WITH RECURSIVE temp1(id, parent_id, title, path, ord, top, is_del, default_table, type, link, icon, roles) AS (
        SELECT t1.id,
               ((t1.parent_id ->> 'v'::text))::integer            AS int4,
               (t1.title ->> 'v'::text),
               ((t1.title ->> 'v'::text))::character varying(150) AS "varchar",
               ((t1.ord ->> 'v'::text))::integer                  AS int4,
               CASE
                   WHEN (t1.is_del = true) THEN 0
                   ELSE t1.id
                   END                                            AS id,
               t1.is_del,
               ((t1.default_table ->> 'v'::text))::integer        AS default_table,
               (t1.type ->> 'v'::text)                            AS type,
               (t1.link ->> 'v'::text)                            AS link,
               (t1.icon ->> 'v'::text)                            AS icon,
               (t1.roles ->> 'v'::text)                           AS roles
        FROM tree t1
        WHERE ((t1.parent_id ->> 'v'::text) IS NULL)
        UNION
        SELECT t2.id,
               ((t2.parent_id ->> 'v'::text))::integer                                                       AS int4,
               (t2.title ->> 'v'::text),
               ((((temp1_1.path)::text || ' → '::text) || (t2.title ->> 'v'::text)))::character varying(150) AS "varchar",
               ((t2.ord ->> 'v'::text))::integer                                                             AS int4,
               CASE
                   WHEN (t2.is_del = true) THEN 0
                   ELSE temp1_1.top
                   END                                                                                       AS top,
               t2.is_del,
               ((t2.default_table ->> 'v'::text))::integer                                                   AS default_table,
               (t2.type ->> 'v'::text)                                                                       AS type,
               (t2.link ->> 'v'::text)                                                                       AS link,
               (t2.icon ->> 'v'::text)                                                                       AS icon,
               (t2.roles ->> 'v'::text)                                                                      AS roles
        FROM (tree t2
                 JOIN temp1 temp1_1 ON ((temp1_1.id = ((t2.parent_id ->> 'v'::text))::integer)))
    )
    SELECT temp1.id,
           temp1.parent_id,
           temp1.title,
           temp1.path,
           temp1.top,
           temp1.ord,
           temp1.is_del,
           temp1.default_table,
           temp1.type,
           temp1.link,
           temp1.icon,
           temp1.roles
    FROM temp1
    ORDER BY temp1.ord;