<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$distRoot = $root . '/dist';
$packageRoot = $distRoot . '/qs-core';
$zipPath = $distRoot . '/qs-core.zip';

$directories = [
    'app',
    'config',
    'database',
    'vendor',
];

$files = [
    'composer.json',
    'composer.lock',
    'qs-core.php',
    'readme.txt',
    'uninstall.php',
];

rrmdir($distRoot);

if (! mkdir($packageRoot, 0777, true) && ! is_dir($packageRoot)) {
    fwrite(STDERR, "Could not create package directory.\n");
    exit(1);
}

foreach ($directories as $directory) {
    $source = $root . '/' . $directory;
    $target = $packageRoot . '/' . $directory;

    if (! is_dir($source)) {
        fwrite(STDERR, sprintf("Missing directory: %s\n", $directory));
        exit(1);
    }

    rcopy($source, $target);
}

foreach ($files as $file) {
    $source = $root . '/' . $file;
    $target = $packageRoot . '/' . $file;

    if (! is_file($source)) {
        fwrite(STDERR, sprintf("Missing file: %s\n", $file));
        exit(1);
    }

    if (! copy($source, $target)) {
        fwrite(STDERR, sprintf("Could not copy file: %s\n", $file));
        exit(1);
    }
}

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "ZipArchive is not available.\n");
    exit(1);
}

$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not create zip archive.\n");
    exit(1);
}

addDirectoryToZip($zip, $packageRoot, basename($packageRoot));
$zip->close();

fwrite(STDOUT, sprintf("Package created: %s\n", $zipPath));

/**
 * @return void
 */
function rrmdir(string $path): void
{
    if (! file_exists($path)) {
        return;
    }

    if (removePathWithSystemCommand($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @chmod($path, 0777);
        unlink($path);

        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $pathname = $item->getPathname();
        @chmod($pathname, 0777);

        if ($item->isDir()) {
            rmdir($pathname);
            continue;
        }

        unlink($pathname);
    }

    rmdir($path);
}

/**
 * @return bool
 */
function removePathWithSystemCommand(string $path): bool
{
    if (! file_exists($path)) {
        return true;
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        $command = sprintf(
            'cmd /c if exist "%s" rmdir /s /q "%s" >nul 2>nul',
            str_replace('/', '\\', $path),
            str_replace('/', '\\', $path)
        );
    } else {
        $command = sprintf('rm -rf %s >/dev/null 2>&1', escapeshellarg($path));
    }

    exec($command, $output, $exitCode);

    return $exitCode === 0 && ! file_exists($path);
}

/**
 * @return void
 */
function rcopy(string $source, string $destination): void
{
    if (! is_dir($destination) && ! mkdir($destination, 0777, true) && ! is_dir($destination)) {
        throw new RuntimeException(sprintf('Could not create directory: %s', $destination));
    }

    $items = scandir($source);

    if ($items === false) {
        throw new RuntimeException(sprintf('Could not read directory: %s', $source));
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $sourcePath = $source . '/' . $item;
        $destinationPath = $destination . '/' . $item;

        if (is_dir($sourcePath)) {
            rcopy($sourcePath, $destinationPath);
            continue;
        }

        if (! copy($sourcePath, $destinationPath)) {
            throw new RuntimeException(sprintf('Could not copy file: %s', $sourcePath));
        }
    }
}

/**
 * @return void
 */
function addDirectoryToZip(ZipArchive $zip, string $source, string $prefix): void
{
    $items = scandir($source);

    if ($items === false) {
        throw new RuntimeException(sprintf('Could not read directory for zip: %s', $source));
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $sourcePath = $source . '/' . $item;
        $zipPath = $prefix . '/' . $item;

        if (is_dir($sourcePath)) {
            $zip->addEmptyDir($zipPath);
            addDirectoryToZip($zip, $sourcePath, $zipPath);
            continue;
        }

        $zip->addFile($sourcePath, $zipPath);
    }
}
