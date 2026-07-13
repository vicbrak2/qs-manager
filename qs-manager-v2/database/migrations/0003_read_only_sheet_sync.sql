alter table qs_bookings
    add column if not exists sheet_external_id varchar(80),
    add column if not exists source_sheet varchar(120),
    add column if not exists source_row integer,
    add column if not exists sheets_last_import_at timestamptz;

create unique index if not exists qs_services_source_unique
    on qs_services (source_sheet, source_row)
    where source_sheet is not null and source_row is not null;

create unique index if not exists qs_bookings_source_unique
    on qs_bookings (source_sheet, source_row)
    where source_sheet is not null and source_row is not null;

create unique index if not exists qs_bookings_sheet_external_unique
    on qs_bookings (sheet_external_id)
    where sheet_external_id is not null;

create table if not exists qs_sheet_cash_tracking_rows (
    id bigserial primary key,
    import_run_id bigint references qs_sheet_import_runs(id),
    source_row integer not null,
    service_external_id varchar(80),
    service_date date,
    service_names text,
    customer_name varchar(160),
    comuna varchar(120),
    deposit_amount numeric(12, 2),
    total_services numeric(12, 2),
    balance_due numeric(12, 2),
    operating_expenses numeric(12, 2),
    payment_status varchar(80),
    service_status varchar(80),
    created_at timestamptz not null default now()
);

create table if not exists qs_sheet_operational_expense_rows (
    id bigserial primary key,
    import_run_id bigint references qs_sheet_import_runs(id),
    source_row integer not null,
    selected_service text,
    expense_external_id varchar(80),
    contract_id varchar(80),
    service_type varchar(120),
    service_status varchar(80),
    expense_date date,
    concept varchar(180),
    amount numeric(12, 2),
    observations text,
    expense_status varchar(80),
    customer_name varchar(160),
    service_name varchar(240),
    created_at timestamptz not null default now()
);
