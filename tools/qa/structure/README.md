# Project Structure Validation

Valida dos cosas:

- que la raiz del repo no acumule archivos o carpetas fuera de la politica permitida;
- que `infrastructure/n8n/` mantenga solo definiciones de despliegue y workflows, mientras `tools/n8n/` concentre scripts operativos.

## Ejecutar manualmente

```bash
composer run validate:structure
```

O, si quieres probar exactamente lo que ejecuta el hook local:

```powershell
pwsh -NoProfile -File tools/qa/structure/validate-project-structure.ps1
```

## Integracion

- `pre-commit` local via `.git/hooks/pre-commit`;
- job `quality` en GitHub Actions;
- `composer run package`, para bloquear builds con estructura desordenada.
