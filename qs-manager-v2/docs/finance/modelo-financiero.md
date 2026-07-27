# Modelo financiero de QS Manager V2

Revisado y confirmado con el usuario el 2026-07-26. Antes de tocar cualquier
cálculo de finanzas, leer esto: los nombres de las métricas son sutiles y es
fácil sumar dos veces la misma plata.

## Fuente de verdad

Las finanzas se calculan **solas** desde las réplicas de las planillas, sin
carga manual:

| Concepto | Hoja de origen |
|---|---|
| Servicios (venta, abono, saldo, estado) | `Bitácora QS — Servicios` y `Agenda 2026` (meses) |
| Talleres | `Talleres` |
| Gastos fijos mensuales | `Gastos_Fijos` |
| Gastos operativos y devoluciones | `Gastos Operativos` |

**`Seguimiento Caja` está pendiente** (decisión del usuario, 2026-07-26). Hoy
la tabla réplica tiene 1 fila sin fechas y no aporta ninguna entrada: todo
julio 2026 sale de Bitácora + Talleres. El código todavía la consulta primero
y usa Bitácora/Agenda como fallback, lo cual da el resultado correcto
mientras Caja siga vacía. Si algún día se empieza a llenar, revisar que no
duplique con el fallback (la deduplicación es por `stable_external_id` y por
`calendar_event_id`).

## El abono de reserva NO es plata disponible

Es la regla que más se malinterpreta. La clienta abona una parte para
reservar la fecha; **ese dinero recién es del estudio cuando el servicio se
realiza**. Hasta entonces es un compromiso, no un ingreso.

La cadena que muestra el dashboard:

```
Vendido            contracted_sales    todos los servicios registrados
Dinero recibido    collected_revenue   toda la plata que entró (abonos + pagos completos)
🔒 Retenido        committed_deposits  saldo de abonos de servicios AÚN NO realizados al cierre del período
✅ Liberado        released_revenue     parte de lo recibido cuyo servicio ya se realizó
Pendiente de cobro accounts_receivable  vendido − recibido − devoluciones
```

Y el resultado del período:

```
Resultado disponible = max(0, realized_revenue − costos − gastos operativos − gastos fijos − devoluciones)
```

`realized_revenue` cuenta **solo servicios en estado realizado**
(`realizada/realizado/terminado/ejecutado/completed`). Un abono de un
servicio futuro nunca infla el resultado.

Cuando los ingresos realizados no alcanzan para cubrir gastos fijos, caja
queda en **$0**. Los servicios realizados primero cubren el gasto fijo; solo el
excedente se considera disponible/caja.

## Vista mensual y rango

La pestaña Finanzas abre por defecto en modo mensual, usando el mes actual en
America/Santiago. En ese modo el selector principal es el mes y el dashboard
debe mostrar la brecha real para cubrir gastos fijos:

```
Nos falta MONTO para cubrir el gasto fijo de este mes.
```

`MONTO` se calcula como lo que falta para que los ingresos realizados del mes,
después de costos directos, otros gastos y devoluciones, cubran los gastos
fijos confirmados.

El cálculo por rango existe solo como modo secundario: al activar “Calcular
por rango de fechas” se habilitan Desde/Hasta, se deshabilita el selector de
mes y no se muestra el mensaje mensual de gasto fijo.

> ⚠️ El abono **no es siempre el 50%**. En los datos reales de 2026 va del
> 25% al 100% (ej. Camila Soto abonó 60.000 de 244.500 = 25%). El sistema usa
> el monto realmente abonado, no un porcentaje fijo. No hardcodear 50%.

### Ejemplo verificado (julio 2026)

| Métrica | Monto | De dónde sale |
|---|---|---|
| Vendido | $217.530 | $112.530 (Nadia, 27/07) + $105.000 (talleres) |
| Dinero recibido | $165.000 | $60.000 abono de Nadia + $105.000 talleres |
| 🔒 Retenido | $370.000 | abonos abiertos al 31/07: Nadia, Cecilia, Camila Soto, Maria Bravo y Verónica Garate |
| ✅ Liberado | $105.000 | solo los talleres, ya realizados |
| Pendiente de cobro | $52.530 | saldo de Nadia |
| Gastos fijos | $309.110 | Gastos_Fijos confirmados mensuales |
| **Resultado** | **$0** | $105.000 no alcanza a cubrir $309.110 de gastos fijos |

Julio no deja caja disponible porque solo se realizaron talleres y los gastos
fijos del mes son mayores. Los $60.000 de Nadia **no** lo mejoran: entran
recién cuando el servicio del 27 quede como realizado.

## Directorio de profesionales

Las planillas registran la encargada en **un solo campo de texto y con las
dos profesionales juntas**: `"Cami - Paz"` = Cami maquilladora, Paz
estilista. El orden es fijo: **primero MUA, después estilista** (confirmado
por el usuario).

`Domain/Team/StaffAssignment` separa ese texto y tolera las variantes reales
de las hojas (`Cami - Paz`, `Cami- Paz`, `Cami-Paz`, `cami -paz`, `Cami/Paz`,
`Cami y Paz`). `PostgresSheetReplicaImporter::syncStaffDirectoryFromSheets()`
corre en cada sync y:

1. puebla `qs_staff` con los nombres reales de las hojas (sin duplicar, el
   match es case-insensitive), y
2. asigna a cada reserva la **MUA** en `qs_bookings.staff_id`.

Antes de esto `qs_staff` estaba **vacía** y ninguna reserva tenía equipo: por
eso el servicio del 27/07 aparecía sin nadie aunque la planilla dijera
"Cami - Paz".

### Suciedad de datos conocida (pendiente de decisión)

El directorio refleja lo que dicen las hojas, incluidos sus errores:

- **`Yeimy` y `Yeimi`** son la misma persona escrita de dos formas → hoy son
  dos registros distintos. `qs_staff.aliases` existe para resolverlo, sin usar.
- **`Equipo`** no es una persona, es un placeholder que quedó en una fila.

No se corrigieron automáticamente: unificar personas es una decisión del
estudio, no una inferencia segura del sistema. Lo más limpio es arreglarlo en
la planilla y volver a sincronizar.

## Pendientes

- La reserva tiene **una sola** `staff_id` (la MUA); la estilista se pierde
  en la proyección. El modelo que sí soporta las dos es Bitácora
  (`mua_id` + `estilista_id`). Si se necesita el equipo completo en Reservas,
  hay que decidir si se agrega `estilista_id` a `qs_bookings` o si se lee
  desde la bitácora vinculada.
- Pagos parciales a profesionales cuando hay prueba presencial: ver la nota
  de bitácoras de prueba en `docs/audits/MIGRATION_LOG.md`.
