<?php
/**
 * Temporary debug script to list files.
 */

$root = dirname(__DIR__);

function list_dir($dir, $level = 0) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        echo str_repeat("  ", $level) . $file . (is_dir($dir . '/' . $file) ? '/' : '') . "\n";
        if (is_dir($dir . '/' . $file) && $level < 2) {
            list_dir($dir . '/' . $file, $level + 1);
        }
    }
}

header('Content-Type: text/plain');
echo "Listing for: $root\n";
list_dir($root);
