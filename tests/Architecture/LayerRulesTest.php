<?php

declare(strict_types=1);

namespace QS\Tests\Architecture;

use QS\Shared\Testing\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class LayerRulesTest extends TestCase
{
    public function testDomainLayerDoesNotReferenceWordPressOrInfrastructureConcerns(): void
    {
        $domainRoot = QS_CORE_ROOT_DIR . '/app/Modules';
        $forbiddenPatterns = [
            '$wpdb',
            'register_rest_route',
            'add_action',
            'add_filter',
            'Infrastructure\\',
            'Wordpress\\',
            'WP_',
        ];

        $violations = [];

        foreach ($this->phpFilesIn($domainRoot) as $file) {
            if (! str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'Domain' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if ($contents === false) {
                continue;
            }

            foreach ($forbiddenPatterns as $pattern) {
                if (str_contains($contents, $pattern)) {
                    $violations[] = sprintf('%s contains forbidden pattern "%s".', $file->getPathname(), $pattern);
                }
            }
        }

        self::assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function testCoreDoesNotContainProductSpecificStrings(): void
    {
        $coreRoot = QS_CORE_ROOT_DIR . '/app/Core';
        $forbiddenPatterns = [
            'qs-core',
            'QS Core',
            'Qamiluna',
            'qamiluna',
            'qs_',
        ];

        $violations = [];

        foreach ($this->phpFilesIn($coreRoot) as $file) {
            $contents = file_get_contents($file->getPathname());

            if ($contents === false) {
                continue;
            }

            foreach ($forbiddenPatterns as $pattern) {
                if (str_contains($contents, $pattern)) {
                    $violations[] = sprintf('%s contains forbidden pattern "%s".', $file->getPathname(), $pattern);
                }
            }
        }

        self::assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function phpFilesIn(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $files = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->isDir()) {
                continue;
            }

            if ($file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }
}
