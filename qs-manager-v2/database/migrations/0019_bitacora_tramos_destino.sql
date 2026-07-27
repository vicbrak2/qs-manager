-- Los tramos pasaron de "nombre libre + comuna opcional" a "destino":
-- el tramo es "viajar hasta un punto", y encadenando destinos sale el orden
-- de traslado sin interpretar texto. Convierte las filas ya guardadas.

update qs_bitacoras
set tramos = (
    select coalesce(
        jsonb_agg(
            case
                when tramo ? 'destino' then tramo
                else (tramo - 'nombre' - 'comuna')
                     || jsonb_build_object('destino', coalesce(tramo->>'comuna', tramo->>'nombre'))
            end
            order by indice
        ),
        '[]'::jsonb
    )
    from jsonb_array_elements(tramos) with ordinality as t(tramo, indice)
)
where jsonb_array_length(tramos) > 0;
