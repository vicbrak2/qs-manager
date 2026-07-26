alter table qs_bitacoras
    add column if not exists booking_id bigint references qs_bookings(id) on delete set null,
    add column if not exists booking_external_id varchar(80);

create index if not exists qs_bitacoras_booking_idx
    on qs_bitacoras(booking_id);

create unique index if not exists qs_bitacoras_booking_unique
    on qs_bitacoras(booking_id) where booking_id is not null;

create index if not exists qs_bitacoras_booking_external_idx
    on qs_bitacoras(booking_external_id) where booking_external_id is not null;
