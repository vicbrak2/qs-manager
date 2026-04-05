<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__, 2);
$sourceDir = $repoRoot . '/.githooks';
$targetDir = $repoRoot . '/.git/hooks';
$hooks = ['pre-commit'];

if (! is_dir($sourceDir) || ! is_dir($targetDir)) {
    fwrite(STDOUT, "Skipping git hook installation.\n");
    exit(0);
}

foreach ($hooks as $hook) {
    $source = $sourceDir . '/' . $hook;
    $target = $targetDir . '/' . $hook;

    if (! is_file($source)) {
        fwrite(STDERR, sprintf("Missing hook source: %s\n", $source));
        exit(1);
    }

    if (! copy($source, $target)) {
        fwrite(STDERR, sprintf("Could not install hook: %s\n", $hook));
        exit(1);
    }

    @chmod($target, 0755);
    fwrite(STDOUT, sprintf("Installed git hook: %s\n", $hook));
}
