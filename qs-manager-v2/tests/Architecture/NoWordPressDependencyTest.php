<?php

declare(strict_types=1);

namespace QSManager\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use QSManager\Tests\Architecture\Support\PhpFileScanner;

/**
 * Guardrail (Fase 6): V2 es un standalone app, no puede volver a acoplarse a
 * WordPress. Si este test falla, algo en app/ referencia wp_*, WP_*, $wpdb,
 * hooks o el REST API de WordPress -- exactamente lo que Fase 0/2 del plan
 * de migracion prohibio explicitamente.
 *
 * Ver docs/audits/deprecated-v1-components.md para el inventario de todo lo
 * que quedo fuera de V2 por esta misma razon.
 */
final class NoWordPressDependencyTest extends TestCase
{
    use PhpFileScanner;

    /** @var list<string> */
    private const FORBIDDEN_PATTERNS = [
        '/\bwp_[a-z_]+\s*\(/',
        '/\bWP_[A-Za-z_]+\b/',
        '/\$wpdb\b/',
        '/\badd_action\s*\(/',
        '/\badd_filter\s*\(/',
        '/\bregister_rest_route\s*\(/',
        '/\bdo_action\s*\(/',
        '/\bapply_filters\s*\(/',
    ];

    public function testAppSourceHasNoWordPressReferences(): void
    {
        $violations = [];

        foreach (self::phpFilesUnder('app') as $file) {
            $content = (string) file_get_contents($file->getPathname());

            foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                if (preg_match($pattern, $content, $m) === 1) {
                    $violations[] = self::relativePath($file) . " -- coincide con {$pattern} ({$m[0]})";
                    break; // un hallazgo por archivo alcanza para reportar
                }
            }
        }

        self::assertSame(
            [],
            $violations,
            "Se encontraron referencias a WordPress en app/ (prohibido para V2):\n"
            . implode("\n", $violations)
            . "\n\nSi el codigo realmente necesita esto, no pertenece a qs-manager-v2/app --"
            . " revisa docs/audits/deprecated-v1-components.md."
        );
    }
}
