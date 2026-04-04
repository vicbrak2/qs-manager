<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__, 2);

if (! defined('QS_CORE_ROOT_DIR')) {
    define('QS_CORE_ROOT_DIR', $rootDir);
}

if (! defined('ABSPATH')) {
    define('ABSPATH', $rootDir . DIRECTORY_SEPARATOR);
}

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
