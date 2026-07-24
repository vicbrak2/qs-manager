<?php

declare(strict_types=1);

namespace QSManager\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use QSManager\Tests\Architecture\Support\PhpFileScanner;

/**
 * Guardrail (Fase 6): archivos y clases que crecen sin limite son la razon
 * por la que V1 termino con un ReindexAdminPage.php de 1358 lineas. Este
 * test falla si CUALQUIER archivo nuevo (no listado en $legacyLineExceptions)
 * supera el umbral, o si un archivo ya excepcionado crece TODAVIA MAS.
 *
 * Las excepciones son deuda tecnica conocida y ya priorizada en el plan de
 * migracion (ver Fase 4) -- no se "perdonan" para siempre, solo se congela
 * su tamano actual hasta que se refactoricen.
 */
final class FileSizeGuardrailTest extends TestCase
{
    use PhpFileScanner;

    private const MAX_LINES = 700;
    private const MAX_METHODS = 25;

    /**
     * Archivos que YA superan MAX_LINES al momento de escribir este
     * guardrail. No pueden crecer mas; deben achicarse, no congelarse para
     * siempre.
     *
     * PostgresSheetReplicaImporter.php salio de esta lista en Fase 4: paso
     * de 1509 a ~350 lineas al partirse en SheetRowMapper +
     * SheetImportSource + BookingProjectionWriter + Importers/*.
     *
     * @var array<string, int> ruta relativa => lineas actuales (baseline)
     */
    private const LEGACY_LINE_EXCEPTIONS = [];

    /**
     * Idem para cantidad de metodos por archivo.
     *
     * @var array<string, int>
     */
    private const LEGACY_METHOD_EXCEPTIONS = [
        'app/Domain/Booking/Booking.php' => 26,
    ];

    public function testNoFileExceedsMaxLinesWithoutBaselineException(): void
    {
        $violations = [];

        foreach (self::phpFilesUnder('app') as $file) {
            $path = self::relativePath($file);
            $lines = count(file($file->getPathname()) ?: []);

            $baseline = self::LEGACY_LINE_EXCEPTIONS[$path] ?? null;

            if ($baseline === null) {
                if ($lines > self::MAX_LINES) {
                    $violations[] = "$path: $lines lineas (max " . self::MAX_LINES
                        . ") -- archivo nuevo/no excepcionado, hay que partirlo.";
                }
                continue;
            }

            if ($lines > $baseline) {
                $violations[] = "$path: $lines lineas, creció desde el baseline de $baseline"
                    . " -- esta excepcion es deuda tecnica congelada, no puede crecer mas.";
            }
        }

        self::assertSame([], $violations, "Guardrail de tamano de archivo:\n" . implode("\n", $violations));
    }

    public function testNoFileExceedsMaxMethodsWithoutBaselineException(): void
    {
        $violations = [];

        foreach (self::phpFilesUnder('app') as $file) {
            $path = self::relativePath($file);
            $content = (string) file_get_contents($file->getPathname());
            $methodCount = preg_match_all(
                '/\b(?:public|private|protected)\s+(?:static\s+)?function\s+\w+/',
                $content
            );

            $baseline = self::LEGACY_METHOD_EXCEPTIONS[$path] ?? null;

            if ($baseline === null) {
                if ($methodCount > self::MAX_METHODS) {
                    $violations[] = "$path: $methodCount metodos (max " . self::MAX_METHODS
                        . ") -- archivo nuevo/no excepcionado, la clase hace demasiado.";
                }
                continue;
            }

            if ($methodCount > $baseline) {
                $violations[] = "$path: $methodCount metodos, creció desde el baseline de $baseline.";
            }
        }

        self::assertSame([], $violations, "Guardrail de metodos por archivo:\n" . implode("\n", $violations));
    }
}
