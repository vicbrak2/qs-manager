# Architectural Decisions (ADR Summary)

Este documento centraliza las decisiones arquitectónicas fundamentales del proyecto para mantener el repositorio limpio y el contexto accesible.

| ID | Título | Decisión / Estado |
|---|---|---|
| 001 | Monolito Modular | `qs-core` se implementa como un solo plugin desplegable con módulos internos. |
| 002 | REST API Nativa | La interfaz pública inicial usa WordPress REST API en `qs/v1`. |
| 003 | LatePoint via wpdb | LatePoint se integrará vía adapter `$wpdb` encapsulado en el módulo Booking. |
| 004 | Capas Domain App Infra | El dominio no depende de WordPress; infraestructura encapsula IO y WordPress. |
| 005 | CPT vs Tablas Custom | QS usa CPT para contenido operativo y tablas custom para datos relacionales críticos. |
| 006 | Estrategia de Calidad | PHPStan, PHPUnit y mutation testing se adoptan por fases. |
| 007 | Roles y Permisos QS | Los roles iniciales son `qs_admin`, `qs_coordinadora` y `qs_staff`. |
| 008 | Convenciones de Módulos | El namespace base es `QS\` e los módulos viven bajo `app/Modules`. |
| 009 | Reglas de Arquitectura | Las reglas de capa y acceso a WordPress se verifican con tests de arquitectura. |
| 010 | Automatización n8n | La automatización operativa se delega a n8n self-hosted con webhooks desde `qs-core`. |
| 011 | Boilerplate Genérico | Implementación de estructura base modular para plugins de WordPress. |
