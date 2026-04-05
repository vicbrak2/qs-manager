<?php

declare(strict_types=1);

use QS\Core\Container\ContainerBuilder;
use QS\Modules\Setup\Application\Command\SetupSiteCommand;
use QS\Modules\Setup\Application\CommandHandler\SetupSiteHandler;

$rootDir = dirname(__DIR__, 2);

if (! defined('QS_CORE_ROOT_DIR')) {
    define('QS_CORE_ROOT_DIR', $rootDir);
}

if (! defined('QS_CORE_PLUGIN_DIR')) {
    define('QS_CORE_PLUGIN_DIR', $rootDir);
}

if (! defined('QS_CORE_PLUGIN_FILE')) {
    define('QS_CORE_PLUGIN_FILE', $rootDir . '/qs-core.php');
}

$autoload = $rootDir . '/vendor/autoload.php';

if (! file_exists($autoload)) {
    fwrite(STDERR, "No se encontro vendor/autoload.php. Ejecuta composer install en la raiz del plugin.\n");
    exit(1);
}

require_once $autoload;

try {
    ensureWordPressLoaded(parseArguments($_SERVER['argv'] ?? []), $rootDir);

    $container = (new ContainerBuilder($rootDir))->build();
    $command = SetupSiteCommand::fromInput(parseArguments($_SERVER['argv'] ?? []), SetupSiteCommand::defaults());
    $result = $container->get(SetupSiteHandler::class)->handle($command);

    outputJson($result);
    exit(0);
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string>
 */
function parseArguments(array $arguments): array
{
    $parsed = [];

    foreach (array_slice($arguments, 1) as $argument) {
        if (! str_starts_with($argument, '--')) {
            continue;
        }

        $normalized = substr($argument, 2);

        if (str_contains($normalized, '=')) {
            [$key, $value] = explode('=', $normalized, 2);
            $parsed[$key] = $value;
            continue;
        }

        $parsed[$normalized] = 'true';
    }

    return $parsed;
}

/**
 * @param array<string, string> $arguments
 */
function ensureWordPressLoaded(array $arguments, string $rootDir): void
{
    if (defined('ABSPATH')) {
        return;
    }

    $explicitPath = $arguments['wp-load'] ?? getenv('QS_WP_LOAD') ?: '';
    $candidates = [];

    if (is_string($explicitPath) && trim($explicitPath) !== '') {
        $candidates[] = trim($explicitPath);
    }

    $searchRoot = $rootDir;

    for ($depth = 0; $depth < 6; $depth++) {
        $candidates[] = $searchRoot . '/wp-load.php';
        $searchRoot = dirname($searchRoot);
    }

    foreach (array_unique($candidates) as $candidate) {
        if (is_string($candidate) && file_exists($candidate)) {
            require_once $candidate;
            return;
        }
    }

    throw new RuntimeException('No se encontro wp-load.php. Usa --wp-load=<ruta> o ejecuta wp eval-file tools/setup/wp-setup.php.');
}

/**
 * @param array<string, mixed> $payload
 */
function outputJson(array $payload): void
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (! is_string($json)) {
        fwrite(STDOUT, print_r($payload, true) . PHP_EOL);
        return;
    }

    fwrite(STDOUT, $json . PHP_EOL);
}
