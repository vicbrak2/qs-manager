create table if not exists qs_sheet_fixed_expense_rows (
    id bigserial primary key,
    import_run_id bigint not null references qs_sheet_import_runs(id) on delete cascade,
    source_row integer not null,
    concept varchar(240) not null,
    category varchar(120),
    amount numeric(12, 2),
    expense_type varchar(80),
    periodicity varchar(40),
    expense_status varchar(80),
    notes text,
    base_period varchar(7),
    created_at timestamptz not null default now()
);

create index if not exists qs_sheet_fixed_expense_rows_run_idx
    on qs_sheet_fixed_expense_rows(import_run_id);

insert into qs_sheet_sources (
    spreadsheet_id, spreadsheet_title, sheet_name, sheet_gid, purpose
) values (
    '1Nl9hsMADAjJFJ_GPLGxvHenXjb91hLzp5EO-sDar0WE',
    'Seguimiento Contable - Margen por Servicio',
    'Gastos_Fijos',
    1900014001,
    'fixed_expenses'
)
on conflict (spreadsheet_id, sheet_name) do update set
    sheet_gid = excluded.sheet_gid,
    purpose = excluded.purpose;

create or replace view v_fixed_expense_latest as
select expense.*
from qs_sheet_fixed_expense_rows expense
join v_sheet_import_runs_latest run on run.id = expense.import_run_id;

drop index if exists qs_finance_entries_source_unique;
create unique index qs_finance_entries_source_unique
    on qs_finance_entries(entry_type, source_type, source_sheet, source_row, occurred_on)
    where source_sheet is not null;
