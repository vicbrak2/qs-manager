<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);
$configPath = $repoRoot . '/config/quality/project-structure.json';

if (! is_file($configPath)) {
    fwrite(STDERR, "Missing structure config: config/quality/project-structure.json\n");
    exit(1);
}

$rawConfig = file_get_contents($configPath);

if ($rawConfig === false) {
    fwrite(STDERR, "Could not read structure config.\n");
    exit(1);
}

/** @var array{
 *  allowed_root_entries: list<string>,
 *  restricted_paths: list<array{
 *      relative_path: string,
 *      description: string,
 *      allowed_extensions: list<string>,
 *      allowed_filenames: list<string>
 *  }>
 * }|null $config
 */
$config = json_decode($rawConfig, true);

if (! is_array($config)) {
    fwrite(STDERR, "Could not parse structure config JSON.\n");
    exit(1);
}

$errors = [];
$allowedRootEntries = array_fill_keys($config['allowed_root_entries'], true);

$rootIterator = new DirectoryIterator($repoRoot);

foreach ($rootIterator as $entry) {
    if ($entry->isDot()) {
        continue;
    }

    $name = $entry->getFilename();

    if (isset($allowedRootEntries[$name])) {
        continue;
    }

    if (isGitIgnored($repoRoot, $name)) {
        continue;
    }

    $errors[] = sprintf(
        'Raiz del repo: "%s" no esta permitido. Muevelo a la carpeta correcta o agregalo a la politica de estructura.',
        $name
    );
}

foreach ($config['restricted_paths'] as $rule) {
    $absolutePath = $repoRoot . '/' . $rule['relative_path'];

    if (! is_dir($absolutePath)) {
        continue;
    }

    $allowedExtensions = array_fill_keys($rule['allowed_extensions'], true);
    $allowedFilenames = array_fill_keys($rule['allowed_filenames'], true);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (! $item->isFile()) {
            continue;
        }

        $relativePath = normalizeRelativePath($repoRoot, $item->getPathname());

        if (isGitIgnored($repoRoot, $relativePath)) {
            continue;
        }

        $filename = basename($relativePath);

        if (isset($allowedFilenames[$filename])) {
            continue;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension !== '' && isset($allowedExtensions[$extension])) {
            continue;
        }

        $errors[] = sprintf(
            '"%s" contiene "%s", pero ahi deben vivir %s.',
            $rule['relative_path'],
            $relativePath,
            $rule['description']
        );
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Project structure validation failed.\n\n");

    foreach ($errors as $error) {
        fwrite(STDERR, sprintf("- %s\n", $error));
    }

    fwrite(STDERR, "\nSugerencias:\n");
    fwrite(STDERR, "- scripts y utilidades ejecutables -> tools/\n");
    fwrite(STDERR, "- compose, workflows y definiciones de despliegue -> infrastructure/\n");
    fwrite(STDERR, "- dumps, snapshots y archivos temporales locales -> var/tmp/\n");

    exit(1);
}

fwrite(STDOUT, "Project structure validation passed.\n");

/**
 * @return bool
 */
function isGitIgnored(string $repoRoot, string $relativePath): bool
{
    $stderrRedirect = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';
    $command = sprintf(
        'git -C %s check-ignore -q --no-index -- %s %s',
        escapeshellarg($repoRoot),
        escapeshellarg(str_replace('\\', '/', $relativePath)),
        $stderrRedirect
    );

    exec($command, $output, $exitCode);

    return $exitCode === 0;
}

/**
 * @return string
 */
function normalizeRelativePath(string $repoRoot, string $absolutePath): string
{
    $normalizedRoot = rtrim(str_replace('\\', '/', $repoRoot), '/');
    $normalizedPath = str_replace('\\', '/', $absolutePath);

    return ltrim(substr($normalizedPath, strlen($normalizedRoot)), '/');
}
