-- Los servicios llevan hasta dos profesionales (maquilladora y estilista) y
-- la planilla las registra juntas en un campo ("Cami - Paz"). Hasta ahora la
-- reserva solo guardaba una: la estilista se perdia en la proyeccion.

alter table qs_bookings
    add column if not exists estilista_id bigint references qs_staff(id);

create index if not exists qs_bookings_estilista_idx on qs_bookings(estilista_id);
