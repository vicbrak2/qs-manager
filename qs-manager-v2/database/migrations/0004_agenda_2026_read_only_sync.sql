create table if not exists qs_sheet_agenda_month_rows (
    id bigserial primary key,
    import_run_id bigint references qs_sheet_import_runs(id),
    source_sheet varchar(120) not null,
    source_row integer not null,
    staff_name varchar(160),
    day_label varchar(80),
    service_date date,
    service_time time,
    service_name varchar(240),
    quantity integer,
    customer_name varchar(160),
    customer_phone varchar(60),
    address varchar(240),
    comuna varchar(120),
    trial_date date,
    trial_time time,
    trial_status varchar(80),
    transfer_value numeric(12, 2),
    deposit_amount numeric(12, 2),
    deposit_date date,
    service_value numeric(12, 2),
    total_service numeric(12, 2),
    balance_due numeric(12, 2),
    action varchar(80),
    event_status varchar(80),
    calendar_event_id varchar(180),
    payment_status varchar(80),
    created_at timestamptz not null default now()
);

create table if not exists qs_sheet_agenda_value_rows (
    id bigserial primary key,
    import_run_id bigint references qs_sheet_import_runs(id),
    source_row integer not null,
    service_name varchar(240) not null,
    makeup_cost numeric(12, 2),
    hair_cost numeric(12, 2),
    trial_makeup_cost numeric(12, 2),
    trial_hair_cost numeric(12, 2),
    sale_price numeric(12, 2),
    profit numeric(12, 2),
    observations text,
    created_at timestamptz not null default now()
);

create table if not exists qs_sheet_workshop_rows (
    id bigserial primary key,
    import_run_id bigint references qs_sheet_import_runs(id),
    source_row integer not null,
    workshop_date date,
    customer_name varchar(160),
    customer_phone varchar(60),
    payment_amount numeric(12, 2),
    payment_date date,
    notes text,
    created_at timestamptz not null default now()
);

insert into qs_sheet_sources (spreadsheet_id, spreadsheet_title, sheet_name, sheet_gid, purpose)
values
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Valores', 0, 'agenda_values'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Talleres', 1004626842, 'workshops'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Enero', 1600012026, 'agenda_month'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Febrero', 297232105, 'agenda_month'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Marzo', 817931728, 'agenda_month'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Abril', 1913010066, 'agenda_month'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Mayo', 2068172479, 'agenda_month'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Junio', 544909107, 'agenda_month'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Julio', 2073502017, 'agenda_month'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Agosto', 301380220, 'agenda_month'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Septiembre', 2086235780, 'agenda_month'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Octubre', 1600102026, 'agenda_month'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Noviembre', 1600112026, 'agenda_month'),
    ('1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4', 'Agenda 2026', 'Diciembre', 1600122026, 'agenda_month')
on conflict (spreadsheet_id, sheet_name) do nothing;
