create table if not exists qs_staff (
    id bigserial primary key,
    display_name varchar(160) not null,
    role varchar(80) not null,
    active boolean not null default true,
    created_at timestamptz not null default now()
);

create table if not exists qs_services (
    id bigserial primary key,
    name varchar(160) not null,
    category varchar(80),
    duration_minutes integer,
    active boolean not null default true,
    created_at timestamptz not null default now()
);

create table if not exists qs_bookings (
    id bigserial primary key,
    service_id bigint references qs_services(id),
    staff_id bigint references qs_staff(id),
    customer_name varchar(160),
    customer_phone varchar(40),
    scheduled_for timestamptz,
    status varchar(40) not null default 'draft',
    created_at timestamptz not null default now()
);

create table if not exists qs_finance_entries (
    id bigserial primary key,
    booking_id bigint references qs_bookings(id),
    entry_type varchar(40) not null,
    amount numeric(12, 2) not null,
    description text,
    occurred_on date not null default current_date,
    created_at timestamptz not null default now()
);

create table if not exists qs_leads (
    id bigserial primary key,
    name varchar(160),
    phone varchar(40),
    source varchar(80),
    status varchar(40) not null default 'new',
    notes text,
    created_at timestamptz not null default now()
);

create table if not exists qs_bitacora_entries (
    id bigserial primary key,
    title varchar(180) not null,
    body text,
    related_booking_id bigint references qs_bookings(id),
    created_at timestamptz not null default now()
);

