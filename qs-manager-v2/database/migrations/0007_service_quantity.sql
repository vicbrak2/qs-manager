alter table qs_services
    add column if not exists quantity integer not null default 1;

alter table qs_sheet_service_catalog_rows
    add column if not exists quantity integer not null default 1;

alter table qs_services
    drop constraint if exists qs_services_quantity_positive;

alter table qs_services
    add constraint qs_services_quantity_positive check (quantity > 0);

alter table qs_sheet_service_catalog_rows
    drop constraint if exists qs_sheet_service_catalog_quantity_positive;

alter table qs_sheet_service_catalog_rows
    add constraint qs_sheet_service_catalog_quantity_positive check (quantity > 0);
