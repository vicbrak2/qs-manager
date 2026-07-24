<?php

declare(strict_types=1);

namespace QSManager\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use QSManager\Tests\Architecture\Support\PhpFileScanner;

/**
 * Guardrail (Fase 6): app/Domain/** es dominio puro -- reglas de negocio sin
 * saber de base de datos, framework HTTP ni entrada de usuario cruda. Si
 * esto falla, algo en Domain esta haciendo el trabajo de Infrastructure o
 * Interfaces, y hay que mover esa logica, no silenciar el test.
 */
final class DomainPurityTest extends TestCase
{
    use PhpFileScanner;

    /** @var array<string, string> patron => descripcion humana */
    private const FORBIDDEN = [
        '/\bnew\s+PDO\b/'        => 'PDO (persistencia -- pertenece a Infrastructure)',
        '/\bPDOStatement\b/'     => 'PDOStatement (persistencia)',
        '/\\\\Slim\\\\/'         => 'Slim (framework HTTP -- pertenece a Interfaces/Http)',
        '/\bcurl_init\s*\(/'     => 'curl_init (HTTP saliente -- pertenece a Infrastructure)',
        '/\$_ENV\b/'             => '$_ENV (config -- pertenece a Infrastructure/Http/AppFactory o similar)',
        '/\$_POST\b/'            => '$_POST (entrada HTTP cruda -- pertenece a Interfaces/Http)',
        '/\$_GET\b/'             => '$_GET (entrada HTTP cruda -- pertenece a Interfaces/Http)',
        '/\$_SERVER\b/'          => '$_SERVER (entorno HTTP -- pertenece a Interfaces/Http)',
    ];

    public function testDomainLayerHasNoInfrastructureOrHttpLeakage(): void
    {
        $violations = [];

        foreach (self::phpFilesUnder('app/Domain') as $file) {
            $content = (string) file_get_contents($file->getPathname());

            foreach (self::FORBIDDEN as $pattern => $why) {
                if (preg_match($pattern, $content) === 1) {
                    $violations[] = self::relativePath($file) . " -- usa $why";
                }
            }
        }

        self::assertSame(
            [],
            $violations,
            "app/Domain no es dominio puro:\n" . implode("\n", $violations)
        );
    }
}
