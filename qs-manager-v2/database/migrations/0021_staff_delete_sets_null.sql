-- Borrar una profesional estaba prohibido si tenia CUALQUIER servicio, aunque
-- fuera de hace un año. La regla real del estudio es mas acotada: solo
-- bloquea si tiene servicios PENDIENTES (esa validacion vive en
-- DeleteStaffMember); los pasados quedan desvinculados y el historial de la
-- reserva/bitacora se conserva.

alter table qs_bookings drop constraint if exists qs_bookings_staff_id_fkey;
alter table qs_bookings
    add constraint qs_bookings_staff_id_fkey
    foreign key (staff_id) references qs_staff(id) on delete set null;

alter table qs_bookings drop constraint if exists qs_bookings_estilista_id_fkey;
alter table qs_bookings
    add constraint qs_bookings_estilista_id_fkey
    foreign key (estilista_id) references qs_staff(id) on delete set null;

alter table qs_bitacoras drop constraint if exists qs_bitacoras_mua_id_fkey;
alter table qs_bitacoras
    add constraint qs_bitacoras_mua_id_fkey
    foreign key (mua_id) references qs_staff(id) on delete set null;

alter table qs_bitacoras drop constraint if exists qs_bitacoras_estilista_id_fkey;
alter table qs_bitacoras
    add constraint qs_bitacoras_estilista_id_fkey
    foreign key (estilista_id) references qs_staff(id) on delete set null;
