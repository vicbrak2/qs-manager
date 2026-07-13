alter table qs_services
    add column if not exists sheet_external_id varchar(80),
    add column if not exists sale_price numeric(12, 2),
    add column if not exists total_cost numeric(12, 2),
    add column if not exists utility numeric(12, 2),
    add column if not exists margin_percent numeric(7, 4),
    add column if not exists margin_status varchar(40),
    add column if not exists source_sheet varchar(120),
    add column if not exists source_row integer;

alter table qs_staff
    add column if not exists aliases text;

alter table qs_bookings
    add column if not exists address varchar(240),
    add column if not exists comuna varchar(120),
    add column if not exists service_value numeric(12, 2),
    add column if not exists transfer_value numeric(12, 2),
    add column if not exists deposit_amount numeric(12, 2),
    add column if not exists total_service numeric(12, 2),
    add column if not exists balance_due numeric(12, 2),
    add column if not exists payment_status varchar(40),
    add column if not exists service_status varchar(40),
    add column if not exists contract_id varchar(80),
    add column if not exists milestone varchar(80),
    add column if not exists cash_group varchar(80),
    add column if not exists calendar_event_id varchar(180),
    add column if not exists agenda_reference varchar(180),
    add column if not exists gas_last_sync_at timestamptz,
    add column if not exists gas_last_sync_status varchar(40),
    add column if not exists gas_last_sync_message text;

create table if not exists qs_sheet_sources (
    id bigserial primary key,
    spreadsheet_id varchar(160) not null,
    spreadsheet_title varchar(240) not null,
    sheet_name varchar(160) not null,
    sheet_gid bigint,
    purpose varchar(120) not null,
    last_synced_at timestamptz,
    created_at timestamptz not null default now(),
    unique (spreadsheet_id, sheet_name)
);

create table if not exists qs_sheet_import_runs (
    id bigserial primary key,
    source_id bigint references qs_sheet_sources(id),
    status varchar(40) not null,
    rows_seen integer not null default 0,
    rows_imported integer not null default 0,
    error_message text,
    started_at timestamptz not null default now(),
    finished_at timestamptz
);

create table if not exists qs_sheet_service_catalog_rows (
    id bigserial primary key,
    import_run_id bigint references qs_sheet_import_runs(id),
    source_row integer not null,
    active boolean,
    category varchar(120),
    service_name varchar(240) not null,
    sale_price numeric(12, 2),
    payment_mua numeric(12, 2),
    payment_stylist numeric(12, 2),
    trial_mua numeric(12, 2),
    trial_stylist numeric(12, 2),
    materials numeric(12, 2),
    logistics numeric(12, 2),
    transfer_value numeric(12, 2),
    other_costs numeric(12, 2),
    total_cost numeric(12, 2),
    utility numeric(12, 2),
    margin_percent numeric(7, 4),
    margin_status varchar(40),
    observations text,
    created_at timestamptz not null default now()
);

create table if not exists qs_sheet_bitacora_rows (
    id bigserial primary key,
    import_run_id bigint references qs_sheet_import_runs(id),
    source_row integer not null,
    qs_external_id varchar(80) not null,
    service_date date,
    service_time time,
    type varchar(80),
    staff_name varchar(160),
    service_name varchar(240),
    service_type varchar(120),
    customer_name varchar(160),
    comuna varchar(120),
    transfer_value numeric(12, 2),
    deposit_amount numeric(12, 2),
    service_value numeric(12, 2),
    total_service numeric(12, 2),
    balance_due numeric(12, 2),
    payment_method varchar(120),
    payment_status varchar(80),
    service_status varchar(80),
    observations text,
    address varchar(240),
    calendar_event_id varchar(180),
    agenda_reference varchar(180),
    contract_id varchar(80),
    milestone varchar(80),
    cash_group varchar(80),
    contract_status varchar(80),
    contract_reserve numeric(12, 2),
    created_at timestamptz not null default now()
);

insert into qs_sheet_sources (spreadsheet_id, spreadsheet_title, sheet_name, sheet_gid, purpose)
values
    ('1Nl9hsMADAjJFJ_GPLGxvHenXjb91hLzp5EO-sDar0WE', 'Seguimiento Contable - Margen por Servicio', 'Servicios', 839064078, 'service_catalog'),
    ('1Nl9hsMADAjJFJ_GPLGxvHenXjb91hLzp5EO-sDar0WE', 'Seguimiento Contable - Margen por Servicio', 'Seguimiento Caja', 513021861, 'cash_tracking'),
    ('1Nl9hsMADAjJFJ_GPLGxvHenXjb91hLzp5EO-sDar0WE', 'Seguimiento Contable - Margen por Servicio', 'Gastos Operativos', 1642061717, 'operational_expenses'),
    ('1cXOd9imvZGA5oj-Hlk8QHwB48cbx5hNptnGi0HdjMWE', 'Bitácora QS — Servicios', 'Bitácora QS — Servicios', 1880538608, 'bitacora')
on conflict (spreadsheet_id, sheet_name) do nothing;
