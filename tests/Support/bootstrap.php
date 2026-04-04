<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__, 2);
$autoload = $rootDir . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
}

if (! defined('QS_CORE_ROOT_DIR')) {
    define('QS_CORE_ROOT_DIR', $rootDir);
}
