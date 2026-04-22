# Plan de Proyecto: Boilerplate Modular para Plugins de WordPress

## Visión General

Este documento define el plan para extraer la arquitectura modular actual de `qs-manager` hacia una plantilla genérica y reutilizable para nuevos plugins de WordPress.

El objetivo es conservar el valor técnico del proyecto actual, sin arrastrar lógica de negocio de Qamiluna Studio o QS Manager: estructura modular, arranque consistente, inyección de dependencias, endpoints, tooling de calidad y documentación base.

## 1. Alcance del Boilerplate

El código genérico debe incluir las siguientes capacidades, desacopladas de cualquier entidad, copy, tabla o flujo específico de negocio:

- **Arquitectura modular:** estructura por dominios en `app/Modules/`, con separación entre `Domain`, `Application` e `Infrastructure` cuando el módulo lo justifique.
- **Contenedor de inyección de dependencias:** mecanismo para registrar y resolver servicios sin depender de singletons globales ni instanciaciones rígidas.
- **Registro de endpoints:** clases base para registrar rutas de WP REST API y webhooks de forma consistente.
- **Gestor de hooks de WordPress:** capa orientada a objetos para suscribir métodos a `actions` y `filters` sin mezclar infraestructura de WordPress con lógica de aplicación.
- **Tooling de calidad:** configuración base de PHP-CS-Fixer, PHPUnit y validaciones locales antes de commit cuando corresponda.
- **Infraestructura opcional:** directorio `infrastructure/` con plantillas genéricas para n8n, Docker u otras piezas externas, sin asumir flujos propios de QS Manager.
- **Gestión de dependencias:** Composer como punto único para dependencias, autoload PSR-4 y scripts de automatización.

## 2. Fases de Ejecución

### Fase 1: Auditoría y Desacoplamiento

**Objetivo:** separar el núcleo técnico de la lógica propia de QS Manager.

- Identificar clases de núcleo puro: cargadores de módulos, registro de servicios, bootstrap, routing, gestión de hooks, utilidades compartidas y configuración base.
- Detectar referencias a `qs-manager`, `QsManager`, Qamiluna, entidades de negocio, tablas propias, slugs, roles, copy y flujos n8n específicos.
- Clasificar cada pieza como `Core reutilizable`, `Módulo de negocio`, `Infraestructura específica` o `Descartable`.
- Agrupar el núcleo reutilizable en una estructura clara, por ejemplo `app/Core/`, antes de extraerlo.
- Definir una lista de exclusiones explícita para evitar que el template herede comportamiento de producción del plugin actual.

### Fase 2: Extracción y Creación del Template Repo

**Objetivo:** crear un repositorio nuevo con una base limpia y reutilizable.

- Crear el repositorio del template, por ejemplo `wp-modular-boilerplate`.
- Migrar archivos de configuración base: `composer.json`, `.php-cs-fixer.php`, `phpunit.xml.dist`, `.gitignore` y scripts necesarios.
- Migrar carpetas reutilizables como `app/Core/`, `config/`, `bootstrap/`, `tests/` y `tools/`, ajustando rutas y dependencias.
- Reemplazar namespaces fijos como `QsManager\...` por placeholders o namespaces genéricos, por ejemplo `PluginNamespace\...`.
- Validar que el plugin de ejemplo se pueda instalar en WordPress sin depender del repositorio original.

### Fase 3: Scaffolding y Herramientas CLI

**Objetivo:** facilitar la personalización inicial del boilerplate.

- Crear un script de inicialización, por ejemplo `tools/setup.php`, para evitar reemplazos manuales.
- Solicitar valores mínimos: nombre del plugin, slug, namespace PHP, autor, dominio de texto y prefijo técnico.
- Reemplazar placeholders de forma determinística en archivos PHP, JSON, Markdown y configuración.
- Incluir una opción para conservar o eliminar el módulo de ejemplo.
- Ejecutar validaciones al final del setup: `composer dump-autoload`, PHP-CS-Fixer en modo dry-run y PHPUnit cuando el entorno lo permita.

### Fase 4: Documentación y Ejemplo

**Objetivo:** dejar una plantilla entendible, verificable y fácil de adoptar.

- Escribir un `README.md` con instalación, setup, estructura, comandos disponibles y convenciones.
- Incluir un módulo de ejemplo en `app/Modules/Example/` que registre un endpoint REST y, opcionalmente, un shortcode simple.
- Documentar cómo crear un módulo nuevo y dónde ubicar `Domain`, `Application` e `Infrastructure`.
- Incluir ADRs mínimos para explicar las decisiones base del template.
- Mantener el ejemplo pequeño para que enseñe el patrón sin convertirse en una segunda aplicación.

## 3. Estructura de Carpetas Propuesta

```text
wp-modular-boilerplate/
├── app/
│   ├── Core/                # Bootstrap, DI, routing, hooks y contratos base
│   └── Modules/
│       └── Example/         # Módulo de ejemplo removible
├── bootstrap/               # Arranque del plugin
├── config/                  # Configuración PHP versionable
├── database/                # Instalación o migraciones opcionales
├── docs/                    # Documentación técnica y ADRs
├── infrastructure/          # n8n, Docker u otras plantillas genéricas
├── tests/                   # Pruebas unitarias y de arquitectura
├── tools/                   # Scripts de setup y QA
├── .env.example             # Variables de entorno opcionales
├── .php-cs-fixer.php
├── composer.json
├── phpunit.xml.dist
├── plugin-bootstrap.php     # Archivo principal cargado por WordPress
└── README.md
```

## 4. Criterios de Aceptación

- El boilerplate no contiene referencias a Qamiluna, QS Manager ni entidades de negocio del repositorio original.
- El plugin generado carga en WordPress sin errores fatales.
- `composer install` y `composer dump-autoload` funcionan desde un clon limpio.
- Las pruebas base pasan o documentan explícitamente los requisitos faltantes del entorno.
- El módulo de ejemplo demuestra el patrón sin depender de servicios externos.
- El README permite crear un plugin nuevo sin consultar el repositorio `qs-manager`.

## 5. Próximos Pasos Recomendados

1. Aprobar el documento **ADR-011** adjunto.
2. Agendar un bloque de refactorización ligera en `qs-manager` para asegurar que el `Core` actual esté desacoplado.
3. Iniciar el repositorio de la plantilla.
