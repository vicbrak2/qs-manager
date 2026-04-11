# ADR-011: Extracción de Arquitectura Base a un Boilerplate Genérico para Plugins de WordPress

**Fecha:** 2026-04-11
**Estatus:** Propuesta
**Autores:** Equipo de Desarrollo

## Contexto

El repositorio `qs-manager` contiene una base técnica que puede reutilizarse en futuros plugins de WordPress: arquitectura modular inspirada en Domain-Driven Design, inyección de dependencias, registro estructurado de endpoints y hooks, integraciones de infraestructura como n8n y un conjunto de herramientas de calidad basado en PHP-CS-Fixer y PHPUnit.

Cuando se crea un nuevo plugin, partir desde cero genera fricción:

1. Se reimplementan bootstrap, autoload, routing, hooks y configuración de servicios.
2. Se pierde consistencia en estilo, pruebas y organización modular.
3. Aumenta el riesgo de volver a estructuras globales difíciles de testear y mantener.
4. Se mezclan decisiones técnicas reutilizables con reglas de negocio específicas de cada cliente o producto.

## Decisión

Se decide extraer el núcleo arquitectónico reutilizable de `qs-manager` para crear un boilerplate independiente para nuevos plugins de WordPress.

El boilerplate debe contener únicamente infraestructura técnica y patrones de desarrollo: bootstrap, autoload, contenedor de dependencias, registro de hooks, registro de endpoints, configuración, tooling de calidad, documentación base y un módulo de ejemplo removible.

Quedan fuera del boilerplate las reglas de negocio, copy, modelos, tablas, workflows n8n, slugs, roles y cualquier referencia específica a Qamiluna Studio o QS Manager.

## Consecuencias

### Positivas

- **Arranque más rápido:** un proyecto nuevo podrá iniciar desde una base funcional en vez de repetir configuración técnica.
- **Consistencia técnica:** los plugins nuevos heredarán convenciones de namespaces, estructura modular, tooling de calidad y pruebas base.
- **Menor acoplamiento:** la extracción obliga a separar núcleo técnico de reglas de negocio, lo que también mejora la claridad de `qs-manager`.
- **Mejor documentación de arquitectura:** el boilerplate puede incluir ADRs y ejemplos mínimos para alinear a futuros desarrolladores.

### Negativas

- **Mantenimiento adicional:** habrá un repositorio extra que deberá mantenerse actualizado cuando evolucione la arquitectura base.
- **Esfuerzo inicial:** la auditoría y limpieza de referencias específicas puede tomar tiempo antes de lograr una plantilla realmente genérica.
- **Riesgo de sobregeneralización:** el template debe evitar incorporar abstracciones que no estén justificadas por necesidades reales.
- **Sincronización futura:** las mejoras realizadas en proyectos derivados no volverán automáticamente al boilerplate; se requerirá un flujo explícito de retroalimentación.

## Alternativas Consideradas

- **Usar WordPress Plugin Boilerplate (WPPB):** rechazado. Es una referencia conocida de la comunidad, pero no cubre la arquitectura modular, el contenedor de dependencias ni las convenciones de calidad ya adoptadas por este proyecto.
- **Duplicar el repositorio actual:** rechazado. Copiar `qs-manager` y borrar piezas manualmente por cada proyecto aumenta el riesgo de arrastrar código residual, credenciales, slugs, workflows o decisiones de negocio.
- **Mantener una rama template dentro de `qs-manager`:** rechazado por ahora. Reduciría el número de repositorios, pero mantendría cerca dos ciclos de vida distintos: producto real y plantilla genérica.

## Criterios de Aceptación

- El boilerplate no contiene referencias a Qamiluna Studio, QS Manager ni entidades de negocio del plugin actual.
- Un plugin generado desde la plantilla puede instalarse y cargar en WordPress sin errores fatales.
- El setup inicial permite configurar namespace, slug, nombre del plugin, autor y dominio de texto.
- El módulo de ejemplo demuestra el patrón sin depender de servicios externos.
- Las herramientas base de Composer, PHP-CS-Fixer y PHPUnit quedan documentadas y ejecutables desde un clon limpio.
