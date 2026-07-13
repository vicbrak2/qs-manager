# Checklist de Cierre V2

- [x] 1. Eliminar artefactos y ejecutores inseguros (`run_tests.php`, `diff.txt`).
- [x] 2. Refactorizar dependencias HTTP en tests (`GoogleSheetsCsvReader`).
- [x] 3. Extraer lógica del Worker a `ProcessSheetSyncRun` y `PostgresSyncRunRepository`.
- [x] 4. Aplicar heartbeat, control de abandonos (lease timeout) y contadores en PostgreSQL.
- [x] 5. Prevenir ejecución duplicada en API con `pg_advisory_xact_lock()`.
- [x] 6. Ajustar polling UI con `AbortController` (cada 2s, max 5m, sin fallar por timeout).
- [x] 7. Escribir tests de integración de todo el ciclo de vida del Worker.
- [x] 8. Configurar script de migración y Healthcheck de Docker para el Worker.
- [x] 9. Crear suite Playwright E2E (Desktop/Mobile) con API mockeada.
- [ ] 10. Validar sync real de extremo a extremo (E2E).
- [ ] 11. Organizar documentación en `docs/` con evidencia.
- [ ] 12. Revisar árbol limpio, whitespace y *commits* segregados.
