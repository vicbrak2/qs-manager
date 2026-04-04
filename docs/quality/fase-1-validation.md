# Fase 1 Validation Checklist

## Objetivo

Cerrar Fase 1 con validacion ejecutable para `IdentityAccess`, `Team` y `Booking`.

## Comandos esperados

```powershell
composer install
vendor/bin/php-cs-fixer fix --dry-run --config=config/quality/.php-cs-fixer.dist.php
vendor/bin/phpstan analyse --configuration=config/quality/phpstan.neon
vendor/bin/phpunit --configuration phpunit.xml.dist --coverage-text
```

## Smoke tests WordPress

1. Activar el plugin desde `plugin/qs-core.php`.
2. Confirmar creacion/actualizacion de tablas `wp_qs_*`.
3. Confirmar registro de roles `qs_admin`, `qs_coordinadora`, `qs_staff`.
4. Probar `GET /wp-json/qs/v1/health`.
5. Probar `GET /wp-json/qs/v1/version`.
6. Probar con usuario autenticado:
   `GET /wp-json/qs/v1/staff`
   `GET /wp-json/qs/v1/staff/{id}`
   `GET /wp-json/qs/v1/staff/{id}/availability`
   `GET /wp-json/qs/v1/muas`
   `GET /wp-json/qs/v1/muas/{id}`
   `GET /wp-json/qs/v1/muas/{id}/agenda`
   `GET /wp-json/qs/v1/bookings`
   `GET /wp-json/qs/v1/bookings/today`
   `GET /wp-json/qs/v1/bookings/{id}`

## Criterios de aceptacion

- PHPStan nivel 6 sin errores.
- PHPUnit en verde.
- Cobertura reportada para nuevos tests de dominio y REST.
- El bootstrap del plugin no rompe activacion ni `rest_api_init`.
- LatePoint sin tablas `lp_*` debe degradar a listas vacias, no fatal error.

## Riesgos conocidos

- No se pudo ejecutar esta validacion localmente en este workspace porque `php` no esta disponible en PATH.
- La agenda MUA actualmente asume que el identificador consumido por `/muas/{id}/agenda` es compatible con `lp_agents.id`; si no lo es, se requerira una estrategia de mapeo explicita en la siguiente iteracion.
