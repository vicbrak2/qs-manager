-- Plan de traslado de la bitacora: horario del servicio, tramos con tiempos
-- registrados y campos narrativos del documento que se envia al equipo.
-- La hora de salida/llegada NO se persiste: se calcula siempre desde
-- hora_inicio_servicio y los tramos (regla: llegar 15 min antes + 15 min
-- de holgura diluida en el viaje).

alter table qs_bitacoras
    add column if not exists hora_inicio_servicio varchar(20),
    add column if not exists hora_fin_servicio varchar(20),
    add column if not exists tramos jsonb not null default '[]'::jsonb,
    add column if not exists objetivo text,
    add column if not exists consideraciones text;
