<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (! defined('QS_CORE_ROOT_DIR')) {
    define('QS_CORE_ROOT_DIR', __DIR__);
}

if (! defined('QS_CORE_PLUGIN_DIR')) {
    define('QS_CORE_PLUGIN_DIR', __DIR__);
}

if (! defined('QS_CORE_PLUGIN_FILE')) {
    define('QS_CORE_PLUGIN_FILE', __DIR__ . '/qs-core.php');
}

$autoload = __DIR__ . '/vendor/autoload.php';

if (! file_exists($autoload)) {
    return;
}

require_once $autoload;

$bootstrapper = new \QS\Core\Bootstrap\PluginBootstrapper(__DIR__);
$bootstrapper->uninstall();
