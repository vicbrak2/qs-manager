alter table qs_bitacoras
    add column if not exists professional_ids jsonb not null default '[]'::jsonb;

