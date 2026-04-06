# Corpus Publico Cleanup

Usa este checklist para dejar el chatbot publico de Qamiluna Studio con un corpus coherente y sin mezclar material interno o benchmark.

## Debe quedar

Conservar solo los 13 documentos cargados desde `qamiluna-rag-publico.json`:

- Qamiluna | Servicios publicos principales
- Qamiluna | Servicios para novia civil y novia fiesta
- Qamiluna | Maquillaje y peinado social
- Qamiluna | Reserva con abono y pago de saldo
- Qamiluna | Anticipacion recomendada y confirmacion de disponibilidad
- Qamiluna | Servicio a domicilio y traslado
- Qamiluna | Capacidad maxima por matrimonio
- Qamiluna | Precios publicos referenciales para novia
- Qamiluna | Datos minimos para cotizar bien
- Qamiluna | Tono de atencion y propuesta de marca
- Qamiluna | Checklist operativo para una reserva
- Qamiluna | Estilo conversacional del asistente
- Qamiluna | Variacion natural en saludos y cierres

## Debe salir

Eliminar del chatbot publico:

- Todo documento con `source_name = qs-rag-atencion.json`
- Todo documento con `source_name = manual`
- Todo documento cuyo titulo comience con `Benchmark`
- Todo documento cuyo titulo contenga `uso interno`
- Todo documento de prueba o synthetic content cargado para validar la ingesta

## Orden recomendado

1. Respaldar la opcion `qs_chatbot_context_documents`.
2. Dejar en WordPress solo los 13 documentos de `qamiluna-rag-publico.json`.
3. Ejecutar reindexacion completa.
4. Probar consultas cortas:
   - `servicios`
   - `novia`
   - `precios`
   - `reservas`
   - `traslado`
   - `que es qamiluna studio`

## Resultado esperado

El chatbot publico debe responder sobre Qamiluna Studio, sin mencionar `QS Manager`, sin traer benchmark competitivo y sin usar reglas internas en conversaciones con clientas.
