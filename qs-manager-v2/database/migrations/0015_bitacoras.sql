-- Modulo Bitacora nativo de V2: registro operativo de un servicio (equipo,
-- logistica de traslado, pricing). Reemplaza al modulo Bitacora de V1
-- (WordPress CPT). Distinto de qs_sheet_bitacora_rows, que es la replica
-- read-only de la hoja "Bitácora QS — Servicios".

create table if not exists qs_bitacoras (
    id bigserial primary key,
    fecha_servicio varchar(40) not null,
    tipo_servicio varchar(120) not null,
    mua_id bigint references qs_staff(id),
    estilista_id bigint references qs_staff(id),
    clienta_nombre varchar(160) not null,
    direccion_servicio varchar(240) not null,
    punto_salida varchar(240) not null,
    orden_recogida text,
    tiempo_traslado_min integer not null default 0,
    hora_llegada varchar(20),
    notas_logisticas text,
    costo_staff_clp integer not null default 0,
    precio_cliente_clp integer not null default 0,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create table if not exists qs_bitacora_notes (
    id bigserial primary key,
    bitacora_id bigint not null references qs_bitacoras(id) on delete cascade,
    message text not null,
    author_user_id bigint,
    created_at timestamptz not null default now()
);

create index if not exists qs_bitacora_notes_bitacora_idx
    on qs_bitacora_notes(bitacora_id);
