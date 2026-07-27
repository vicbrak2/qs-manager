-- Datos de contacto y logistica del equipo. La comuna base es donde se
-- recoge habitualmente a cada profesional: al elegirla en un tramo de la
-- bitacora, el destino se completa solo.
-- (aliases ya existia sin uso: guarda las otras formas en que la planilla
-- escribe su nombre, para que el sync no la duplique -- ej. Yeimy/Yeimi.)

alter table qs_staff
    add column if not exists phone varchar(40),
    add column if not exists comuna_base varchar(120);
