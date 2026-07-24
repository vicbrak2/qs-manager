<?php

declare(strict_types=1);

namespace QSManager\Tests\Architecture\Support;

use SplFileInfo;

/**
 * Helper minimo para recorrer archivos .php de app/ en los tests de
 * arquitectura (Fase 6 del plan de migracion). Sin dependencias externas,
 * KISS: un metodo, sin estado.
 */
trait PhpFileScanner
{
    /**
     * @return list<SplFileInfo>
     */
    private static function phpFilesUnder(string $relativeDir): array
    {
        $root = dirname(__DIR__, 3) . '/' . ltrim($relativeDir, '/');
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    /** Ruta relativa a la raiz del proyecto (para mensajes de error legibles). */
    private static function relativePath(SplFileInfo $file): string
    {
        $projectRoot = dirname(__DIR__, 3) . '/';

        return str_replace('\\', '/', str_replace($projectRoot, '', $file->getPathname()));
    }
}
