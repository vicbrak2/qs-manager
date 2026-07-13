create table if not exists qs_sync_runs (
    id bigserial primary key,
    status varchar(50) not null,
    mode varchar(50) not null,
    started_at timestamptz not null default now(),
    finished_at timestamptz,
    total_sources integer not null default 0,
    completed_sources integer not null default 0,
    failed_sources integer not null default 0,
    total_rows_seen integer not null default 0,
    total_rows_imported integer not null default 0,
    triggered_by varchar(255),
    error_summary text
);

alter table qs_sheet_import_runs
    add column if not exists sync_run_id bigint references qs_sync_runs(id) on delete set null,
    add column if not exists duration_ms integer,
    add column if not exists attempts integer not null default 1,
    add column if not exists http_code integer,
    add column if not exists message_public text;
