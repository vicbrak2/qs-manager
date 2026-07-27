# Cierre de servicio y liberacion de reserva

Cuando un servicio ya fue realizado, la reserva deja de estar retenida y pasa a
Finanzas como ingreso liberado. La app lo hace marcando la reserva como
`completed` y el `Estado servicio` como `Realizado`.

## Que hacer en la app

1. Ir a `Reservas`.
2. Buscar la reserva del servicio terminado.
3. Revisar que el abono, saldo, total y estado de pago esten correctos.
4. Si corresponde, abrir o crear la bitacora y registrar costos/pagos del equipo.
5. Presionar `Terminar servicio`.
6. Confirmar la accion.
7. Si GAS esta configurado, esperar la sincronizacion automatica de Sheets.
8. Revisar `Finanzas` en el mes del servicio.

## Que actualiza la accion

- En la base local:
  - `status = completed`
  - `service_status = Realizado`
- En GAS/Sheets:
  - La reserva se envia con `estado_servicio = Realizado`.
  - Agenda 2026 queda como fuente para que el importador la lea como servicio
    realizado.
- Si GAS no esta configurado, la app deja el servicio terminado localmente y
  muestra un aviso; en ese caso Sheets no queda actualizado hasta habilitar GAS
  o corregir la fila manualmente.
- En Finanzas, despues de sincronizar Sheets:
  - El abono deja de aparecer en `Retenido como reserva`.
  - El dinero recibido por ese servicio aparece en `Liberado`.
  - El total del servicio aparece en `Ingresos realizados`.
  - Los costos profesionales registrados aparecen en `Pago a profesionales`.
  - El disponible cubre primero gastos fijos; si no alcanza, se muestra en `$0`.

## Regla financiera

```text
Ingreso liberado del servicio
- pago a profesionales
- gastos fijos del mes
- otros gastos/devoluciones
= resultado disponible
```

Si el resultado es negativo, la app lo muestra como `$0` para no confundir caja
disponible con deficit contable. El mensaje mensual indica cuanto falta para
cubrir el gasto fijo.
